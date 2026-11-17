/* Deactivation dialog of the Supsystic plugins (v1) on the Plugins page: asks why a plugin is switched off, then deactivates it.
   One script serves every registered plugin and its PRO (see deactivation.php). */
(function () {
  'use strict';

  var data = window.supsysticDeactivationV1;
  if (!data || !data.targets || !data.targets.length) {
    return;
  }
  var texts = data.texts || {};
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var dialogs = {};
  var current = null;
  var lastFocus = null;
  var deactivateUrl = '';
  var busy = false;
  var uid = 0;

  function findLink(plugin) {
    var rows = document.querySelectorAll('tr[data-plugin]');
    for (var i = 0; i < rows.length; i++) {
      if (rows[i].getAttribute('data-plugin') === plugin) {
        return rows[i].querySelector('span.deactivate a');
      }
    }
    return null;
  }

  function make(tag, className, text) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (text) {
      node.textContent = text;
    }
    return node;
  }

  function reasonDetails(reason) {
    if (!reason.placeholder && !reason.help) {
      return null;
    }
    var more = make('div', 'sup-deact__more');
    more.hidden = true;
    if (reason.placeholder) {
      var area = make('textarea', 'sup-deact__details');
      area.rows = 2;
      area.maxLength = data.maxDetails || 2000;
      area.placeholder = reason.placeholder;
      area.setAttribute('aria-label', reason.placeholder);
      more.appendChild(area);
    }
    if (reason.help && data.supportUrl) {
      var help = make('p', 'sup-deact__help', (texts.help || '') + ' ');
      var link = make('a', '', texts.helpLink);
      link.href = data.supportUrl;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      help.appendChild(link);
      more.appendChild(help);
    }
    return more;
  }

  function radio(name, code) {
    var input = make('input');
    input.type = 'radio';
    input.name = name;
    input.value = code;
    return input;
  }

  function build(target) {
    var id = 'sup-deact-' + (++uid);
    var name = id + '-reason';
    var overlay = make('div', 'sup-deact');
    overlay.hidden = true;

    var box = make('div', 'sup-deact__box');
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-labelledby', id + '-title');
    box.setAttribute('aria-describedby', id + '-intro');

    var close = make('button', 'sup-deact__close');
    close.type = 'button';
    close.setAttribute('aria-label', texts.close || 'Close');
    close.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    close.addEventListener('click', hide);

    var title = make('h2', 'sup-deact__title', texts.title);
    title.id = id + '-title';
    var intro = make('p', 'sup-deact__intro', target.intro);
    intro.id = id + '-intro';

    var group = make('div', 'sup-deact__reasons');
    group.setAttribute('role', 'radiogroup');
    group.setAttribute('aria-labelledby', id + '-title');

    // A moment off (debugging, maintenance, reinstall): buttons in one row
    var quick = (target.reasons || []).filter(function (r) { return r.group === 'quick'; });
    var main = (target.reasons || []).filter(function (r) { return r.group !== 'quick'; });
    if (quick.length) {
      group.appendChild(make('p', 'sup-deact__group', texts.quick));
      var chips = make('div', 'sup-deact__chips');
      quick.forEach(function (reason) {
        var chip = make('label', 'sup-deact__chip sup-deact__reason');
        chip.appendChild(radio(name, reason.code));
        chip.appendChild(make('span', '', reason.label));
        chips.appendChild(chip);
      });
      group.appendChild(chips);
      group.appendChild(make('p', 'sup-deact__group', texts.main));
    }
    var list = make('div', 'sup-deact__list');
    main.forEach(function (reason) {
      var item = make('div', 'sup-deact__item sup-deact__reason');
      var label = make('label', 'sup-deact__option');
      label.appendChild(radio(name, reason.code));
      label.appendChild(make('span', 'sup-deact__mark'));
      label.appendChild(make('span', 'sup-deact__label', reason.label));
      item.appendChild(label);
      var more = reasonDetails(reason);
      if (more) {
        item.appendChild(more);
      }
      list.appendChild(item);
    });
    group.appendChild(list);

    var email = null;
    var emailError = null;
    var mail = target.email || {};
    var emailBox = null;
    if (mail.mode === 'input') {
      emailBox = make('div', 'sup-deact__email');
      var emailLabel = make('label', 'sup-deact__email-label', texts.emailLabel);
      emailLabel.htmlFor = id + '-email';
      email = make('input', 'sup-deact__email-input');
      email.type = 'email';
      email.id = id + '-email';
      email.maxLength = 190;
      email.autocomplete = 'email';
      email.placeholder = 'you@example.com';
      email.setAttribute('aria-describedby', id + '-email-hint');
      var hint = make('p', 'sup-deact__email-hint', texts.emailHint);
      hint.id = id + '-email-hint';
      emailError = make('p', 'sup-deact__email-error', texts.emailError);
      emailError.id = id + '-email-error';
      emailError.hidden = true;
      emailError.setAttribute('role', 'alert');
      email.addEventListener('input', function () {
        if (!emailError.hidden && checkEmail(email, emailError, false)) {
          emailError.hidden = true;
        }
      });
      email.addEventListener('blur', function () {
        checkEmail(email, emailError, true);
      });
      emailBox.appendChild(emailLabel);
      emailBox.appendChild(email);
      emailBox.appendChild(hint);
      emailBox.appendChild(emailError);
    } else if (mail.mode === 'license' && mail.value) {
      emailBox = make('p', 'sup-deact__license', (texts.licenseEmail || '') + ' ');
      emailBox.appendChild(make('b', '', mail.value));
    }

    var privacy = make('p', 'sup-deact__privacy', target.privacy);

    var foot = make('div', 'sup-deact__foot');
    var skip = make('button', 'sup-deact__skip', texts.skip);
    skip.type = 'button';
    var actions = make('div', 'sup-deact__actions');
    var cancel = make('button', 'sup-deact__btn sup-deact__btn--ghost', texts.cancel);
    cancel.type = 'button';
    var submit = make('button', 'sup-deact__btn sup-deact__btn--primary', texts.submit);
    submit.type = 'button';
    submit.disabled = true;
    actions.appendChild(cancel);
    actions.appendChild(submit);
    foot.appendChild(skip);
    foot.appendChild(actions);

    box.appendChild(close);
    box.appendChild(title);
    box.appendChild(intro);
    box.appendChild(group);
    if (emailBox) {
      box.appendChild(emailBox);
    }
    box.appendChild(privacy);
    box.appendChild(foot);
    overlay.appendChild(box);
    document.body.appendChild(overlay);

    group.addEventListener('change', function (event) {
      if (event.target.name !== name) {
        return;
      }
      var items = group.querySelectorAll('.sup-deact__reason');
      for (var i = 0; i < items.length; i++) {
        var input = items[i].querySelector('input[type="radio"]');
        var more = items[i].querySelector('.sup-deact__more');
        items[i].classList.toggle('is-checked', input.checked);
        if (more) {
          more.hidden = !input.checked;
          var area = more.querySelector('textarea');
          if (input.checked && area) {
            area.focus();
          }
        }
      }
      submit.disabled = false;
    });

    cancel.addEventListener('click', hide);
    skip.addEventListener('click', function () {
      go(skip);
    });
    submit.addEventListener('click', function () {
      send(target, name, email, emailError);
    });
    overlay.addEventListener('mousedown', function (event) {
      if (event.target === overlay) {
        hide();
      }
    });
    overlay.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        hide();
      } else if (event.key === 'Tab') {
        trapFocus(event, box, name);
      }
    });

    return overlay;
  }

  // An empty field is fine; a filled one must look like an email
  function checkEmail(input, error, show) {
    var value = input.value.trim();
    var ok = value === '' || (EMAIL.test(value) && (!input.validity || input.validity.valid));
    if (show) {
      error.hidden = ok;
      input.setAttribute('aria-invalid', ok ? 'false' : 'true');
      if (ok) {
        input.removeAttribute('aria-describedby');
        input.setAttribute('aria-describedby', input.id + '-hint');
      } else {
        input.setAttribute('aria-describedby', input.id + '-hint ' + error.id);
      }
    }
    return ok;
  }

  function trapFocus(event, box, name) {
    // Tab reaches a radio group once: on its checked radio, or on the first one while none is checked
    var entryRadio = box.querySelector('input[name="' + name + '"]:checked') || box.querySelector('input[name="' + name + '"]');
    var nodes = Array.prototype.filter.call(
      box.querySelectorAll('button, a[href], input, textarea'),
      function (node) {
        return !node.disabled && node.offsetParent !== null && (node.type !== 'radio' || node === entryRadio);
      }
    );
    if (!nodes.length) {
      return;
    }
    var first = nodes[0];
    var last = nodes[nodes.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function show(target, url) {
    deactivateUrl = url;
    if (!dialogs[target.plugin + ':' + target.key]) {
      dialogs[target.plugin + ':' + target.key] = build(target);
    }
    current = dialogs[target.plugin + ':' + target.key];
    lastFocus = document.activeElement;
    current.hidden = false;
    document.documentElement.classList.add('sup-deact-open');
    var first = current.querySelector('input[type="radio"]');
    if (first) {
      first.focus();
    }
  }

  function hide() {
    if (!current || busy) {
      return;
    }
    current.hidden = true;
    document.documentElement.classList.remove('sup-deact-open');
    if (lastFocus && lastFocus.focus) {
      lastFocus.focus();
    }
  }

  function setBusy(button) {
    busy = true;
    var nodes = current.querySelectorAll('button, input, textarea');
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].disabled = true;
    }
    current.classList.add('is-busy');
    if (button) {
      button.classList.add('is-busy');
      button.textContent = texts.sending || button.textContent;
    }
  }

  function go(button) {
    setBusy(button);
    window.location.href = deactivateUrl;
  }

  function send(target, name, email, emailError) {
    var checked = current.querySelector('input[name="' + name + '"]:checked');
    if (!checked || busy) {
      return;
    }
    if (email && !checkEmail(email, emailError, true)) {
      email.focus();
      return;
    }
    var holder = checked.closest('.sup-deact__reason');
    var area = holder ? holder.querySelector('textarea') : null;
    var body = new URLSearchParams();
    body.append('action', data.action);
    body.append('nonce', data.nonce);
    body.append('plugin', target.plugin);
    body.append('target', target.key);
    body.append('reason', checked.value);
    body.append('details', area ? area.value.trim() : '');
    body.append('email', email ? email.value.trim() : '');

    setBusy(current.querySelector('.sup-deact__btn--primary'));

    var done = false;
    function finish() {
      if (!done) {
        done = true;
        window.location.href = deactivateUrl;
      }
    }
    // The plugin is deactivated even when the answer cannot be sent: never keep the user waiting for long
    var timer = window.setTimeout(finish, 6000);
    window.fetch(data.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(finish, finish).then(function () {
      window.clearTimeout(timer);
    });
  }

  function init() {
    if (!window.fetch || !window.URLSearchParams) {
      return;
    }
    data.targets.forEach(function (target) {
      var link = findLink(target.basename);
      if (!link) {
        return;
      }
      link.addEventListener('click', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) {
          return;
        }
        event.preventDefault();
        show(target, link.href);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
