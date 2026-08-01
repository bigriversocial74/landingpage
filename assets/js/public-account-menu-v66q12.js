/* North Mountain Media build: 20260801-public-account-menu-v66Q12 */
(() => {
  'use strict';

  const menus = [...document.querySelectorAll('[data-public-account-menu]')];
  if (!menus.length) return;

  const closeOthers = (current = null) => {
    menus.forEach((menu) => {
      if (menu !== current) menu.removeAttribute('open');
    });
  };

  menus.forEach((menu) => {
    menu.addEventListener('toggle', () => {
      if (menu.open) closeOthers(menu);
    });
  });

  document.addEventListener('click', (event) => {
    const activeMenu = event.target.closest('[data-public-account-menu]');
    if (!activeMenu) closeOthers();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const openMenu = menus.find((menu) => menu.open);
    if (!openMenu) return;
    openMenu.removeAttribute('open');
    openMenu.querySelector('summary')?.focus();
  });
})();
