/**
 * PRO feature windows of the free version. Any element with data-sgg-upsell="<feature>" opens the window of that
 * feature, if the page has it (templates printed by GridGallery_Upsell_Module); otherwise the element keeps its own
 * behaviour. The listener runs in the capture phase, so a PRO row of the classic settings opens this window instead
 * of its old "Read more" popup.
 */
(function () {
  'use strict';

  var dialog = null,
    body = null;

  function setup() {
    if (dialog) {
      return true;
    }
    dialog = document.querySelector('[data-sgg-up]');
    body = dialog ? dialog.querySelector('[data-sgg-up-body]') : null;
    if (!dialog || !body || typeof dialog.showModal !== 'function') {
      dialog = null;
      return false;
    }
    dialog.addEventListener('click', function (event) {
      // A click on the dimmed backdrop lands on the dialog itself
      if (event.target === dialog || event.target.closest('[data-sgg-up-close]')) {
        dialog.close();
      }
    });
    return true;
  }

  // The first picture in the HTML description of an old PRO row
  function firstImage(html) {
    if (!html || html.indexOf('<img') === -1) {
      return '';
    }
    var image = new DOMParser().parseFromString(html, 'text/html').querySelector('img');
    return image ? image.getAttribute('src') || '' : '';
  }

  /**
   * @param {string} key The feature
   * @param {Element} [source] The element that asked, for the picture of a classic PRO row
   * @return {boolean} Whether the window opened
   */
  function open(key, source) {
    var template = key ? document.getElementById('sgg-up-' + key) : null;
    if (!template || !setup()) {
      return false;
    }
    // The windows lead to each other; a classic feature still finds the picture of its own row
    if (!source || source.closest('[data-sgg-up]')) {
      source = document.querySelector('.gg-pro-read-more[data-sgg-upsell="' + key + '"]');
    }
    body.textContent = '';
    body.appendChild(template.content.cloneNode(true));

    var shot = body.querySelector('[data-sgg-up-shot]');
    if (shot && source) {
      var src = source.getAttribute('data-sgg-upsell-image') || firstImage(source.getAttribute('data-gg-pro-desc'));
      if (src) {
        shot.src = src;
        shot.hidden = false;
        shot.parentNode.classList.add('has-shot');
      }
    }
    if (!dialog.open) {
      dialog.showModal();
    }
    body.scrollTop = 0;
    var cta = body.querySelector('.sgg-up-cta');
    if (cta) {
      cta.focus({ preventScroll: true });
    }
    return true;
  }

  document.addEventListener(
    'click',
    function (event) {
      var trigger = event.target.closest ? event.target.closest('[data-sgg-upsell]') : null;
      if (trigger && open(trigger.getAttribute('data-sgg-upsell'), trigger)) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    },
    true
  );

  window.SggUpsell = { open: open };
})();
