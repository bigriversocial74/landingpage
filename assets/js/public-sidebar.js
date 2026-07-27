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

  document.addEventListener(
    'keydown',
    (event) => {
      if (event.key === 'Escape') {
        closeSidebar();
      }
    }
  );
})();
