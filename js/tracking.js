



(() => {
  const ready = (callback) => {
    if (document.readyState !== 'loading') {
      callback();
    } else {
      document.addEventListener('DOMContentLoaded', callback);
    }
  };

  const animateStatusFill = () => {
    const bar = document.getElementById('statusFill');
    if (!bar) {
      return;
    }

    const target = parseFloat(bar.dataset.target || '0');
    const current = parseFloat(bar.style.width) || 0;
    if (target <= 0 || current === target) {
      bar.style.width = `${target}%`;
      return;
    }

    const duration = 600;
    const start = performance.now();
    const initial = 0;

    const tick = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = progress < 0.5 ? 2 * progress * progress : -1 + (4 - 2 * progress) * progress;
      const width = initial + (target - initial) * eased;
      bar.style.width = `${width.toFixed(2)}%`;
      if (progress < 1) {
        requestAnimationFrame(tick);
      }
    };

    bar.style.width = '0%';
    requestAnimationFrame(tick);
  };

  const initTrackingForm = () => {
    const form = document.getElementById('trackForm');
    if (!form) {
      return;
    }

    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = true;
        button.dataset.originalText = button.textContent || '';
        button.textContent = 'Searching…';
      }
    });
  };

  ready(() => {
    animateStatusFill();
    initTrackingForm();
  });
})();
