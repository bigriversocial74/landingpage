(() => {
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
