/* Review request card: "Is it working well?" leads to the WordPress.org review form or to support; every answer is saved for the current user. */
(function (init) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(function () {
  var card = document.querySelector('[data-sgg-review]');
  if (!card) {
    return;
  }
  var answered = false;
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function showStep(name, focus) {
    Array.prototype.forEach.call(card.querySelectorAll('[data-review-step]'), function (step) {
      var isActive = step.getAttribute('data-review-step') === name;
      step.hidden = !isActive;
      step.classList.toggle('is-active', isActive);
      if (isActive && focus) {
        var first = step.querySelector('a, button');
        if (first) {
          first.focus({ preventScroll: true });
        }
      }
    });
    card.className = card.className.replace(/\bis-step-\S+/g, '').trim() + ' is-step-' + name;
  }

  function send(answer) {
    var body = new URLSearchParams();
    body.append('action', 'grid-gallery');
    body.append('route[module]', 'review');
    body.append('route[action]', 'respond');
    body.append('_wpnonce', card.getAttribute('data-nonce'));
    body.append('answer', answer);

    if (window.fetch) {
      window.fetch(card.getAttribute('data-ajax-url'), { method: 'POST', credentials: 'same-origin', body: body }).catch(function () {});
      return;
    }
    var xhr = new XMLHttpRequest();
    xhr.open('POST', card.getAttribute('data-ajax-url'));
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.send(body.toString());
  }

  function leave(delay) {
    window.setTimeout(function () {
      if (reduced) {
        card.parentNode && card.parentNode.removeChild(card);
        return;
      }
      card.style.maxHeight = card.offsetHeight + 'px';
      // Reflow so the collapse starts from the current height
      void card.offsetHeight;
      card.classList.add('is-leaving');
      card.style.maxHeight = '0px';
      window.setTimeout(function () {
        card.parentNode && card.parentNode.removeChild(card);
      }, 700);
    }, delay || 0);
  }

  card.addEventListener('click', function (event) {
    var go = event.target.closest('[data-review-go]');
    if (go) {
      showStep(go.getAttribute('data-review-go'), true);
      return;
    }
    var control = event.target.closest('[data-review-answer]');
    if (!control || answered) {
      return;
    }
    // Links (the review form, support) open in a new tab on their own
    answered = true;
    var answer = control.getAttribute('data-review-answer');
    send(answer);

    if (answer === 'rate' || answer === 'done') {
      showStep('thanks', false);
      leave(5000);
    } else if (answer === 'support') {
      showStep('support', false);
      leave(5000);
    } else {
      leave(0);
    }
  });
});
