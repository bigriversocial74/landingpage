/* North Mountain Media unified public sidebar v52 */
(() => {
  'use strict';

  const sidebar = document.getElementById(
    'workspaceSidebar'
  );
  const openButton = document.querySelector(
    '[data-sidebar-open]'
  );
  const closeButtons = document.querySelectorAll(
    '[data-sidebar-close]'
  );
  const backdrop = document.querySelector(
    '.sidebar-backdrop'
  );

  const closeSidebar = () => {
    sidebar?.classList.remove('is-open');
    backdrop?.classList.remove('is-open');
    document.body.classList.remove('sidebar-open');
    openButton?.setAttribute(
      'aria-expanded',
      'false'
    );
  };

  const openSidebar = () => {
    sidebar?.classList.add('is-open');
    backdrop?.classList.add('is-open');
    document.body.classList.add('sidebar-open');
    openButton?.setAttribute(
      'aria-expanded',
      'true'
    );
  };

  openButton?.addEventListener(
    'click',
    openSidebar
  );

  closeButtons.forEach((button) => {
    button.addEventListener(
      'click',
      closeSidebar
    );
  });


  document.querySelectorAll(
    '[data-portfolio-open]'
  ).forEach((button) => {
    button.addEventListener('click', () => {
      const slug = String(
        button.dataset.portfolioOpen || ''
      ).trim();

      if (!slug) {
        return;
      }

      const destination = new URL(
        'index.php',
        document.baseURI
      );
      destination.searchParams.set(
        'portfolio',
        slug
      );
      window.location.assign(destination.href);
    });
  });

  document.querySelectorAll(
    '.sidebar-nav a'
  ).forEach((link) => {
    link.addEventListener(
      'click',
      closeSidebar
    );
  });

  const rssModal = document.querySelector('[data-rss-modal]');
  const rssOpen = document.querySelector('[data-rss-modal-open]');
  const rssClose = document.querySelectorAll('[data-rss-modal-close]');
  const rssCopy = document.querySelector('[data-rss-feed-copy]');
  const rssUrl = document.querySelector('[data-rss-feed-url]');
  const rssStatus = document.querySelector('[data-rss-copy-status]');
  let rssReturnFocus = null;

  const closeRssModal = () => {
    if (!rssModal) return;
    rssModal.hidden = true;
    rssModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('rss-feed-modal-open');
    rssReturnFocus?.focus?.({ preventScroll: true });
    rssReturnFocus = null;
  };

  const openRssModal = () => {
    if (!rssModal) return;
    rssReturnFocus = document.activeElement;
    closeSidebar();
    rssModal.hidden = false;
    rssModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('rss-feed-modal-open');
    window.requestAnimationFrame(() => rssCopy?.focus());
  };

  rssOpen?.addEventListener('click', openRssModal);
  rssClose.forEach((button) => button.addEventListener('click', closeRssModal));

  rssCopy?.addEventListener('click', async () => {
    const value = String(rssUrl?.value || '').trim();
    if (!value) return;

    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
      } else {
        rssUrl.focus();
        rssUrl.select();
        document.execCommand('copy');
      }
      rssCopy.textContent = 'RSS URL copied';
      if (rssStatus) rssStatus.textContent = 'The RSS feed address is ready to paste into your feed reader.';
      window.setTimeout(() => {
        rssCopy.textContent = 'Copy RSS Feed URL';
      }, 2200);
    } catch (error) {
      rssUrl?.focus();
      rssUrl?.select();
      if (rssStatus) rssStatus.textContent = 'Select the address and copy it manually.';
    }
  });

  document.addEventListener(
    'keydown',
    (event) => {
      if (event.key === 'Escape') {
        if (rssModal && !rssModal.hidden) {
          closeRssModal();
          return;
        }
        closeSidebar();
      }
    }
  );
})();
