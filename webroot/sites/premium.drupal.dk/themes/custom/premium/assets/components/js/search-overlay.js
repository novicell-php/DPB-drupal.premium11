/**
 * @file
 * Search overlay.
 */

document.addEventListener('DOMContentLoaded', () => {
  const searchOverlayTrigger = document.querySelector('.js-search-toggle');
  const searchOverlay = document.querySelector('.js-search-overlay');
  const searchOverlayClose = document.querySelector('.js-search-overlay__close');
  const searchOverlayInput = document.getElementById('js-search-overlay-input');
  const header = document.querySelector('.header');

  if (!searchOverlayTrigger && !searchOverlay) {
    return;
  }

  function toggleSearchOverlay(trigger) {
    if (searchOverlay.classList.contains('search-overlay--open')) {
      searchOverlay.classList.remove('search-overlay--open');
      header.classList.remove('search-overlay--open');
      searchOverlayTrigger.setAttribute('aria-expanded', 'false');
      searchOverlayTrigger.focus();
    } else if (trigger !== 'escape') {
      searchOverlay.classList.add('search-overlay--open');
      header.classList.add('search-overlay--open');
      searchOverlayTrigger.setAttribute('aria-expanded', 'true');
      searchOverlayInput.focus();
    }
  }

  searchOverlayTrigger.addEventListener('click', () => {
    toggleSearchOverlay();
  });

  searchOverlayClose.addEventListener('click', () => {
    toggleSearchOverlay();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      toggleSearchOverlay('escape');
    }
  });
});
