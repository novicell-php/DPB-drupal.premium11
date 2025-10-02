/**
 * @file
 * Burger menu.
 */

document.addEventListener('DOMContentLoaded', () => {
  const burgerMenuTrigger = document.getElementById('js-burger');
  const burgerMenu = document.getElementById('js-burger-menu');

  if ((!burgerMenu && !burgerMenuTrigger) || burgerMenu.classList.contains('loaded')) {
    return;
  }

  burgerMenuTrigger.addEventListener('click', () => {
    if (burgerMenu.classList.contains('burger-menu--open')) {
      burgerMenu.classList.remove('burger-menu--open');
      burgerMenuTrigger.setAttribute('aria-expanded', 'false');
      burgerMenuTrigger.focus();
      document.body.style.overflow = 'auto';
    } else {
      burgerMenu.classList.add('burger-menu--open');
      burgerMenuTrigger.setAttribute('aria-expanded', 'true');
      burgerMenu.focus();
      document.body.style.overflow = 'hidden';
    }
  });
});
