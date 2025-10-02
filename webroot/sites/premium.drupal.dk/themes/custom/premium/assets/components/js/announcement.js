/**
 * @file
 * Announcement dismiss.
 */

const announcements = document.querySelectorAll('.js-announcement-item');

// Helper: set a session cookie (no expiry)
function setSessionCookie(name, value) {
  document.cookie = `${name}=${value}; path=/`;
}

// Helper: check if a cookie exists
function hasCookie(name) {
  return document.cookie.split('; ').some((row) => row.startsWith(`${name}=`));
}

if (announcements.length) {
  announcements.forEach((announcementEl, index) => {
    const id = announcementEl.getAttribute('data-id') || `announcement-${index}`;
    const announcementItem = announcementEl;

    // Hide if cookie already set
    if (!hasCookie(id)) {
      announcementItem.classList.add('is-visible');
    }

    const closeBtn = announcementItem.querySelector('.js-announcement__dismiss');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => {
        // Hide announcement
        announcementItem.classList.remove('is-visible');
        // Set cookie until browser exit
        setSessionCookie(id, 'dismissed');
      });
    }
  });
}
