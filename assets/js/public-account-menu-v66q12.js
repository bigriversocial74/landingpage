/* North Mountain Media build: 20260801-public-account-menu-v66Q12 */
(() => {
  'use strict';

  const buildLoggedOutMenu = (clientUrl, adminUrl) => {
    const menu = document.createElement('details');
    menu.className = 'public-account-menu';
    menu.dataset.publicAccountMenu = '';

    const summary = document.createElement('summary');
    summary.setAttribute('aria-label', 'Open account menu');
    summary.innerHTML = `
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="8" r="4"></circle>
        <path d="M4.5 20c.8-4 3.3-6 7.5-6s6.7 2 7.5 6"></path>
      </svg>
      <span class="public-account-menu-label">
        <strong>Account</strong>
        <small>Sign in</small>
      </span>
      <b aria-hidden="true">⌄</b>`;

    const nav = document.createElement('nav');
    nav.setAttribute('aria-label', 'Account');

    const client = document.createElement('a');
    client.href = clientUrl;
    client.textContent = 'Client login';

    const admin = document.createElement('a');
    admin.href = adminUrl;
    admin.textContent = 'Administrator login';

    nav.append(client, admin);
    menu.append(summary, nav);
    return menu;
  };

  document.querySelectorAll('.workspace-header-actions, header').forEach((container) => {
    if (container.querySelector('[data-public-account-menu]')) return;

    const anchors = [...container.querySelectorAll('a[href*="portal/login.php?role="]')];
    const client = anchors.find((link) => link.href.includes('role=client'));
    const admin = anchors.find((link) => link.href.includes('role=admin'));
    if (!client || !admin) return;

    const menu = buildLoggedOutMenu(client.href, admin.href);
    client.replaceWith(menu);
    admin.remove();
  });

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
