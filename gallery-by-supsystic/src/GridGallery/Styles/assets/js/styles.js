/**
 * Style library window of the gallery editors. A style is a set of setting values; a click puts them into the
 * settings form (and the hidden theme field of the Carousel and Slider editors), so the live preview shows the style
 * and Save keeps it. Undo brings back the values the form had before. A PRO style opens the PRO window while PRO is
 * not active.
 */
(function ($) {
  'use strict';

  function parse(text) {
    try {
      return JSON.parse(text) || [];
    } catch (e) {
      return [];
    }
  }

  $(function () {
    var dialog = document.querySelector('[data-sgg-st]');
    if (!dialog || typeof dialog.showModal !== 'function') {
      return;
    }
    var styles = parse(dialog.getAttribute('data-styles')),
      type = dialog.getAttribute('data-type'),
      prefix = dialog.getAttribute('data-prefix'),
      grid = dialog.querySelector('[data-sgg-st-grid]'),
      empty = dialog.querySelector('[data-sgg-st-empty]'),
      search = dialog.querySelector('[data-sgg-st-search]'),
      collectionSelect = dialog.querySelector('[data-sgg-st-collection]'),
      themeField = prefix ? document.querySelector('[name="' + prefix + '[style][theme]"]') : null,
      proOpen = dialog.getAttribute('data-pro-open') === '1',
      tier = 'all',
      undo = null;

    // ---------- Form fields of a setting path, such as style.accent or thumbnail.border.radius
    function fieldName(path) {
      var parts = path.split('.');
      if (prefix) {
        return prefix + '[' + parts.join('][') + ']';
      }
      return parts[0] + (parts.length > 1 ? '[' + parts.slice(1).join('][') + ']' : '');
    }

    function fieldsOf(path) {
      return document.querySelectorAll('[name="' + fieldName(path) + '"]');
    }

    function read(path) {
      var fields = fieldsOf(path);
      if (!fields.length) {
        return null;
      }
      if (fields[0].type === 'radio') {
        for (var i = 0; i < fields.length; i++) {
          if (fields[i].checked) {
            return fields[i].value;
          }
        }
        return null;
      }
      return fields[0].value;
    }

    function write(path, value) {
      var fields = fieldsOf(path);
      value = String(value);
      if (!fields.length) {
        return;
      }
      if (fields[0].type === 'radio') {
        Array.prototype.forEach.call(fields, function (radio) {
          if (radio.value !== value || radio.checked) {
            return;
          }
          // The plugin pages draw radios with iCheck; its own handlers listen to the click
          if ($.fn.iCheck && $(radio).closest('[class*="iradio"]').length) {
            $(radio).iCheck('check');
          } else {
            radio.checked = true;
          }
          $(radio).trigger('click').trigger('change');
        });
        return;
      }
      var field = fields[0];
      if (field.tagName === 'SELECT' && !Array.prototype.some.call(field.options, function (option) {
        return option.value === value;
      })) {
        return;
      }
      if (field.value === value) {
        return;
      }
      field.value = value;
      $(field).trigger('input').trigger('change');
      // A switch of the slider editors is a button over a hidden field
      var toggle = field.nextElementSibling;
      if (toggle && toggle.hasAttribute('data-sgg-switch')) {
        toggle.setAttribute('aria-checked', value === '1' ? 'true' : 'false');
      }
    }

    function announce(text, canUndo) {
      Array.prototype.forEach.call(document.querySelectorAll('[data-sgg-st-status]'), function (status) {
        status.textContent = text;
      });
      Array.prototype.forEach.call(document.querySelectorAll('[data-sgg-st-undo]'), function (button) {
        button.hidden = !canUndo;
      });
      Array.prototype.forEach.call(document.querySelectorAll('[data-sgg-st-current]'), function (label) {
        var style = findStyle(themeField ? themeField.value : '');
        label.textContent = style ? style.name : label.getAttribute('data-sgg-st-current');
      });
    }

    function findStyle(id) {
      for (var i = 0; i < styles.length; i++) {
        if (styles[i].id === id) {
          return styles[i];
        }
      }
      return null;
    }

    function apply(style) {
      var before = {};
      Object.keys(style.values).forEach(function (path) {
        before[path] = read(path);
      });
      undo = { values: before, theme: themeField ? themeField.value : '' };
      Object.keys(style.values).forEach(function (path) {
        write(path, style.values[path]);
      });
      if (themeField) {
        themeField.value = style.id;
        $(themeField).trigger('change');
      }
      announce(dialog.getAttribute('data-msg-applied').replace('%s', style.name), true);
    }

    function restore() {
      if (!undo) {
        return;
      }
      Object.keys(undo.values).forEach(function (path) {
        if (undo.values[path] !== null) {
          write(path, undo.values[path]);
        }
      });
      if (themeField) {
        themeField.value = undo.theme;
        $(themeField).trigger('change');
      }
      undo = null;
      announce(dialog.getAttribute('data-msg-undone'), false);
    }

    // ---------- Cards: a small picture of this gallery's layout in the colors and shape of the style
    // The layout the form has now: masonry, justified rows, a logo line, products...
    function goal() {
      if (type === 'gallery') {
        var grid = read('area.grid');
        if (read('thumbnail.border.radius') === '100' && read('thumbnail.border.radius_unit') === '1') {
          return 'rounded';
        }
        return grid === '1' ? 'masonry' : grid === '4' ? 'mosaic' : grid === '2' && read('area.justified') === '1' ? 'justified' : 'grid';
      }
      var source = read('content.source');
      if (type === 'carousel') {
        return source === 'products' ? 'products' : source === 'posts' ? 'posts_carousel' : read('motion.marquee') === '1' ? 'logos' : 'carousel';
      }
      return source === 'posts' ? 'posts_slider' : read('layout.heightMode') === 'viewport' ? 'hero' : 'slider';
    }

    function mock(style, layout) {
      if (!window.SggStyleMock) {
        return '';
      }
      return window.SggStyleMock.html(type, style.swatch, $.extend({}, style.shape), { goal: layout });
    }

    function card(style, layout) {
      var button = document.createElement('button'),
        swatch = style.swatch,
        current = themeField && themeField.value === style.id;
      button.type = 'button';
      button.className = 'sgg-st-card' + (style.locked ? ' is-locked' : '') + (current ? ' is-current' : '');
      button.setAttribute('data-id', style.id);
      button.setAttribute('aria-pressed', current ? 'true' : 'false');
      button.style.cssText = [
        '--h:' + swatch.head,
        '--ht:' + swatch.headText,
        '--sf:' + swatch.surface,
        '--tx:' + swatch.text,
        '--sp:' + swatch.stripe,
        '--ac:' + swatch.accent,
        '--bd:' + swatch.border,
        '--r:' + Math.min(14, parseInt(swatch.radius, 10) || 0) + 'px',
      ].join(';');
      button.innerHTML = mock(style, layout);
      var label = document.createElement('span'),
        name = document.createElement('strong'),
        tag = document.createElement('em'),
        collection = document.createElement('small');
      label.className = 'sgg-st-label';
      name.textContent = style.name;
      tag.className = style.pro ? 'is-pro' : 'is-free';
      tag.textContent = style.pro ? 'PRO' : 'FREE';
      collection.textContent = style.collection;
      label.appendChild(name);
      // With PRO active every style is open: no FREE / PRO labels
      if (!proOpen) {
        label.appendChild(tag);
      }
      button.appendChild(label);
      button.appendChild(collection);
      return button;
    }

    function render() {
      var query = (search.value || '').trim().toLowerCase(),
        collection = collectionSelect.value,
        shown = 0,
        layout = goal();
      grid.textContent = '';
      styles.forEach(function (style) {
        if (tier === 'free' && style.pro) {
          return;
        }
        if (tier === 'pro' && !style.pro) {
          return;
        }
        if (collection && style.collection !== collection) {
          return;
        }
        if (query && (style.name + ' ' + style.collection).toLowerCase().indexOf(query) === -1) {
          return;
        }
        grid.appendChild(card(style, layout));
        shown++;
      });
      empty.hidden = shown > 0;
    }

    // ---------- Events
    $(document).on('click', '[data-sgg-st-open]', function (event) {
      event.preventDefault();
      render();
      dialog.showModal();
      var current = grid.querySelector('.is-current');
      if (current) {
        current.scrollIntoView({ block: 'center' });
      }
    });
    $(document).on('click', '[data-sgg-st-undo]', function (event) {
      event.preventDefault();
      restore();
    });

    dialog.addEventListener('click', function (event) {
      var target = event.target,
        el;
      if (target === dialog || target.closest('[data-sgg-st-close]')) {
        dialog.close();
        return;
      }
      if ((el = target.closest('[data-sgg-st-tier]'))) {
        tier = el.getAttribute('data-sgg-st-tier');
        Array.prototype.forEach.call(dialog.querySelectorAll('[data-sgg-st-tier]'), function (button) {
          button.setAttribute('aria-pressed', button === el ? 'true' : 'false');
        });
        render();
        return;
      }
      if ((el = target.closest('.sgg-st-card'))) {
        var style = findStyle(el.getAttribute('data-id'));
        if (!style) {
          return;
        }
        if (style.locked) {
          if (window.SggUpsell) {
            window.SggUpsell.open('styles');
          }
          return;
        }
        apply(style);
        dialog.close();
      }
    });
    search.addEventListener('input', render);
    collectionSelect.addEventListener('change', render);
    announce('', false);
  });
})(jQuery);
