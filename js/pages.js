(() => {
  const ready = (callback) => {
    if (document.readyState !== 'loading') {
      callback();
    } else {
      document.addEventListener('DOMContentLoaded', callback);
    }
  };

  const initProfileTabs = () => {
    const tabNav = document.querySelector('.pf-tabs');
    const tabPanels = document.querySelectorAll('.pf-main [id^="tab-"]');
    if (!tabNav || !tabPanels.length) {
      return;
    }

    const buttons = tabNav.querySelectorAll('button[data-tab]');
    if (!buttons.length) {
      return;
    }

    const switchTab = (targetButton) => {
      const tabName = targetButton?.dataset?.tab;
      if (!tabName) {
        return;
      }

      buttons.forEach((btn) => {
        const isActive = btn === targetButton;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      tabPanels.forEach((panel) => {
        const matches = panel.id === `tab-${tabName}`;
        panel.hidden = !matches;
        panel.setAttribute('aria-hidden', matches ? 'false' : 'true');
      });
    };

    buttons.forEach((button) => {
      button.type = 'button';
      button.addEventListener('click', () => switchTab(button));
    });

    const activeFromHash = Array.from(buttons).find((btn) => `#tab-${btn.dataset.tab}` === window.location.hash);
    switchTab(activeFromHash || buttons[0]);
  };

  ready(() => {
    initProfileTabs();
  });
})();
