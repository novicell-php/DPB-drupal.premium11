import Swiper from 'swiper';
import { Autoplay, Navigation, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

Drupal.behaviors.contentCarousel = {
  attach(context) {
    const contentCarouselWrappers = document.querySelectorAll('.js-content-carousel__block:not(.loaded)');
    if (contentCarouselWrappers.length > 0) {
      for (let i = 0; i < contentCarouselWrappers.length; i += 1) {
        const current = contentCarouselWrappers[i];
        const contentCarouselSwiper = current.querySelector('.content-carousel__wrapper--slider');
        if (contentCarouselSwiper) {
          const currentSlides = contentCarouselSwiper.querySelectorAll('.content-carousel-slide');
          const nextButton = current.querySelector('.content-carousel__button--next');
          const prevButton = current.querySelector('.content-carousel__button--prev');
          current.classList.add('loaded');

          // Build slider
          const swiper = new Swiper(current.querySelector('.swiper-container'), {
            modules: [Navigation, Pagination],
            lazy: true,
            loop: false,
            navigation: {
              nextEl: nextButton,
              prevEl: prevButton,
            },
            pagination: {
              el: current.querySelector('.content-carousel__navigation'),
              type: 'bullets',
              clickable: true,
            },
            slidesPerView: 1,
            breakpoints: {
              413: {
                slidesPerView: 1.3,
              },
              576: {
                slidesPerView: 2,
              },
              768: {
                slidesPerView: 2.4,
              },
              992: {
                slidesPerView: 2.5,
              },
              1200: {
                slidesPerView: 3,
              },
            },
          });
        }
      }
    }
  },
};
