/* North Mountain Media build: 20260731-public-user-menu-v66Q6 */
(() => {
  'use strict';

  if (window.__nmmPublicUserMenuV66Q6) return;
  window.__nmmPublicUserMenuV66Q6 = true;

  const appUrl = (path) => new URL(path, document.baseURI).href;

  const installStyles = () => {
    if (document.querySelector('[data-public-user-menu-v66q6-style]')) return;
    const style = document.createElement('style');
    style.dataset.publicUserMenuV66q6Style = '';
    style.textContent = `
      .public-user-menu{position:relative;display:inline-flex;align-items:center;z-index:40}
      .public-user-menu-trigger{display:inline-flex;align-items:center;gap:8px;min-height:42px;padding:9px 14px;border:1px solid rgba(112,126,140,.25);border-radius:999px;color:inherit;background:rgba(255,255,255,.92);font:inherit;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 6px 18px rgba(18,32,48,.06)}
      .public-user-menu-trigger svg{width:18px;height:18px}
      .public-user-menu-trigger .public-user-chevron{width:14px;height:14px;transition:transform .18s ease}
      .public-user-menu.is-open .public-user-chevron{transform:rotate(180deg)}
      .public-user-menu-panel{position:absolute;top:calc(100% + 10px);right:0;display:grid;min-width:210px;padding:8px;border:1px solid #dfe6ec;border-radius:15px;background:#fff;box-shadow:0 20px 50px rgba(18,32,48,.18)}
      .public-user-menu-panel[hidden]{display:none}
      .public-user-menu-panel span{padding:8px 10px 10px;color:#73808d;font-size:11px;font-weight:800;letter-spacing:.09em;text-transform:uppercase}
      .public-user-menu-panel a{display:flex;align-items:center;min-height:40px;padding:9px 11px;border-radius:9px;color:#1d2a38;text-decoration:none;font-size:13px;font-weight:760}
      .public-user-menu-panel a:hover,.public-user-menu-panel a:focus-visible{background:#f1f5f7;outline:none}
      .visual-site-header .public-user-menu{margin-left:8px}
      .visual-site-header.header-dark .public-user-menu-trigger,.visual-site-header.header-transparent .public-user-menu-trigger{border-color:rgba(255,255,255,.28);color:#fff;background:rgba(13,23,34,.36);backdrop-filter:blur(12px)}
      @media(max-width:800px){
        .public-user-menu-panel{position:fixed;top:70px;right:12px;left:12px;min-width:0}
        .landing-navigation .public-user-menu{width:100%;margin-top:8px}
        .landing-navigation .public-user-menu-trigger{width:100%;justify-content:center}
        .visual-site-header .public-user-menu{margin-left:auto}
        .visual-site-header .public-user-menu-trigger span{display:none}
      }
    `;
    document.head.appendChild(style);
  };

  const buildMenu = (host, existingLogin = null) => {
    if (!host || host.querySelector('[data-public-user-menu]')) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'public-user-menu';
    wrapper.dataset.publicUserMenu = '';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'public-user-menu-trigger';
    trigger.dataset.publicUserMenuTrigger = '';
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-haspopup', 'menu');
    trigger.setAttribute('aria-label', 'Open login menu');
    trigger.innerHTML = `
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.8"></circle><path d="M4.5 20c.8-4 3.3-6 7.5-6s6.7 2 7.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
      <span>Account</span>
      <svg class="public-user-chevron" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7.5 10 12l5-4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
    `;

    const panel = document.createElement('div');
    panel.className = 'public-user-menu-panel';
    panel.dataset.publicUserMenuPanel = '';
    panel.setAttribute('role', 'menu');
    panel.hidden = true;

    const heading = document.createElement('span');
    heading.textContent = 'Sign in';
    panel.appendChild(heading);

    const client = document.createElement('a');
    client.href = existingLogin?.href || appUrl('portal/login.php?role=client');
    client.textContent = 'Client login';
    client.setAttribute('role', 'menuitem');

    const admin = document.createElement('a');
    admin.href = appUrl('portal/login.php?role=admin');
    admin.textContent = 'Administrator login';
    admin.setAttribute('role', 'menuitem');
    panel.append(client, admin);

    wrapper.append(trigger, panel);
    if (existingLogin) existingLogin.replaceWith(wrapper);
    else host.appendChild(wrapper);

    const close = () => {
      panel.hidden = true;
      wrapper.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
    };

    trigger.addEventListener('click', (event) => {
      event.stopPropagation();
      const opening = panel.hidden;
      document.querySelectorAll('[data-public-user-menu-panel]').forEach((other) => {
        if (other !== panel) other.hidden = true;
      });
      panel.hidden = !opening;
      wrapper.classList.toggle('is-open', opening);
      trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
    panel.addEventListener('click', (event) => event.stopPropagation());
    document.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  };

  const initialize = () => {
    installStyles();

    const landingNav = document.querySelector('[data-landing-navigation]');
    if (landingNav) {
      buildMenu(landingNav, landingNav.querySelector('.landing-login'));
    }

    const visualHeader = document.querySelector('.visual-site-header');
    if (visualHeader) buildMenu(visualHeader);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
