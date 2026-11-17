/**
 * Dialogs of the gallery settings page and of the Images page: every dialog in the middle of the screen, the caption
 * effect picker and the icon effects picker as cards. Only the look changes: the fields, values and handlers of the
 * dialogs (settings.js, photos.js, the Images page) stay as they were.
 */
/**
 * Every dialog of these pages opens in the middle of the screen (an old rule pins dialogs to the top of the
 * page, out of view once the settings are scrolled); the PRO "Read more" windows keep their own place.
 */
(function ($) {
  'use strict';

  $(document).on('dialogopen', function (event) {
    var $content = $(event.target);
    if (!$content.dialog || !$content.dialog('instance')) {
      return;
    }
    var $widget = $content.dialog('widget');
    if (!$widget.hasClass('gg-pro-readmore-dialog-wrap')) {
      $widget.addClass('sgg-dlg-center');
    }
  });
})(jQuery);

/**
 * The caption effect picker (#effectDialog) as cards: the photo of this gallery with the caption effect on hover, the
 * name under it, the chosen one marked. The figures, their handlers and the effect field stay as they were; a card
 * only wraps its figure. Beside the "Choose effect" buttons a chip shows the effect in use.
 */
(function ($) {
  'use strict';

  var CHECKED_NAME = 'sgg-fx-name',
    FALLBACK =
      'data:image/svg+xml,' +
      encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><defs><linearGradient id="s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#b9d7ef"/><stop offset="1" stop-color="#7fa9cc"/></linearGradient></defs><rect width="300" height="300" fill="url(#s)"/><circle cx="226" cy="76" r="26" fill="#fde68a"/><path d="M0 190 70 120l60 50 70-80 100 90v120H0z" fill="#4f7a4b"/><path d="M0 232l90-50 80 40 80-50 50 30v98H0z" fill="#2f4f30"/></svg>'
      );

  // The name is the text holder.js draws on the picture; the effect key when there is none
  function nameOf(figure) {
    var image = figure.querySelector('img.dialog-image'),
      source = image ? image.getAttribute('data-src') || '' : '',
      match = /[?&]text=([^&]*)/.exec(source);
    if (match) {
      try {
        return decodeURIComponent(match[1].replace(/\+/g, ' '));
      } catch (error) {
        return match[1];
      }
    }
    var key = String(figure.getAttribute('data-grid-gallery-type') || '');
    return key.charAt(0).toUpperCase() + key.slice(1).replace(/-/g, ' ');
  }

  // The cover of the gallery in the editor head, an image of the page (the Images page), or a drawn landscape
  function photo() {
    var cover = document.querySelector('img.ggImg');
    if (cover && (cover.currentSrc || cover.src)) {
      return cover.currentSrc || cover.src;
    }
    var image = Array.prototype.filter.call(document.images, function (item) {
      return item.naturalWidth >= 120 && !item.closest('.ui-dialog') && !/holder|svg\+xml/.test(item.getAttribute('src') || '');
    })[0];
    return image ? image.currentSrc || image.src : FALLBACK;
  }

  function init() {
    var dialog = document.getElementById('effectDialog'),
      preview = document.getElementById('effectsPreview');
    if (!dialog || !preview || !$.fn.dialog || dialog.getAttribute('data-sgg-fx')) {
      return;
    }
    // The Images page sets the dialog up on its first use
    if (!$(dialog).dialog('instance')) {
      $(dialog).one('dialogopen', init);
      return;
    }
    dialog.setAttribute('data-sgg-fx', '1');
    var $dialog = $(dialog);
    $dialog.dialog('widget').addClass('gg-pth-dialog sgg-fx-dialog');

    Array.prototype.forEach.call(preview.querySelectorAll('figure.grid-gallery-caption'), function (figure) {
      var parent = figure.parentNode;
      if (parent.classList.contains('sgg-fx-card')) {
        return;
      }
      var card = document.createElement('div'),
        label = document.createElement('span');
      card.className = 'sgg-fx-card' + (figure.classList.contains('available-in-pro') ? ' is-locked' : '');
      // The effects with icons read their question from the parent of the figure
      if (parent.getAttribute('data-confirm')) {
        card.setAttribute('data-confirm', parent.getAttribute('data-confirm'));
      }
      label.className = CHECKED_NAME;
      label.textContent = nameOf(figure);
      parent.insertBefore(card, figure);
      card.appendChild(figure);
      card.appendChild(label);
    });
    // holder.js leaves the pictures alone once they have no data-src
    Array.prototype.forEach.call(preview.querySelectorAll('img.dialog-image'), function (image) {
      image.removeAttribute('data-src');
    });

    function paint() {
      var source = photo();
      Array.prototype.forEach.call(preview.querySelectorAll('img.dialog-image'), function (image) {
        if (image.getAttribute('src') !== source) {
          image.setAttribute('src', source);
        }
      });
    }

    // A click on the name picks the effect too
    preview.addEventListener('click', function (event) {
      var card = event.target.closest('.sgg-fx-card');
      if (!card || event.target.closest('figure')) {
        return;
      }
      var figure = card.querySelector('figure'),
        link = figure ? figure.querySelector('a[href]') : null;
      if (link && card.classList.contains('is-locked')) {
        link.click();
      } else if (figure) {
        $(figure).trigger('click');
      }
    });

    // The effect in use, beside each button that opens the picker
    var chips = [];
    ['chooseEffect', 'captionBuilderChooseEffect'].forEach(function (id) {
      var button = document.getElementById(id);
      if (!button) {
        return;
      }
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'sgg-pth-current sgg-fx-current';
      chip.innerHTML = '<span class="sgg-fx-pic"><img alt=""></span><span class="sgg-fx-current-name"></span>';
      chip.addEventListener('click', function () {
        $(button).trigger('click');
      });
      button.parentNode.insertBefore(chip, button);
      chips.push(chip);
    });

    function drawChips() {
      var chosen = preview.querySelector('figure.selected'),
        card = chosen ? chosen.closest('.sgg-fx-card') : null,
        name = card ? card.querySelector('.' + CHECKED_NAME).textContent : '';
      chips.forEach(function (chip) {
        chip.hidden = !name;
        chip.querySelector('.sgg-fx-current-name').textContent = name;
        chip.querySelector('img').setAttribute('src', photo());
      });
    }

    function onOpen() {
      $dialog.dialog('option', 'width', Math.min(1000, Math.max(320, window.innerWidth - 80)));
      paint();
      // jQuery UI focuses the first link, far down among the effects with icons: open at the chosen effect instead
      window.setTimeout(function () {
        var chosen = preview.querySelector('figure.selected'),
          card = chosen ? chosen.closest('.sgg-fx-card') : null;
        if (document.activeElement && dialog.contains(document.activeElement)) {
          document.activeElement.blur();
        }
        dialog.scrollTop = 0;
        if (card) {
          var top = card.getBoundingClientRect().top - dialog.getBoundingClientRect().top;
          dialog.scrollTop = Math.max(0, top - (dialog.clientHeight - card.offsetHeight) / 2);
        }
      }, 0);
    }
    $dialog.on('dialogopen', onOpen);
    if ($dialog.dialog('isOpen')) {
      onOpen();
    }
    // After the handler of settings.js, which marks the figure and sets the effect field
    $(document).on('click', '#effectsPreview figure', function () {
      window.setTimeout(drawChips, 0);
    });
    paint();
    drawChips();
    // The cover gets its address a moment after the editor starts
    var tries = 0;
    (function wait() {
      var head = document.querySelector('img.ggImg');
      if (head && (head.currentSrc || head.getAttribute('src'))) {
        paint();
        drawChips();
      } else if (tries++ < 40) {
        window.setTimeout(wait, 250);
      }
    })();
  }

  $(function () {
    window.setTimeout(init, 0);
  });
})(jQuery);

/**
 * Icon effects (#iconsPreview) as cards: the tiles keep their markup and handlers (settings.js); each gets a name
 * under it, a click anywhere on the card picks its effect, and the effect in use is marked.
 */
(function ($) {
  'use strict';

  function init() {
    var dialog = document.getElementById('iconsPreview'),
      field = document.getElementById('iconsEffectName');
    if (!dialog || !$.fn.dialog || !$(dialog).dialog('instance')) {
      return;
    }
    var $dialog = $(dialog),
      label = dialog.getAttribute('data-sgg-label') || 'Effect %s',
      cards = [];
    $dialog.dialog('widget').addClass('gg-pth-dialog sgg-ic-dialog');

    Array.prototype.forEach.call(dialog.querySelectorAll('.hi-icon-wrap'), function (tile) {
      if (tile.parentNode.classList.contains('sgg-ic-card')) {
        return;
      }
      var link = tile.querySelector('a.hi-icon'),
        effect = link ? link.getAttribute('data-effect') || '' : '',
        match = /hi-icon-effect-(\w+)/.exec(effect),
        card = document.createElement('div'),
        name = document.createElement('span');
      card.className = 'sgg-ic-card';
      card.setAttribute('data-effect', effect);
      name.className = 'sgg-ic-name';
      name.textContent = match ? label.replace('%s', match[1]) : effect;
      tile.parentNode.insertBefore(card, tile);
      card.appendChild(tile);
      card.appendChild(name);
      card.addEventListener('click', function (event) {
        if (link && !event.target.closest('a.hi-icon')) {
          $(link).trigger('click');
        }
      });
      cards.push(card);
    });

    function mark() {
      var value = field ? field.value : '';
      cards.forEach(function (card) {
        card.classList.toggle('is-current', card.getAttribute('data-effect') === value);
      });
    }

    $dialog.on('dialogopen', function () {
      $dialog.dialog('option', 'width', Math.min(880, Math.max(320, window.innerWidth - 80)));
      mark();
    });
    $(field).on('change', mark);
    mark();
  }

  $(function () {
    window.setTimeout(init, 0);
  });
})(jQuery);
