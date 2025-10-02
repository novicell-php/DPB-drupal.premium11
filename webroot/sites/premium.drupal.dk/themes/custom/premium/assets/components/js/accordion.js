/**
 * @file
 * Accordion toggle.
 */

Drupal.behaviors.accordion = {
  attach(context) {
    const accordions = document.querySelectorAll('.js-accordion:not(.loaded)');
    if (accordions.length === 0) {
      return;
    }

    for (let i = 0; i < accordions.length; i += 1) {
      const accordion = accordions[i];
      accordion.classList.add('loaded');
      const accordionItems = accordion.querySelectorAll('.js-accordion-item');
      if (accordionItems.length === 0) {
        return;
      }

      for (let j = 0; j < accordionItems.length; j += 1) {
        const accordionItem = accordionItems[j];
        const accordionItemHeader = accordionItem.querySelector('.js-accordion-item__headline');
        accordionItemHeader.addEventListener('click', () => {
          accordionItem.classList.toggle('is-expanded');
          if (accordionItem.classList.contains('is-expanded')) {
            accordionItemHeader.setAttribute('aria-expanded', 'true');
          } else {
            accordionItemHeader.setAttribute('aria-expanded', 'false');
          }
        });
      }

      const hiddenAccordions = accordion.querySelectorAll('.js-accordion-item.is-hidden');

      if (hiddenAccordions.length > 0) {
        const showMoreButton = accordion.querySelector('.js-accordion__showmore');
        showMoreButton.addEventListener('click', () => {
          showMoreButton.remove();
          for (let k = 0; k < hiddenAccordions.length; k += 1) {
            hiddenAccordions[k].classList.remove('is-hidden');
          }
        });
      }
    }
  },
};
