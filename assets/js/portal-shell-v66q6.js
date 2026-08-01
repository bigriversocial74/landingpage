/* North Mountain Media build: 20260731-portal-shell-v66Q6 */
(() => {
  'use strict';

  if (window.__nmmPortalShellV66Q6) return;
  window.__nmmPortalShellV66Q6 = true;

  const runtimeScript = document.currentScript
    || document.querySelector('script[src*="portal-shell-v66q6.js"]');
  const appRoot = runtimeScript?.src
    ? new URL('../../', runtimeScript.src)
    : new URL('../', document.baseURI);
  const appUrl = (path) => new URL(String(path).replace(/^\/+/, ''), appRoot).href;
  const text = (element) => String(element?.textContent || '').trim();

  const installStyles = () => {
    if (document.querySelector('[data-portal-shell-v66q6-style]')) return;
    const style = document.createElement('style');
    style.dataset.portalShellV66q6Style = '';
    style.textContent = `
      .portal-user-menu{position:relative;flex:0 0 auto}
      .portal-user-menu .portal-user-info{appearance:none;width:auto;min-width:220px;border:1px solid var(--line,#dfe5eb);cursor:pointer;text-align:left}
      .portal-user-menu-trigger{display:flex;align-items:center;gap:10px}
      .portal-user-menu-trigger .portal-user-copy{flex:1}
      .portal-user-menu-chevron{width:16px;height:16px;flex:0 0 16px;color:#6b7785;transition:transform .18s ease}
      .portal-user-menu.is-open .portal-user-menu-chevron{transform:rotate(180deg)}
      .portal-user-menu-panel{position:absolute;top:calc(100% + 10px);right:0;z-index:120;width:240px;padding:8px;border:1px solid #dfe5eb;border-radius:15px;background:#fff;box-shadow:0 20px 48px rgba(18,31,45,.16)}
      .portal-user-menu-panel[hidden]{display:none}
      .portal-user-menu-panel header{display:grid;gap:2px;padding:10px 11px 12px;border-bottom:1px solid #edf1f4}
      .portal-user-menu-panel header span{color:#74808d;font-size:.62rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
      .portal-user-menu-panel header strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.8rem}
      .portal-user-menu-panel nav{display:grid;padding-top:7px}
      .portal-user-menu-panel a{display:flex;align-items:center;min-height:38px;padding:8px 10px;border-radius:9px;color:#263445;text-decoration:none;font-size:.75rem;font-weight:750}
      .portal-user-menu-panel a:hover,.portal-user-menu-panel a:focus-visible{background:#f1f5f7;outline:none}
      .portal-user-menu-panel a:last-child{margin-top:5px;border-top:1px solid #edf1f4;border-radius:0 0 9px 9px;color:#8b313b}
      .publishing-center-menu a[data-publishing-option]{width:100%;display:block;text-align:left;border:0;background:transparent;border-radius:13px;padding:11px 12px;margin:2px 0;color:#172231;text-decoration:none}
      .publishing-center-menu a[data-publishing-option]:hover,.publishing-center-menu a[data-publishing-option].is-active{background:#fff;box-shadow:0 8px 22px rgba(18,32,48,.08)}
      .publishing-center-menu a[data-publishing-option] strong,.publishing-center-menu a[data-publishing-option] small{display:block}
      .publishing-center-menu a[data-publishing-option] small{margin-top:3px;color:#6d7988;line-height:1.35}
      .social-feed-stories header a[data-publishing-open]{display:grid;place-items:center;width:38px;height:38px;padding:0;border:1px solid #dce4e9;border-radius:999px;color:#172231;background:#fff;font-size:22px;font-weight:800;text-decoration:none}
      .social-feed-record-actions a[data-publishing-open]{display:inline-flex;align-items:center;border:0;border-radius:999px;padding:8px 12px;color:#172231;background:#eef3f5;font-weight:800;text-decoration:none}
      .social-feed-empty a[data-publishing-open]{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:999px;padding:11px 16px;color:#fff;background:#0f766e;font-weight:800;text-decoration:none}
      .social-feed-drafts a[data-publishing-open]{display:inline-flex;align-items:center;border:1px solid #dfe6ec;border-radius:999px;padding:8px 11px;color:#172231;background:#f7f9fa;text-decoration:none}
      @media(max-width:620px){
        .portal-user-menu .portal-user-info{min-width:0;padding:4px}
        .portal-user-menu-trigger .portal-user-copy,.portal-user-menu-chevron{display:none}
        .portal-user-menu-panel{position:fixed;top:70px;right:12px;left:12px;width:auto}
      }
    `;
    document.head.appendChild(style);
  };

  const findLink = (scope, label) => Array.from(
    scope?.querySelectorAll('a') || []
  ).find((link) => text(link) === label) || null;

  const organizeNavigation = () => {
    const nav = document.querySelector('[data-admin-navigation]');
    if (!nav) return;

    const groups = new Map();
    nav.querySelectorAll('[data-nav-group]').forEach((group) => {
      const label = text(group.querySelector('[data-nav-group-toggle] span:first-child'));
      const links = group.querySelector('[data-nav-group-links]');
      if (label && links) groups.set(label, links);
    });

    const move = (label, groupName) => {
      const link = findLink(nav, label);
      const destination = groups.get(groupName);
      if (link && destination) destination.appendChild(link);
    };

    move('Unified Inbox', 'Relationships');
    move('Call Center', 'Relationships');
    move('Visitor Intelligence', 'System');
    move('Site Analytics', 'System');
    findLink(nav, 'Notifications')?.remove();

    const clientsEnabled = Boolean(nav.querySelector('a[href*="view=clients"]'));
    if (!clientsEnabled) findLink(nav, 'Communications')?.remove();
  };

  const cleanStaticShell = () => {
    document.querySelectorAll(
      '.portal-header-user > a.portal-top-action[href*="view=call-center"]'
    ).forEach((link) => link.remove());
    document.querySelectorAll(
      '.portal-sidebar-foot a[href*="logout.php"]'
    ).forEach((link) => link.remove());
    if (document.body.dataset.portalActive === 'social-posts') {
      document.querySelectorAll(
        '.social-feed-toolbar, .social-feed-guidance'
      ).forEach((element) => element.remove());
    }
  };

  const installUserMenu = () => {
    const current = document.querySelector('.portal-user-info');
    if (!current || current.closest('[data-portal-user-menu]')) return;

    const role = document.body.dataset.portalRole === 'admin' ? 'admin' : 'client';
    const roleLabel = role === 'admin' ? 'Administrator' : 'Client';
    const name = text(current.querySelector('strong')) || 'Account';
    const avatar = current.querySelector('.portal-user-avatar')?.cloneNode(true);

    const wrapper = document.createElement('div');
    wrapper.className = 'portal-user-menu';
    wrapper.dataset.portalUserMenu = '';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'portal-user-info portal-user-menu-trigger';
    trigger.dataset.portalUserMenuTrigger = '';
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-haspopup', 'menu');
    trigger.setAttribute('aria-label', 'Open user menu');
    if (avatar) trigger.appendChild(avatar);

    const copy = document.createElement('span');
    copy.className = 'portal-user-copy';
    const strong = document.createElement('strong');
    strong.textContent = name;
    const small = document.createElement('small');
    small.textContent = roleLabel;
    copy.append(strong, small);
    trigger.appendChild(copy);

    const chevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    chevron.setAttribute('viewBox', '0 0 20 20');
    chevron.setAttribute('aria-hidden', 'true');
    chevron.classList.add('portal-user-menu-chevron');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M5 7.5 10 12l5-4.5');
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', 'currentColor');
    path.setAttribute('stroke-width', '1.8');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    chevron.appendChild(path);
    trigger.appendChild(chevron);

    const panel = document.createElement('section');
    panel.className = 'portal-user-menu-panel';
    panel.dataset.portalUserMenuPanel = '';
    panel.setAttribute('role', 'menu');
    panel.hidden = true;

    const panelHeader = document.createElement('header');
    const eyebrow = document.createElement('span');
    eyebrow.textContent = 'Signed in';
    const panelName = document.createElement('strong');
    panelName.textContent = name;
    panelHeader.append(eyebrow, panelName);

    const menu = document.createElement('nav');
    const links = role === 'admin'
      ? [
          ['Dashboard', appUrl('portal/admin.php?view=dashboard')],
          ['Settings', appUrl('portal/admin.php?view=settings')],
          ['Account', appUrl('portal/admin.php?view=account')],
          ['Sign out', appUrl('portal/logout.php')],
        ]
      : [
          ['Dashboard', appUrl('portal/client.php')],
          ['Settings', appUrl('portal/client.php?view=account')],
          ['Sign out', appUrl('portal/logout.php')],
        ];

    links.forEach(([label, href]) => {
      const link = document.createElement('a');
      link.href = href;
      link.textContent = label;
      link.setAttribute('role', 'menuitem');
      menu.appendChild(link);
    });
    panel.append(panelHeader, menu);
    wrapper.append(trigger, panel);
    current.replaceWith(wrapper);

    const close = () => {
      panel.hidden = true;
      wrapper.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
    };
    trigger.addEventListener('click', (event) => {
      event.stopPropagation();
      const opening = panel.hidden;
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
    organizeNavigation();
    cleanStaticShell();
    installUserMenu();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
