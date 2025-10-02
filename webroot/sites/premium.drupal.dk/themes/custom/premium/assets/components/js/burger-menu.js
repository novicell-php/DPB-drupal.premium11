/**
 * @file
 * Accessible Burger menu with keyboard support and focus trap.
 */

document.addEventListener('DOMContentLoaded', () => {
  const burgerMenuTrigger = document.getElementById('js-burger');
  const burgerMenu = document.getElementById('js-burger-menu');
  const burgerMenuClose = document.getElementById('js-burger-menu__close');

  if ((!burgerMenu && !burgerMenuClose) || burgerMenu.classList.contains('loaded')) {
    return;
  }
  burgerMenu.classList.add('loaded');

  const getFocusableElements = (container) => container.querySelectorAll(
    'a[href], button:not([disabled]), textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select, [tabindex]:not([tabindex="-1"])',
  );

  let previouslyFocusedElement = null;
  let isKeyboard = false;

  const trapFocus = (container) => {
    const focusableEls = getFocusableElements(container);
    const firstEl = focusableEls[0];
    const lastEl = focusableEls[focusableEls.length - 1];

    container.addEventListener('keydown', (e) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstEl) {
          e.preventDefault();
          lastEl.focus();
        }
      } else if (document.activeElement === lastEl) {
        e.preventDefault();
        firstEl.focus();
      }
    });
  };

  const closeMenu = () => {
    burgerMenu.classList.remove('burger-menu--open');
    burgerMenuTrigger.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = 'auto';

    if (previouslyFocusedElement) {
      previouslyFocusedElement.focus();
    }
  };

  const openMenu = () => {
    previouslyFocusedElement = document.activeElement;
    burgerMenu.classList.add('burger-menu--open');
    burgerMenuTrigger.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';

    const focusableEls = getFocusableElements(burgerMenu);
    if (focusableEls.length) {
      focusableEls[0].focus();
    }

    trapFocus(burgerMenu);
  };

  const showSubNav = (trigger, navItem) => {
    const subNav = navItem.lastElementChild;
    const subNavItems = subNav.childNodes;
    const navItemParent = navItem.parentElement;
    let subNavHeight = 0;

    if (navItem.classList.contains('open-sub-navigation')) return;
    navItem.classList.add('open-sub-navigation');

    subNavItems.forEach((subNavItem) => {
      subNavHeight += subNavItem.offsetHeight || 0;
      subNavItem.querySelectorAll('a, button').forEach((subNavItemTriggers) => {
        subNavItemTriggers.setAttribute('tabindex', '0');
      });
    });

    trigger.setAttribute('aria-expanded', 'true');

    if (navItemParent.offsetHeight > 0 && navItemParent.classList.contains('js-burger-submenu-list')) {
      navItemParent.style.height = `${navItemParent.offsetHeight + subNavHeight}px`;
    }

    subNav.style.height = `${subNavHeight}px`;
  };

  const hideSubNav = (trigger, navItem) => {
    const subNav = navItem.lastElementChild;
    const subNavItems = subNav.childNodes;
    const navItemParent = navItem.parentElement;
    let subNavHeight = 0;

    trigger.setAttribute('aria-expanded', 'false');

    navItem.querySelectorAll('.js-burger-submenu-list').forEach((subNavigation) => {
      const currentSubNav = subNavigation;
      currentSubNav.parentElement.classList.remove('open-sub-navigation');
      currentSubNav.style.height = 0;
    });

    subNavItems.forEach((subNavItem) => {
      subNavHeight += subNavItem.offsetHeight || 0;
      subNavItem.querySelectorAll('a, button').forEach((subNavItemTriggers) => {
        subNavItemTriggers.setAttribute('tabindex', '-1');
      });
    });

    if (navItemParent.offsetHeight > 0 && navItemParent.classList.contains('js-burger-submenu-list')) {
      navItemParent.style.height = `${navItemParent.offsetHeight - subNavHeight}px`;
    }

    subNav.style.height = 0;
  };

  burgerMenuClose.addEventListener('click', closeMenu);
  burgerMenuTrigger.addEventListener('click', openMenu);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Tab') {
      isKeyboard = true;
    }
    if (e.key === 'Escape') {
      closeMenu();
    }
  });

  document.addEventListener('mousedown', () => {
    isKeyboard = false;
  });

  const navItems = burgerMenu.querySelectorAll('.burger-menu-list-item--has-children');
  navItems.forEach((navItem) => {
    const navTrigger = navItem.querySelector('.js-burger-menu-list-item__toggle');
    if (!navTrigger) return;

    navTrigger.addEventListener('click', (e) => {
      e.preventDefault();
      if (navTrigger.parentElement.classList.contains('open-sub-navigation')) {
        hideSubNav(navTrigger, navItem);
      } else {
        showSubNav(navTrigger, navItem);
      }
    });

    navTrigger.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (navTrigger.parentElement.classList.contains('open-sub-navigation')) {
          hideSubNav(navTrigger, navItem);
        } else {
          showSubNav(navTrigger, navItem);
        }
      }
    });

    navTrigger.addEventListener('focus', () => {
      if (isKeyboard) {
        requestAnimationFrame(() => {
          if (!navItem.classList.contains('open-sub-navigation')) {
            showSubNav(navTrigger, navItem);
          }
        });
      }
    });

    const navList = navItem.lastElementChild;
    navList.addEventListener('focusout', (e) => {
      if (!navItem.contains(e.relatedTarget)) {
        hideSubNav(navTrigger, navItem);
      }
    });

    const subNavItems = navItem.querySelectorAll('.burger-menu-list-item--has-children');
    subNavItems.forEach((subNavItem) => {
      const subNavTrigger = subNavItem.querySelector('.js-burger-submenu-list-item__toggle');
      if (!subNavTrigger) return;

      subNavTrigger.addEventListener('click', (e) => {
        e.preventDefault();
        if (subNavTrigger.parentElement.classList.contains('open-sub-navigation')) {
          hideSubNav(subNavTrigger, subNavItem);
        } else {
          showSubNav(subNavTrigger, subNavItem);
        }
      });

      subNavTrigger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          if (subNavTrigger.parentElement.classList.contains('open-sub-navigation')) {
            hideSubNav(subNavTrigger, subNavItem);
          } else {
            showSubNav(subNavTrigger, subNavItem);
          }
        }
      });

      subNavTrigger.addEventListener('focus', () => {
        if (isKeyboard) {
          requestAnimationFrame(() => {
            if (!subNavItem.classList.contains('open-sub-navigation')) {
              showSubNav(subNavTrigger, subNavItem);
            }
          });
        }
      });

      const subNavList = subNavItem.lastElementChild;
      subNavList.addEventListener('focusout', (e) => {
        if (!subNavItem.contains(e.relatedTarget)) {
          hideSubNav(subNavTrigger, subNavItem);
        }
      });
    });
  });
});
