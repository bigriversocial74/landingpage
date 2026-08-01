(() => {
  const runtimeScript = document.currentScript;
  if (!document.querySelector('script[src*="public-user-menu-v66q6.js"]')) {
    const accountMenu = document.createElement('script');
    accountMenu.src = runtimeScript?.src
      ? new URL('public-user-menu-v66q6.js?v=20260731-v66Q6', runtimeScript.src).href
      : new URL('assets/js/public-user-menu-v66q6.js?v=20260731-v66Q6', document.baseURI).href;
    document.head.appendChild(accountMenu);
  }

  const button = document.querySelector('[data-landing-menu]');
  const navigation = document.querySelector('[data-landing-navigation]');
  if (!button || !navigation) return;

  button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', open ? 'false' : 'true');
    navigation.classList.toggle('is-open', !open);
  });

  navigation.addEventListener('click', (event) => {
    if (event.target.closest('a')) {
      button.setAttribute('aria-expanded', 'false');
      navigation.classList.remove('is-open');
    }
  });
})();
