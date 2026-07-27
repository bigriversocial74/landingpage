/* North Mountain Media public shell v52 */
(() => {
  'use strict';

  const account = document.querySelector(
    '[data-public-account]'
  );
  const accountToggle = document.querySelector(
    '[data-public-account-toggle]'
  );
  const accountMenu = document.querySelector(
    '[data-public-account-menu]'
  );

  accountToggle?.addEventListener('click', () => {
    if (!accountMenu) {
      return;
    }

    const nextHidden = !accountMenu.hidden;
    accountMenu.hidden = nextHidden;
    accountToggle.setAttribute(
      'aria-expanded',
      nextHidden ? 'false' : 'true'
    );
  });

  document.addEventListener('click', (event) => {
    if (
      account
      && !account.contains(event.target)
      && accountMenu
    ) {
      accountMenu.hidden = true;
      accountToggle?.setAttribute(
        'aria-expanded',
        'false'
      );
    }
  });

  document.addEventListener('keydown', (event) => {
    if (
      event.key === 'Escape'
      && accountMenu
    ) {
      accountMenu.hidden = true;
      accountToggle?.setAttribute(
        'aria-expanded',
        'false'
      );
    }
  });
})();
