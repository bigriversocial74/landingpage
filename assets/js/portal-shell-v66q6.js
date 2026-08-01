/* North Mountain Media build: 20260731-portal-shell-v66Q6 */
(() => {
  'use strict';

  if (window.__nmmPortalShellV66Q6) return;
  window.__nmmPortalShellV66Q6 = true;

  const text = (element) => String(element?.textContent || '').trim();
  const appUrl = (path) => new URL(path, document.baseURI).href;

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

  const removeHeaderCallCenter = () => {
    document.querySelectorAll(
      '.portal-header-user > a.portal-top-action[href*="view=call-center"]'
    ).forEach((link) => link.remove());
  };

  const removeSidebarSignOut = () => {
    document.querySelectorAll(
      '.portal-sidebar-foot a[href*="logout.php"]'
    ).forEach((link) => link.remove());
  };

  const simplifyMyFeed = () => {
    if (document.body.dataset.portalActive !== 'social-posts') return;
    document.querySelectorAll(
      '.social-feed-toolbar, .social-feed-guidance'
    ).forEach((element) => element.remove());
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
    const script = role === 'admin' ? 'admin.php' : 'client.php';
    const links = role === 'admin'
      ? [
          ['Dashboard', appUrl(`portal/${script}?view=dashboard`)],
          ['Settings', appUrl('portal/admin.php?view=settings')],
          ['Account', appUrl('portal/admin.php?view=account')],
          ['Sign out', appUrl('portal/logout.php')],
        ]
      : [
          ['Dashboard', appUrl(`portal/${script}`)],
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
      document.querySelectorAll('[data-portal-user-menu-panel]').forEach((other) => {
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
    organizeNavigation();
    removeHeaderCallCenter();
    removeSidebarSignOut();
    simplifyMyFeed();
    installUserMenu();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
