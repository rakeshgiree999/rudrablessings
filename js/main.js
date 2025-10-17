/* RudraBlessings – Core Frontend (client-only)
   =============================================== */

const RB_CONFIG = {
  csrfCookie: window.__RB_CSRF_COOKIE__ || 'rb_csrf'
};

let RB_PRODUCTS = Array.isArray(window.__RB_PRODUCTS__) ? window.__RB_PRODUCTS__ : [];
let RB_PRODUCTS_PROMISE = null;

function rbNormalizeProduct(product) {
  const id = Number(product?.id ?? product?.product_id ?? 0);
  const slug = product?.slug || '';
  const tag = product?.tag || product?.category_slug || 'products';
  const imagePath = product?.image_path || product?.img || (slug && tag ? `media/products/${tag}/${slug}/image1.jpg` : '');
  return {
    id,
    slug,
    name: product?.name || 'Product',
    price: Number(product?.price ?? 0),
    tag,
    img: imagePath || 'media/logo.png',
    desc: product?.short_description || product?.description || product?.desc || '',
  };
}

function rbGetCookie(name) {
  return document.cookie
    .split(';')
    .map(chunk => chunk.trim())
    .filter(Boolean)
    .map(chunk => chunk.split('='))
    .map(([key, ...rest]) => [decodeURIComponent(key), decodeURIComponent(rest.join('='))])
    .find(([key]) => key === name)?.[1] || '';
}

function rbEnsureProducts() {
  if (RB_PRODUCTS.length) {
    return Promise.resolve(RB_PRODUCTS);
  }
  if (!RB_PRODUCTS_PROMISE) {
    RB_PRODUCTS_PROMISE = apiRequest('api/catalog.php?limit=500')
      .then(data => {
        const list = Array.isArray(data?.products) ? data.products : [];
        RB_PRODUCTS = list.map(rbNormalizeProduct);
        return RB_PRODUCTS;
      })
      .catch(() => {
        RB_PRODUCTS = [];
        return RB_PRODUCTS;
      });
  }
  return RB_PRODUCTS_PROMISE;
}

function rbWithProducts(callback) {
  if (RB_PRODUCTS.length) {
    callback(RB_PRODUCTS);
    return;
  }
  rbEnsureProducts().then(products => callback(products));
}

/* ------------ Toast ------------- */
function showToast(msg) {
  const t = document.createElement('div');
  t.className = 'rb-toast';
  t.textContent = msg;
  document.body.appendChild(t);
  requestAnimationFrame(() => t.classList.add('show'));
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 250); }, 1800);
}

/* ------------ State & API helpers ------------- */
const RB_STATE = {
  userLoggedIn: document.body?.dataset?.user === '1',
  wishlist: new Set(),
  cartCount: 0
};

async function apiRequest(url, options = {}) {
  const opts = {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json' },
    ...options
  };
  const method = (opts.method || 'GET').toString().toUpperCase();
  if (opts.body && typeof opts.body !== 'string') {
    const payload = { ...opts.body };
    if (method !== 'GET') {
      const token = rbGetCookie(RB_CONFIG.csrfCookie);
      if (token && !payload.csrf_token) {
        payload.csrf_token = token;
      }
    }
    opts.body = JSON.stringify(payload);
    opts.headers = { ...opts.headers, 'Content-Type': 'application/json' };
  }
  if (method !== 'GET') {
    const token = rbGetCookie(RB_CONFIG.csrfCookie);
    if (token) {
      opts.headers = { ...opts.headers, 'X-CSRF-Token': token };
    }
  }
  const res = await fetch(url, opts);
  const text = await res.text();
  let data = {};
  if (text) {
    try {
      data = JSON.parse(text);
    } catch (_) {
      data = {};
    }
  }
  if (!res.ok) {
    const err = new Error(data.error || 'Request failed');
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}

function updateCartCountDisplay(count) {
  RB_STATE.cartCount = typeof count === 'number' ? count : RB_STATE.cartCount;
  const el = document.getElementById('cart-count');
  if (el) el.textContent = RB_STATE.cartCount;
}

async function refreshCartCount() {
  try {
    const data = await apiRequest('api/cart.php');
    updateCartCountDisplay(data.count || 0);
  } catch (err) {
    updateCartCountDisplay(0);
  }
}

async function cartAdd(productId, quantity = 1) {
  const data = await apiRequest('api/cart.php', {
    method: 'POST',
    body: { action: 'add', product_id: productId, quantity }
  });
  updateCartCountDisplay(data.count || 0);
  document.dispatchEvent(new CustomEvent('cart:updated'));
  return data;
}

async function cartUpdate(productId, quantity) {
  const data = await apiRequest('api/cart.php', {
    method: 'POST',
    body: { action: 'update', product_id: productId, quantity }
  });
  updateCartCountDisplay(data.count || 0);
  document.dispatchEvent(new CustomEvent('cart:updated'));
  return data;
}

async function cartRemove(productId) {
  const data = await apiRequest('api/cart.php', {
    method: 'POST',
    body: { action: 'remove', product_id: productId }
  });
  updateCartCountDisplay(data.count || 0);
  document.dispatchEvent(new CustomEvent('cart:updated'));
  return data;
}

async function cartClear() {
  const data = await apiRequest('api/cart.php', {
    method: 'POST',
    body: { action: 'clear' }
  });
  updateCartCountDisplay(0);
  document.dispatchEvent(new CustomEvent('cart:updated'));
  return data;
}

function updateWishlistButtons() {
  const applyState = (btn) => {
    const id = Number(btn?.dataset?.id);
    if (!id) return;
    const wished = RB_STATE.wishlist.has(id);
    btn.classList.toggle('active', wished);
    btn.setAttribute('aria-pressed', wished);
    btn.textContent = wished ? 'Wishlisted' : 'Add to Wishlist';
  };
  document.querySelectorAll('.btn-wish, #pvWish, #buyWish').forEach(applyState);
}

async function refreshWishlist() {
  try {
    const data = await apiRequest('api/wishlist.php');
    RB_STATE.userLoggedIn = true;
    RB_STATE.wishlist = new Set((data.product_ids || []).map(Number));
    document.body.dataset.user = '1';
  } catch (err) {
    if (err.status === 401) {
      RB_STATE.userLoggedIn = false;
      RB_STATE.wishlist.clear();
      document.body.dataset.user = '0';
    }
  } finally {
    updateWishlistButtons();
  }
}

async function wishlistToggle(productId) {
  if (!productId) return;
  if (!RB_STATE.userLoggedIn) {
    showToast('Sign in to use the wishlist.');
    return;
  }
  const wished = RB_STATE.wishlist.has(productId);
  try {
    const action = wished ? 'remove' : 'add';
    const data = await apiRequest('api/wishlist.php', {
      method: 'POST',
      body: { action, product_id: productId }
    });
    if (data.status === 'added') {
      RB_STATE.wishlist.add(productId);
    } else if (data.status === 'removed') {
      RB_STATE.wishlist.delete(productId);
    }
    document.dispatchEvent(new CustomEvent('wishlist:updated'));
    updateWishlistButtons();
  } catch (err) {
    if (err.status === 401) {
      RB_STATE.userLoggedIn = false;
      document.body.dataset.user = '0';
      showToast('Sign in to use the wishlist.');
    } else {
      showToast('Could not update wishlist right now.');
    }
  }
}

const isProductWished = (id) => RB_STATE.wishlist.has(Number(id));

/* Preferred image path inside media/products/{category}/{slug}/image1.jpg */
function rbImg(p) {
  if (p && p.slug && p.tag) {
    return `media/products/${p.tag}/${p.slug}/image1.jpg`;
  }
  return p?.img || 'media/logo.png';
}
function rbGalleryUrls(product, max = 10) {
  const urls = [];
  if (product && product.slug && product.tag) {
    for (let i = 1; i <= max; i++) {
      urls.push(`media/products/${product.tag}/${product.slug}/image${i}.jpg`);
    }
  }
  return urls;
}
/* =============================================== */
document.addEventListener('DOMContentLoaded', () => {
  refreshCartCount();
  refreshWishlist();

  /* Global header search -> shop.php?search= */
  document.querySelectorAll('form.rb-search').forEach(f => {
    f.addEventListener('submit', (e) => { e.preventDefault(); const q = (f.querySelector('input[type="search"], #rbSearch')?.value || '').trim(); location.href = q ? `shop.php?search=${encodeURIComponent(q)}` : 'shop.php'; });
  });

  /* Mobile navigation toggle */
  (function () {
    const header = document.querySelector('.rb-header');
    const burger = document.getElementById('burger');
    const overlay = document.getElementById('rbOverlay');
    const nav = document.getElementById('rbNav');
    if (!header || !burger) return;

    const closeMenu = () => {
      header.classList.remove('menu-open');
      document.body.classList.remove('menu-open');
      if (overlay) overlay.classList.remove('show');
      burger.setAttribute('aria-expanded', 'false');
    };

    const openMenu = () => {
      header.classList.add('menu-open');
      document.body.classList.add('menu-open');
      if (overlay) overlay.classList.add('show');
      burger.setAttribute('aria-expanded', 'true');
    };

    burger.setAttribute('aria-controls', 'rbNav');
    burger.setAttribute('aria-expanded', 'false');

    burger.addEventListener('click', () => {
      if (header.classList.contains('menu-open')) {
        closeMenu();
      } else {
        openMenu();
      }
    });

    overlay?.addEventListener('click', closeMenu);

    nav?.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 900) closeMenu();
      });
    });

    window.addEventListener('keydown', (evt) => {
      if (evt.key === 'Escape') closeMenu();
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 900) closeMenu();
    });
  })();

  /* Global Add-to-Cart + Wishlist Toggle */
  document.body.addEventListener('click', (e) => {
    const addBtn = e.target.closest('.btn-add');
    if (addBtn) {
      e.preventDefault();
      const productId = Number(addBtn.dataset.id || addBtn.dataset.productId);
      if (!productId) return;
      const qty = Number(addBtn.dataset.qty || addBtn.dataset.quantity || 1) || 1;
      const name = addBtn.dataset.name || 'Item';
      cartAdd(productId, qty)
        .then(() => showToast(`${name} added to cart!`))
        .catch(() => showToast('Unable to add to cart.'));
      return;
    }
    const wishBtn = e.target.closest('.btn-wish, #pvWish, #buyWish, #qvWish');
    if (wishBtn) {
      e.preventDefault();
      const productId = Number(wishBtn.dataset.id || wishBtn.getAttribute('data-id'));
      if (!productId) return;
      wishlistToggle(productId);
    }
  });

  /* Hero slider */
  (function () {
    const slider = document.querySelector('.hero-slider');
    if (slider) {
      const slides = slider.querySelectorAll('.slide');
      const dotsWrap = slider.querySelector('.slider-dots');
      if (!slides.length || !dotsWrap) {
        return;
      }
      dotsWrap.innerHTML = '';
      slides.forEach((_, i) => {
        const dot = document.createElement('span');
        dot.dataset.index = String(i);
        dot.setAttribute('role', 'button');
        dot.setAttribute('tabindex', '0');
        dot.setAttribute('aria-label', 'Slide ' + (i + 1));
        dotsWrap.appendChild(dot);
      });
      const dots = dotsWrap.querySelectorAll('span');
      let current = 0;
      const activateSlide = (index) => {
        slides.forEach((s) => s.classList.remove('active'));
        dots.forEach((d) => d.classList.remove('active'));
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        current = index;
      };
      activateSlide(0);
      let timer = window.setInterval(() => activateSlide((current + 1) % slides.length), 5000);
      const restart = () => {
        window.clearInterval(timer);
        timer = window.setInterval(() => activateSlide((current + 1) % slides.length), 5000);
      };
      dots.forEach((dot) => {
        const goTo = () => {
          const index = Number(dot.dataset.index || 0);
          activateSlide(Number.isNaN(index) ? 0 : index);
          restart();
        };
        dot.addEventListener('click', goTo);
        dot.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            goTo();
          }
        });
      });
      return;
    }

    const hero = document.querySelector('.rb-hero[data-hero-images]');
    if (!hero) {
      return;
    }

    let images = [];
    try {
      const raw = hero.dataset.heroImages || '[]';
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        images = parsed.filter((src) => typeof src === 'string' && src.trim() !== '');
      }
    } catch (err) {
      images = [];
    }

    if (images.length <= 1) {
      return;
    }

    const heroBg = hero.querySelector('.rb-hero-bg');
    const dotsWrap = hero.querySelector('.slider-dots') || (() => {
      const el = document.createElement('div');
      el.className = 'slider-dots';
      hero.appendChild(el);
      return el;
    })();

    dotsWrap.innerHTML = '';
    images.forEach((_, idx) => {
      const dot = document.createElement('span');
      dot.dataset.index = String(idx);
      dot.setAttribute('role', 'button');
      dot.setAttribute('tabindex', '0');
      dot.setAttribute('aria-label', 'Slide ' + (idx + 1));
      dotsWrap.appendChild(dot);
    });

    const dots = Array.from(dotsWrap.querySelectorAll('span'));
    if (!dots.length) {
      return;
    }

    hero.setAttribute('data-hero-count', String(images.length));

    const applyImage = (value) => {
      const source = String(value || '').trim();
      if (!source) {
        return;
      }
      const escaped = source.replace(/"/g, '\\"');
      hero.style.setProperty('--hero', 'url("' + escaped + '")');
      if (heroBg) {
        heroBg.style.backgroundImage = 'url("' + escaped + '")';
      }
    };

    let current = 0;
    const activateHeroSlide = (index) => {
      dots.forEach((dotEl) => dotEl.classList.remove('active'));
      dots[index].classList.add('active');
      applyImage(images[index]);
      hero.setAttribute('data-current-slide', String(index + 1));
      current = index;
    };

    activateHeroSlide(0);

    let timer = window.setInterval(() => activateHeroSlide((current + 1) % images.length), 5000);
    const restart = () => {
      window.clearInterval(timer);
      timer = window.setInterval(() => activateHeroSlide((current + 1) % images.length), 5000);
    };

    dots.forEach((dot) => {
      const goTo = () => {
        const idx = Number(dot.dataset.index || 0);
        activateHeroSlide(Number.isNaN(idx) ? 0 : idx);
        restart();
      };
      dot.addEventListener('click', goTo);
      dot.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          goTo();
        }
      });
    });
  })();
  /* Home â€“ Featured + New Arrivals */
  (function () {
    function cardHTML(p) {
      const wished = isProductWished(p.id); return `
      <a class="card-link" href="product.php?id=${encodeURIComponent(p.id)}">
        <div class="product-thumb"><span class="badge-cat">${p.tag}</span><img src="${rbImg(p)}" data-fallback="${p.img}" onerror="this.onerror=null;this.src=this.dataset.fallback" alt="${p.name}"></div>
        <div class="product-info"><h4>${p.name}</h4><p class="price">$${p.price.toFixed(2)}</p></div>
      </a>
      <button class="btn btn-add" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}">Add to Cart</button>
      <button class="btn secondary btn-wish ${wished ? 'active' : ''}" data-id="${p.id}" aria-pressed="${wished}">${wished ? 'Wishlisted' : 'Add to Wishlist'}</button>`;
    }
    const shouldClientRender = (el) => el && el.dataset.render !== 'server';
    const featured = document.getElementById('featuredProducts');
    const newArrivals = document.getElementById('newArrivals');
    if (!shouldClientRender(featured) && !shouldClientRender(newArrivals)) {
      return;
    }
    const render = () => {
      if (shouldClientRender(featured)) {
        featured.innerHTML = '';
        RB_PRODUCTS.slice(0, 8).forEach(p => {
          const d = document.createElement('div'); d.className = 'product-card'; d.innerHTML = cardHTML(p); featured.appendChild(d);
        });
      }
      if (shouldClientRender(newArrivals)) {
        newArrivals.innerHTML = '';
        [...RB_PRODUCTS].sort(() => Math.random() - .5).slice(0, 8).forEach(p => {
          const d = document.createElement('div'); d.className = 'product-card'; d.innerHTML = cardHTML(p); newArrivals.appendChild(d);
        });
      }
    };
    if (RB_PRODUCTS.length) {
      render();
    } else {
      rbWithProducts(render);
    }
  })();

  /* Contact form */
  (function () { const f = document.getElementById('contactForm'); if (!f) return; f.addEventListener('submit', (e) => { e.preventDefault(); showToast('Your message has been submitted to admin.'); f.reset(); }); })();

  /* Cart page */
  (function () {
    const table = document.getElementById('cartBody');
    const totalEl = document.getElementById('cartPageTotal');
    const clearBtn = document.getElementById('cartClear');
    if (!table || !totalEl) return;

    let currentIds = new Set();

    let renderSuggest = () => {};

    async function renderCart() {
      try {
        const data = await apiRequest('api/cart.php');
        const items = data.items || [];
        currentIds = new Set(items.map(it => Number(it.product_id)));
        updateCartCountDisplay(data.count || 0);
        table.innerHTML = '';
        if (!items.length) {
          table.innerHTML = '<tr><td colspan="3">Your cart is empty.</td></tr>';
          totalEl.textContent = '0.00';
          return;
        }
        let subtotal = 0;
        items.forEach((item) => {
          const line = Number(item.line_total || 0);
          subtotal += line;
          const nameHtml = item.product_id ? `<a href="product.php?id=${item.product_id}">${item.name}</a>` : item.name;
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${nameHtml}</td><td>$${Number(item.unit_price).toFixed(2)} &times; ${item.quantity}</td><td><button class="btn secondary mini-remove" data-id="${item.product_id}">Remove</button></td>`;
          table.appendChild(tr);
        });
        totalEl.textContent = subtotal.toFixed(2);
        table.querySelectorAll('.mini-remove').forEach(btn => {
          btn.addEventListener('click', async () => {
            const pid = Number(btn.dataset.id);
            await cartRemove(pid);
            showToast('Item removed from cart.');
            renderCart();
          });
        });
      } catch (_) {
        table.innerHTML = '<tr><td colspan="3">Unable to load cart right now.</td></tr>';
        totalEl.textContent = '0.00';
      }
      renderSuggest();
    }

    renderCart();
    document.addEventListener('cart:updated', renderCart);

    if (clearBtn) {
      clearBtn.addEventListener('click', async () => {
        await cartClear();
        showToast('Cart cleared');
        renderCart();
      });
    }

    const cartSug = document.getElementById('cartSuggest');
    if (cartSug) {
      const bindSuggestions = () => {
        renderSuggest = function () {
          const picks = RB_PRODUCTS.filter(p => !currentIds.has(p.id)).sort(() => Math.random() - .5).slice(0, 4);
          cartSug.innerHTML = '';
          picks.forEach(p => {
            const card = document.createElement('div');
            card.className = 'product-card';
            card.innerHTML = `<a class="card-link" href="product.php?id=${p.id}"><div class="product-thumb"><span class="badge-cat">${p.tag}</span><img src="${rbImg(p)}" data-fallback="${p.img}" onerror="this.onerror=null;this.src=this.dataset.fallback" alt="${p.name}"></div><div class="product-info"><h4>${p.name}</h4><p class="price">$${p.price.toFixed(2)}</p></div></a><button class="btn btn-add" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}">Add to Cart</button>`;
            cartSug.appendChild(card);
          });
        };
        renderSuggest();
        document.addEventListener('cart:updated', renderSuggest);
      };
      if (RB_PRODUCTS.length) {
        bindSuggestions();
      } else {
        rbWithProducts(bindSuggestions);
      }
    }
  })();

  /* Checkout page */
  (function () {
    const form = document.getElementById('checkoutForm'); if (!form) return;
    const mode = form.dataset.mode || 'client';
    const serverMode = mode === 'server';
    const list = document.getElementById('checkoutList');
    const totalEl = document.getElementById('checkoutTotal');
    const note = document.getElementById('checkoutNote');
    const subEl = document.getElementById('sumSubtotal');
    const shipEl = document.getElementById('sumShipping');
    const discEl = document.getElementById('sumDiscount');
    const csBox = document.getElementById('coSuggest');

    let shippingMap = {};
    try {
      shippingMap = JSON.parse(form.dataset.shipping || '{}');
    } catch (_) {
      shippingMap = {};
    }
    const defaultShipping = Number(form.dataset.shippingDefault || 0);
    let baseSubtotal = Number(form.dataset.subtotal || 0);
  let discount = Number(form.dataset.discount || 0);
  let freeShip = form.dataset.freeShip === '1';
  const freeShipDefault = freeShip;
  let items = [];
  let promoMap = {};
  try {
    const parsedPromos = JSON.parse(form.dataset.promos || '{}');
    if (parsedPromos && typeof parsedPromos === 'object') promoMap = parsedPromos;
  } catch (_) {
    promoMap = {};
  }
  const normalizedPromos = {};
  Object.entries(promoMap || {}).forEach(([key, value]) => {
    if (!key) return;
    const code = String(key).toUpperCase();
    const data = value || {};
    normalizedPromos[code] = {
      code,
      type: (data.type || 'percent').toString().toLowerCase(),
      value: Number(data.value || 0),
      label: typeof data.label === 'string' ? data.label : '',
      min_subtotal: Number(data.min_subtotal || 0),
      free_shipping: Boolean(data.free_shipping),
    };
  });
  promoMap = normalizedPromos;
  if (!serverMode && Object.keys(promoMap).length === 0) {
    promoMap = {
      SAVE10: { code: 'SAVE10', type: 'percent', value: 10, label: 'SAVE10 applied - 10% off subtotal', min_subtotal: 0, free_shipping: false },
      FREESHIP: { code: 'FREESHIP', type: 'shipping', value: 0, label: 'FREESHIP applied - free shipping unlocked', min_subtotal: 0, free_shipping: true },
    };
  }

  if (serverMode) {
    try {
      const rawItems = form.dataset.items ? JSON.parse(form.dataset.items) : [];
      if (Array.isArray(rawItems)) items = rawItems;
      } catch (_) {
        items = [];
      }
    }

    function baseShippingCost() {
      const selected = document.querySelector('input[name="shipping"]:checked');
      const key = selected ? selected.value : Object.keys(shippingMap)[0];
      let amount = (shippingMap && Object.prototype.hasOwnProperty.call(shippingMap, key)) ? Number(shippingMap[key]) : defaultShipping;
      if (Number.isNaN(amount)) amount = defaultShipping;
      return Math.max(0, amount);
    }

    function currentShipping() {
      const amount = baseShippingCost();
      if (freeShip) return 0;
      return amount;
    }

    function computeSubtotal() {
      if (serverMode) return baseSubtotal;
      return items.reduce((acc, it) => acc + Number(it.line_total || 0), 0);
    }

    function renderItems() {
      if (serverMode || !list) return;
      list.innerHTML = '';
      if (!items.length) {
        list.innerHTML = '<li class="muted">Your cart is empty.</li>';
        return;
      }
      items.forEach((it) => {
        const nameHtml = it.product_id ? `<a href="product.php?id=${it.product_id}" class="link">${it.name}</a>` : it.name;
        const li = document.createElement('li');
        li.innerHTML = `<span class="name">${nameHtml}</span><span class="price">$${Number(it.line_total).toFixed(2)}</span>`;
        list.appendChild(li);
      });
    }

    function updateTotals() {
      const sub = computeSubtotal();
      const ship = currentShipping();
      const total = Math.max(0, sub + ship - discount);
      if (subEl) subEl.textContent = sub.toFixed(2);
      if (shipEl) {
        const freeLabel = shipEl.dataset.freeLabel || '0.00';
        shipEl.textContent = ship <= 0 ? freeLabel : ship.toFixed(2);
      }
      if (discEl) discEl.textContent = discount.toFixed(2);
      if (totalEl) totalEl.textContent = total.toFixed(2);
    }

    let renderSuggest = () => {};
    if (csBox && !serverMode) {
      const bindCheckoutSuggest = () => {
        renderSuggest = function () {
          const inCart = new Set(items.map(it => Number(it.product_id)));
          const picks = RB_PRODUCTS.filter(p => !inCart.has(p.id)).sort(() => Math.random() - .5).slice(0, 4);
          csBox.innerHTML = '';
          picks.forEach(p => {
            const d = document.createElement('div');
            d.className = 'product-card';
            d.innerHTML = `
              <a class="card-link" href="product.php?id=${p.id}">
                <div class="product-thumb"><span class="badge-cat">${p.tag}</span><img src="${rbImg(p)}" data-fallback="${p.img}" onerror="this.onerror=null;this.src=this.dataset.fallback" alt="${p.name}"></div>
                <div class="product-info"><h4>${p.name}</h4><p class="price">$${p.price.toFixed(2)}</p></div>
              </a>
              <button class="btn btn-add" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}">Add to Cart</button>`;
            csBox.appendChild(d);
          });
        };
        document.addEventListener('cart:updated', renderSuggest);
      };
      if (RB_PRODUCTS.length) {
        bindCheckoutSuggest();
      } else {
        rbWithProducts(bindCheckoutSuggest);
      }
    }

    async function loadCheckout() {
      if (serverMode) {
        updateTotals();
        if (typeof renderSuggest === 'function') renderSuggest();
        return;
      }
      try {
        const data = await apiRequest('api/cart.php');
        items = data.items || [];
        renderItems();
        baseSubtotal = computeSubtotal();
        discount = 0;
        updateTotals();
        renderSuggest();
      } catch (_) {
        if (list) list.innerHTML = '<li class="muted">Unable to load cart.</li>';
        if (totalEl) totalEl.textContent = '0.00';
      }
    }

    loadCheckout();
    if (!serverMode) document.addEventListener('cart:updated', loadCheckout);
    if (note) note.textContent = note.dataset.text || 'All payments are simulated for demo only (client-side).';

    document.querySelectorAll('input[name="shipping"]').forEach(r => r.addEventListener('change', updateTotals));

    // Brand logos
    (function () {
      const color = {
        visa: ['#172b85', '#fff'], mastercard: ['#fff', '#000'], amex: ['#016fd0', '#fff'], paypal: ['#003087', '#fff']
      };
      document.querySelectorAll('.pay-logos img[data-brand]').forEach(img => {
        const b = (img.dataset.brand || 'visa').toLowerCase(); const c = document.createElement('canvas'); c.width = 96; c.height = 40; const ctx = c.getContext('2d');
        const pair = color[b] || ['#eee', '#111']; ctx.fillStyle = pair[0]; ctx.fillRect(0, 0, c.width, c.height);
        if (b === 'mastercard') { ctx.fillStyle = '#eb001b'; ctx.beginPath(); ctx.arc(40, 20, 12, 0, Math.PI * 2); ctx.fill(); ctx.fillStyle = '#f79e1b'; ctx.beginPath(); ctx.arc(56, 20, 12, 0, Math.PI * 2); ctx.fill(); }
        else { ctx.fillStyle = pair[1]; ctx.font = '700 16px Segoe UI, Arial'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(b === 'amex' ? 'AMEX' : b.toUpperCase(), 48, 20); }
        img.src = c.toDataURL('image/png');
      });
    })();

    // Promo apply
    const promoIn = document.getElementById('promoInput'); const promoBtn = document.getElementById('promoApply'); const promoMsg = document.getElementById('promoMsg');
    const normalizePromoCode = (value) => (value || '').toString().replace(/\s+/g, '').toUpperCase();
    const formatCurrency = (amount) => `$${Number(amount || 0).toFixed(2)}`;
    const promoMessageFor = (promo, code) => {
      if (promo?.label) return promo.label;
      const type = (promo?.type || 'percent').toLowerCase();
      const value = Number(promo?.value || 0);
      if (type === 'amount') return `${code} applied - ${formatCurrency(Math.max(0, value))} off subtotal`;
      if (type === 'shipping' || promo?.free_shipping) return `${code} applied - free shipping unlocked`;
      const percentLabel = Number.isFinite(value) ? value : 0;
      return `${code} applied - ${percentLabel}% off subtotal`;
    };
    promoBtn && promoBtn.addEventListener('click', () => {
      if (!promoIn) return;
      const code = normalizePromoCode(promoIn.value);
      promoIn.value = code;
      const sub = computeSubtotal();
      if (!code) {
        discount = 0;
        freeShip = freeShipDefault;
        if (promoMsg) promoMsg.textContent = '';
        updateTotals();
        return;
      }
      const promo = promoMap[code];
      discount = 0;
      freeShip = freeShipDefault;
      if (!promo) {
        if (promoMsg) promoMsg.textContent = 'Code not valid';
        updateTotals();
        return;
      }
      const minSubtotal = Number(promo.min_subtotal || 0);
      if (Number.isFinite(minSubtotal) && minSubtotal > 0 && sub + 1e-6 < minSubtotal) {
        if (promoMsg) promoMsg.textContent = `Subtotal must be at least ${formatCurrency(minSubtotal)}`;
        updateTotals();
        return;
      }
      if (promo.free_shipping || promo.type === 'shipping') {
        freeShip = true;
      } else if (promo.type === 'amount') {
        const amount = Math.max(0, Number(promo.value || 0));
        discount = +Math.min(sub, amount).toFixed(2);
      } else {
        const percent = Math.max(0, Number(promo.value || 0));
        const amount = Math.min(sub, sub * (percent / 100));
        discount = +amount.toFixed(2);
      }
      if (promoMsg) promoMsg.textContent = promoMessageFor(promo, code);
      updateTotals();
    });

    form.addEventListener('submit', async (e) => {
      if (serverMode) return;
      e.preventDefault();
      if (!items.length) return alert('Your cart is empty.');
      const fn = document.getElementById('coFirst')?.value.trim();
      const ln = document.getElementById('coLast')?.value.trim();
      const addr = document.getElementById('coAddr')?.value.trim();
      if (!fn || !ln || !addr) return alert('Please fill all required fields.');
      await cartClear();
      document.dispatchEvent(new CustomEvent('cart:updated'));
      showToast('Order recorded (demo)');
      form.innerHTML = `<h2>Thanks, ${fn}!</h2><p>This static preview cannot contact Stripe. When running the PHP backend you'll be redirected to Stripe Checkout in test mode.</p>`;
      list.innerHTML = '';
      if (totalEl) totalEl.textContent = '0.00';
    });
  })();

  /* Shop page (filters + sort + quick view + suggestions + pagination) */
  (function () {
    const grid = document.getElementById('shopGrid'); if (!grid || grid.dataset.render === 'server') return;
    rbWithProducts(() => {
    const maxOut = document.getElementById('priceMax'); const priceRange = document.getElementById('priceRange'); const checks = document.querySelectorAll(".shop-filters input[type='checkbox']"); const sortSelect = document.getElementById('sortBy'); const resultCount = document.getElementById('resultCount'); const btnClear = document.getElementById('clearFilters'); const btnMore = document.getElementById('loadMore');
    const PAGE = 12; let all = [], rendered = 0;
    const qv = document.getElementById('qvOverlay'); const qvImg = document.getElementById('qvImg'); const qvName = document.getElementById('qvName'); const qvPrice = document.getElementById('qvPrice'); const qvDesc = document.getElementById('qvDesc'); const qvAdd = document.getElementById('qvAdd'); const qvClose = document.querySelector('.qv-close'); let overCard = false, overQV = false, openTimer = null, closeTimer = null, qvProduct = null;
    function openQv(p) { if (!qv) return; qvProduct = p; if (qvImg) { qvImg.src = rbImg(p); qvImg.onerror = () => { qvImg.onerror = null; qvImg.src = p.img; }; qvImg.alt = p.name; } if (qvName) qvName.textContent = p.name; if (qvPrice) qvPrice.textContent = `$${p.price.toFixed(2)}`; if (qvDesc) qvDesc.textContent = p.desc || ''; const qvView = document.getElementById('qvView'); if (qvView) qvView.href = `product.php?id=${encodeURIComponent(p.id)}`; const qvWish = document.getElementById('qvWish'); if (qvWish) { const w = isProductWished(p.id); qvWish.dataset.id = p.id; qvWish.classList.toggle('active', w); qvWish.setAttribute('aria-pressed', String(w)); qvWish.textContent = w ? 'Wishlisted' : 'Add to Wishlist'; } qv.classList.add('open'); }
    function scheduleClose() { clearTimeout(openTimer); clearTimeout(closeTimer); closeTimer = setTimeout(() => { if (!overCard && !overQV) qv.classList.remove('open'); }, 180); }
    if (qv) { qv.addEventListener('pointerenter', () => { overQV = true; clearTimeout(closeTimer); }); qv.addEventListener('pointerleave', () => { overQV = false; scheduleClose(); }); qv.addEventListener('click', e => { if (e.target === qv) { overQV = false; scheduleClose(); } }); }
    if (qvClose) qvClose.addEventListener('click', () => { overQV = false; scheduleClose(); }); if (qvAdd) qvAdd.addEventListener('click', () => { if (!qvProduct) return; cartAdd(qvProduct.id, 1).then(() => { qv.classList.remove('open'); showToast(`${qvProduct.name} added to cart!`); qvProduct = null; }); });

    function buildCard(p) {
      const wished = isProductWished(p.id); const d = document.createElement('div'); d.className = 'product-card'; d.innerHTML = `
      <a class="card-link" href="product.php?id=${encodeURIComponent(p.id)}">
        <div class="product-thumb"><span class="badge-cat">${p.tag}</span><img src="${rbImg(p)}" data-fallback="${p.img}" onerror="this.onerror=null;this.src=this.dataset.fallback" alt="${p.name}"></div>
      </a>
      <div class="product-info"><h4>${p.name}</h4><p class="price">$${p.price.toFixed(2)}</p>
        <button class="btn btn-add" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}">Add to Cart</button>
        <button class="btn secondary btn-wish ${wished ? 'active' : ''}" data-id="${p.id}" aria-pressed="${wished}">${wished ? 'Wishlisted' : 'Add to Wishlist'}</button>
      </div>`; d.addEventListener('pointerenter', () => { overCard = true; clearTimeout(closeTimer); clearTimeout(openTimer); if (window.matchMedia('(hover: hover)').matches) openTimer = setTimeout(() => openQv(p), 2000); }); d.addEventListener('pointerleave', () => { overCard = false; clearTimeout(openTimer); scheduleClose(); }); d.addEventListener('click', (e) => { if (e.target.closest('.btn-add') || e.target.closest('.btn-wish') || e.target.closest('a')) return; location.href = `product.php?id=${encodeURIComponent(p.id)}`; }); return d;
    }

    function updateCounts(base) { ['crystals', 'rudraksha', 'incense'].forEach(tag => { const el = document.getElementById(`cnt-${tag}`); if (el) { const n = base.filter(p => p.tag === tag).length; el.textContent = `(${n})`; } }); }

    function appendMore() { const slice = all.slice(rendered, rendered + PAGE); slice.forEach(p => grid.appendChild(buildCard(p))); rendered += slice.length; if (btnMore) btnMore.style.display = rendered < all.length ? 'inline-block' : 'none'; }

    function render() { const active = [...checks].filter(c => c.checked).map(c => c.dataset.filter); const max = Number(priceRange?.value || 9999); const q = (new URLSearchParams(location.search).get('search') || '').toLowerCase().trim(); const base = RB_PRODUCTS.filter(p => p.price <= max && (!q || p.name.toLowerCase().includes(q) || (p.desc || '').toLowerCase().includes(q))); updateCounts(base); let list = base.filter(p => (active.includes(p.tag) || !active.length)); switch (sortSelect?.value) { case 'price-asc': list.sort((a, b) => a.price - b.price); break; case 'price-desc': list.sort((a, b) => b.price - a.price); break; case 'name-asc': list.sort((a, b) => a.name.localeCompare(b.name)); break; case 'name-desc': list.sort((a, b) => b.name.localeCompare(a.name)); break; } if (maxOut) maxOut.textContent = max; all = list; rendered = 0; grid.innerHTML = list.length ? '' : "<p style='grid-column:1/-1;text-align:center;color:#666'>No products found.</p>"; appendMore(); if (resultCount) resultCount.textContent = `${list.length} result${list.length === 1 ? '' : 's'}`; renderSuggestions(active.length === 1 ? active[0] : null); }

    function renderSuggestions(excludeTag) {
      const box = document.getElementById('shopSuggest'); if (!box) return; const picks = RB_PRODUCTS.filter(p => !excludeTag || p.tag !== excludeTag).sort(() => Math.random() - .5).slice(0, 4); box.innerHTML = ''; picks.forEach(p => {
        const d = document.createElement('div'); d.className = 'product-card'; d.innerHTML = `
      <a class="card-link" href="product.php?id=${encodeURIComponent(p.id)}">
        <div class="product-thumb"><span class="badge-cat">${p.tag}</span><img src="${rbImg(p)}" data-fallback="${p.img}" onerror="this.onerror=null;this.src=this.dataset.fallback" alt="${p.name}"></div>
        <div class="product-info"><h4>${p.name}</h4><p class="price">$${p.price.toFixed(2)}</p></div>
      </a>
      <button class="btn btn-add" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}">Add to Cart</button>`; box.appendChild(d);
      });
    }

    // Params â†’ UI
    const params = new URLSearchParams(location.search); const cat = params.get('cat'); const maxp = params.get('max'); const sortp = params.get('sort'); if (cat) { const wanted = new Set(cat.split(',').map(s => s.trim().toLowerCase())); checks.forEach(c => c.checked = wanted.has((c.dataset.filter || '').toLowerCase())); } if (maxp && priceRange) { const mv = Number(maxp); if (!Number.isNaN(mv)) priceRange.value = mv; } if (sortp && sortSelect) { const ok = ['default', 'price-asc', 'price-desc', 'name-asc', 'name-desc']; if (ok.includes(sortp)) sortSelect.value = sortp; }

    // Bind
    checks.forEach(c => c.addEventListener('change', render)); priceRange && priceRange.addEventListener('input', render); sortSelect && sortSelect.addEventListener('change', render); btnClear && btnClear.addEventListener('click', () => { checks.forEach(c => c.checked = true); if (priceRange) priceRange.value = priceRange.max || priceRange.value; if (sortSelect) sortSelect.value = 'default'; render(); }); btnMore && btnMore.addEventListener('click', appendMore);

    render();
    });
  })();

  /* Product detail page */
  (function () {
    if (document.body?.dataset?.productSource === 'server') return;
    rbWithProducts(() => {
    const nameEl = document.getElementById('pvName'); const priceEl = document.getElementById('pvPrice'); const badgeEl = document.getElementById('pvBadge'); const descEl = document.getElementById('pvDesc'); const mainImg = document.getElementById('pvMain'); const thumbs = document.getElementById('pvThumbs'); const tags = document.getElementById('pvTags'); const specs = document.getElementById('pvSpecs'); const addBtn = document.getElementById('pvAdd'); const wishBtn = document.getElementById('pvWish'); const similar = document.getElementById('similarList'); if (!nameEl || !priceEl || !badgeEl || !mainImg) return;
    const id = Number(new URLSearchParams(location.search).get('id') || '1'); const product = RB_PRODUCTS.find(p => p.id === id) || RB_PRODUCTS[0]; document.title = `${product.name} | RudraBlessings`;
    nameEl.textContent = product.name; priceEl.textContent = `$${product.price.toFixed(2)}`; badgeEl.textContent = product.tag.charAt(0).toUpperCase() + product.tag.slice(1); mainImg.src = rbImg(product); mainImg.onerror = () => { mainImg.onerror = null; mainImg.src = product.img; }; if (descEl) descEl.textContent = product.desc || ''; const crumbLast = document.querySelector('.crumbs span:last-child'); if (crumbLast) crumbLast.textContent = product.name;
    // Gallery (load all available images)
    if (thumbs) {
      thumbs.innerHTML = '';
      const fall = product.img;
      const urls = rbGalleryUrls(product, 10);
      let first = null; urls.forEach(src => {
        const probe = new Image();
        probe.onload = () => {
          const i = document.createElement('img');
          i.src = src;
          i.alt = product.name;
          i.addEventListener('click', () => {
            mainImg.src = src;
            mainImg.onerror = () => {
              mainImg.onerror = null;
              mainImg.src = fall;
            };
          });
          thumbs.appendChild(i);
          if (!first) {
            first = src; mainImg.src = src;
          }
        }; probe.onerror = () => { }; probe.src = src;
      }); setTimeout(() => { if (!first) { const i = document.createElement('img'); i.src = fall; i.alt = product.name; i.addEventListener('click', () => { mainImg.src = fall; }); thumbs.appendChild(i); mainImg.src = fall; } }, 100);
    }
    if (tags) { tags.innerHTML = '';['handmade', 'natural', 'authentic'].forEach(t => { const s = document.createElement('span'); s.className = 'tag'; s.textContent = t; tags.appendChild(s); }); }
    if (specs) { specs.innerHTML = '';[['Category', badgeEl.textContent], ['SKU', `RB-${String(product.id).padStart(4, '0')}`], ['Ships from', 'Local warehouse'], ['Return', '30-day easy returns']].forEach(([k, v]) => { const tr = document.createElement('tr'); tr.innerHTML = `<td>${k}</td><td>${v}</td>`; specs.appendChild(tr); }); }
    if (addBtn) { addBtn.addEventListener('click', () => { cartAdd(product.id, 1).then(() => showToast(`${product.name} added to cart!`)); }); }
    if (wishBtn) { const initW = isProductWished(product.id); wishBtn.dataset.id = String(product.id); wishBtn.classList.toggle('active', initW); wishBtn.setAttribute('aria-pressed', String(initW)); wishBtn.textContent = initW ? 'Wishlisted' : 'Add to Wishlist'; }

    // Purchase card actions
    const buyPrice = document.getElementById('buyPrice'); if (buyPrice) buyPrice.textContent = `$${product.price.toFixed(2)}`;
    const qtySel = document.getElementById('buyQty');
    const buyAdd = document.getElementById('buyAdd');
    const buyNow = document.getElementById('buyNow');
    const buyWish = document.getElementById('buyWish');
    if (buyWish) { const w = isProductWished(product.id); buyWish.dataset.id = String(product.id); buyWish.textContent = w ? 'Wishlisted' : 'Add to Wishlist'; buyWish.classList.toggle('active', w); buyWish.setAttribute('aria-pressed', String(w)); }
    function addQtyToCart() { const qty = Math.max(1, parseInt(qtySel?.value || '1', 10) || 1); cartAdd(product.id, qty).then(() => showToast(`${product.name} added to cart!`)); }
    if (buyAdd) { buyAdd.addEventListener('click', () => { addQtyToCart(); }); }
    if (buyNow) { buyNow.addEventListener('click', () => { addQtyToCart(); location.href = 'checkout.php'; }); }
    if (similar) { const sibs = RB_PRODUCTS.filter(p => p.tag === product.tag && p.id !== product.id).slice(0, 8); const grid = document.getElementById('similarGrid') || similar; if (grid) grid.innerHTML = ''; sibs.forEach(p => { const a = document.createElement('a'); a.href = `product.php?id=${p.id}`; a.className = 'sim-card'; a.innerHTML = `<img src="${rbImg(p)}" data-fallback="${p.img}" onerror="this.onerror=null;this.src=this.dataset.fallback" alt="${p.name}"><h4>${p.name}</h4><div class='price'>$${p.price.toFixed(2)}</div>`; (grid || similar).appendChild(a); }); }
    // Bottom suggestions
    const sug = document.getElementById('pvSuggest'); if (sug) { const picks = RB_PRODUCTS.filter(p => p.tag === product.tag && p.id !== product.id).slice(0, 4); const list = picks.length ? picks : [...RB_PRODUCTS].filter(p => p.id !== product.id).sort(() => Math.random() - .5).slice(0, 4); sug.innerHTML = ''; list.forEach(p => { const card = document.createElement('div'); const wish = isProductWished(p.id); card.className = 'product-card'; card.innerHTML = `<a class="card-link" href="product.php?id=${p.id}"><div class="product-thumb"><span class="badge-cat">${p.tag}</span><img src="${rbImg(p)}" data-fallback="${p.img}" onerror="this.onerror=null;this.src=this.dataset.fallback" alt="${p.name}"></div><div class="product-info"><h4>${p.name}</h4><p class="price">$${p.price.toFixed(2)}</p></div></a><button class="btn btn-add" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}">Add to Cart</button><button class="btn secondary btn-wish ${wish ? 'active' : ''}" data-id="${p.id}" aria-pressed="${wish}">${wish ? 'Wishlisted' : 'Add to Wishlist'}</button>`; sug.appendChild(card); }); }
    });
  })();

  (function () {
    if (document.body?.dataset?.productSource !== 'server') return;
    const qtySel = document.getElementById('buyQty');
    const buyAdd = document.getElementById('buyAdd');
    const buyNow = document.getElementById('buyNow');
    const buyWish = document.getElementById('buyWish');
    const priceLabel = document.getElementById('buyPrice') || document.getElementById('pvPrice');
    const nameLabel = document.getElementById('pvName');

    const params = new URLSearchParams(location.search);
    const fromParams = Number(params.get('id') || '0');
    const productId = Number(buyAdd?.dataset?.id || buyWish?.dataset?.id || fromParams || 0);
    const productName = buyAdd?.dataset?.name || nameLabel?.textContent?.trim() || 'Product';
    const rawPrice = (buyAdd?.dataset?.price || priceLabel?.textContent || '0').toString();
    const productPrice = Number(rawPrice.replace(/[^0-9.]+/g, '')) || 0;

    if (productId <= 0) {
      return;
    }

    if (buyWish) {
      buyWish.dataset.id = String(productId);
    }

    const addQtyToCart = () => {
      const qty = Math.max(1, parseInt(qtySel?.value || '1', 10) || 1);
      cartAdd(productId, qty)
        .then(() => showToast(`${productName} added to cart!`))
        .catch(() => showToast('Unable to add to cart.'));
    };

    if (buyAdd) {
      buyAdd.addEventListener('click', addQtyToCart);
    }
    if (buyNow) {
      buyNow.addEventListener('click', () => {
        addQtyToCart();
        location.href = 'checkout.php';
      });
    }

    if (priceLabel && !priceLabel.dataset.productLocked && productPrice > 0) {
      priceLabel.dataset.productLocked = '1';
      priceLabel.textContent = `$${productPrice.toFixed(2)}`;
    }
  })();
});













