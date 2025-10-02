import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

Drupal.behaviors.imageSlideshow = {
  attach(context) {
    const imageSlideShowsWrappers = document.querySelectorAll('.js-image-slideshow-wrapper:not(.loaded)');
    if (imageSlideShowsWrappers.length === 0) {
      return;
    }
    for (let i = 0; i < imageSlideShowsWrappers.length; i += 1) {
      const current = imageSlideShowsWrappers[i];
      const currentSlidePrev = current.querySelector('.image-slideshow__button--prev');
      const currentSlideNext = current.querySelector('.image-slideshow__button--next');
      current.classList.add('loaded');
      // Build slider
      if (current.dataset.autoplay === 'true') {
        const swiper = new Swiper(current.querySelector('.swiper-container'), {
          modules: [Navigation, Pagination, Autoplay],
          lazy: true,
          loop: true,
          autoplay: {
            delay: 3000,
          },
          pagination: {
            el: current.querySelector('.image-slideshow-navigation'),
            type: 'bullets',
            clickable: true,
          },
          navigation: {
            nextEl: currentSlideNext,
            prevEl: currentSlidePrev,
          },
        });
      } else {
        const swiper = new Swiper(current.querySelector('.swiper-container'), {
          modules: [Navigation, Pagination, Autoplay],
          lazy: true,
          loop: false,
          pagination: {
            el: current.querySelector('.image-slideshow-navigation'),
            type: 'bullets',
            clickable: true,
          },
          navigation: {
            nextEl: currentSlideNext,
            prevEl: currentSlidePrev,
          },
        });
      }
    }
  },
};
