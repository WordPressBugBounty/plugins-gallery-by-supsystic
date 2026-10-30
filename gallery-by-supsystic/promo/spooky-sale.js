/* "Spooky Sale" admin popup banner, October 29 - November 9, 2026 */
(function () {
  'use strict';

  var FADE_DURATION = 600;
  var ONE_DAY = 86400;

  function init() {
    var overlay = document.getElementById('supsystic-spooky-sale');
    if (!overlay) {
      return;
    }
    var closeBtn = overlay.querySelector('.supsystic-spooky-close');

    function onKeydown(e) {
      if (e.key === 'Escape' || e.key === 'Esc') {
        close();
      }
    }

    function close() {
      document.removeEventListener('keydown', onKeydown);
      overlay.classList.remove('is-visible');
      setTimeout(function () {
        if (overlay.parentNode) {
          overlay.parentNode.removeChild(overlay);
        }
      }, FADE_DURATION);
    }

    function show() {
      // Remember the show for 24 hours: this plugin does not bring the banner back until its cookie expires
      document.cookie = overlay.getAttribute('data-cookie') + '=1; max-age=' + ONE_DAY + '; path=' + (overlay.getAttribute('data-cookie-path') || '/') + '; SameSite=Lax';
      overlay.hidden = false;
      // Force a reflow so the opacity transition starts from 0
      void overlay.offsetWidth;
      overlay.classList.add('is-visible');
      document.addEventListener('keydown', onKeydown);
    }

    closeBtn.addEventListener('click', close);
    show();
  }

  // The banner markup is printed before the footer scripts, so there is no need to wait for the whole page
  if (document.getElementById('supsystic-spooky-sale') || document.readyState !== 'loading') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
