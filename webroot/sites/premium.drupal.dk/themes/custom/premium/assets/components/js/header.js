let lastScrollTop = 0;
const header = document.querySelector('.header');

document.addEventListener('scroll', () => {
  const st = window.pageYOffset || document.documentElement.scrollTop;
  if (st <= 60) {
    header.classList.remove('header--sticky');
  } else if (st > lastScrollTop) {
    if (!header.classList.contains('header--hidden')) {
      header.classList.add('header--hidden');
    }
  } else {
    header.classList.remove('header--hidden');
    header.classList.add('header--sticky');
  }
  lastScrollTop = st <= 0 ? 0 : st;
}, false);
