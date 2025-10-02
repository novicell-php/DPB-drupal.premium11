/**
 * @file
 * Multi-level navigation with fade animations + WCAG keyboard support.
 */

import HoverIntent from 'hoverintent';

Drupal.behaviors.navigation = {
  attach(context) {
    const navigation = document.querySelectorAll('.js-navigation:not(.loaded)');
    if (navigation.length === 0) return;

    let currentlyOpenMenu = null; // track only first-level open menu

    const setupSubMenu = (parentItem, isFirstLevel = false) => {
      const subNav = parentItem.querySelector('.js-sub-navigation');
      const trigger = parentItem.querySelector('a, span');
      if (!trigger) return;

      let hideTimer;
      let animationEndHandler;

      // If it's a <span>, make it tabbable + accessible
      if (trigger.tagName.toLowerCase() === 'span') {
        trigger.setAttribute('tabindex', '0');
        trigger.setAttribute('role', 'link');
      }

      if (subNav) {
        // ARIA setup
        parentItem.setAttribute('aria-haspopup', 'true');
        parentItem.setAttribute('aria-expanded', 'false');
        subNav.setAttribute('aria-hidden', 'true');
      }

      function hideSubNav(targetParent = parentItem) {
        const targetSubNav = targetParent.querySelector('.js-sub-navigation');
        if (!targetSubNav) return;

        targetParent.classList.remove('open-sub-navigation');
        targetParent.setAttribute('aria-expanded', 'false');
        targetSubNav.setAttribute('aria-hidden', 'true');

        targetSubNav.classList.remove('fade-in');
        targetSubNav.classList.add('fade-out');

        if (animationEndHandler) {
          targetSubNav.removeEventListener('animationend', animationEndHandler);
          animationEndHandler = null;
        }

        animationEndHandler = () => {
          if (targetSubNav.classList.contains('fade-out')) {
            targetSubNav.style.display = 'none';
          }
          targetSubNav.removeEventListener('animationend', animationEndHandler);
          animationEndHandler = null;
        };
        targetSubNav.addEventListener('animationend', animationEndHandler);

        if (isFirstLevel && currentlyOpenMenu === targetParent) {
          currentlyOpenMenu = null;
        }
      }

      const showSubNav = () => {
        clearTimeout(hideTimer);

        if (isFirstLevel && currentlyOpenMenu && currentlyOpenMenu !== parentItem) {
          hideSubNav(currentlyOpenMenu);
        }

        if (!subNav) return;

        if (animationEndHandler) {
          subNav.removeEventListener('animationend', animationEndHandler);
          animationEndHandler = null;
        }

        parentItem.classList.add('open-sub-navigation');
        parentItem.setAttribute('aria-expanded', 'true');
        subNav.setAttribute('aria-hidden', 'false');

        subNav.classList.remove('fade-out');
        subNav.style.display = 'block';

        requestAnimationFrame(() => {
          subNav.classList.add('fade-in');
        });

        if (isFirstLevel) {
          currentlyOpenMenu = parentItem;
        }
      };

      if (subNav) {
        // HoverIntent for mouse
        HoverIntent(parentItem, showSubNav, () => hideSubNav(parentItem)).options({
          timeout: 400,
          interval: 55,
        });

        // Keyboard: open on focus
        trigger.addEventListener('focus', () => {
          if (!parentItem.classList.contains('open-sub-navigation')) {
            showSubNav();
          }
        });

        // Keyboard: Enter/Space opens submenu
        trigger.addEventListener('keydown', (e) => {
          if ((e.key === 'Enter' || e.key === ' ') && !parentItem.classList.contains('open-sub-navigation')) {
            e.preventDefault();
            showSubNav();
          }
        });

        // Close submenu on Escape
        subNav.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') {
            e.preventDefault();
            hideSubNav();
            trigger.focus();
          }
        });

        // Close submenu when focus leaves submenu entirely
        subNav.addEventListener('focusout', () => {
          setTimeout(() => {
            if (!subNav.contains(document.activeElement)) {
              hideSubNav();
            }
          }, 0);
        });

        // Recursively setup deeper submenus
        const subNavItems = subNav.querySelectorAll('.js-sub-navigation-item');
        subNavItems.forEach((item) => {
          setupSubMenu(item, false);
        });
      }

      if (isFirstLevel) {
        // Extra: if this item has no submenu, close any open menu when focused
        trigger.addEventListener('focus', () => {
          if (!subNav && currentlyOpenMenu && currentlyOpenMenu !== parentItem) {
            hideSubNav(currentlyOpenMenu);
          }
        });
      }
    };

    // Initialize top-level navigation items
    navigation.forEach((nav) => {
      const navItems = nav.querySelectorAll('.js-navigation-item');
      nav.classList.add('loaded');

      navItems.forEach((item) => {
        setupSubMenu(item, true);
      });
    });
  },
};
