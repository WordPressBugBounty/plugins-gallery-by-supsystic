/**
 * Editor of a Carousel, Slider or Product carousel: every change redraws the live preview on the server with the
 * unsaved settings, Save stores them. The preview frame can be narrowed to a tablet or a phone; the slider picks its
 * device from its own width, so the frame shows exactly what a visitor gets.
 */
(function ($) {
  'use strict';

  $(function () {
    var $root = $('.sgg-sle');
    if (!$root.length) {
      return;
    }
    var $form = $('#sgg-sle-form'),
      $canvas = $root.find('.sgg-sle-canvas'),
      $status = $root.find('.sgg-sle-status'),
      $save = $root.find('[data-sgg-sle-save]'),
      galleryId = $root.data('gallery-id'),
      ajaxUrl = $root.data('ajax-url'),
      nonce = $root.data('nonce'),
      dirty = false,
      timer = null,
      request = null;

    function mountPreview() {
      var slider = $canvas.find('.sgg-sl')[0];
      if (slider && window.SggSliders) {
        window.SggSliders.mount(slider);
      }
    }

    function payload(action) {
      return $form.serialize() + '&' + $.param({ action: 'grid-gallery', _wpnonce: nonce, gallery_id: galleryId, route: { module: $root.data('module'), action: action } });
    }

    function setStatus(text, kind) {
      $status.text(text || '').attr('data-kind', kind || '');
    }

    // ---------- Fields that only matter for some values
    function valueOf(key) {
      return String($form.find('[name$="[' + key + ']"]').first().val());
    }

    // Switches: a button over a hidden field
    $form.on('click', '[data-sgg-switch]', function () {
      var $switch = $(this),
        on = $switch.attr('aria-checked') !== 'true';
      $switch.attr('aria-checked', on ? 'true' : 'false');
      $switch.prev('input[type=hidden]').val(on ? '1' : '0').trigger('change');
    });

    function syncVisibility() {
      $form.find('[data-sgg-sle-show], [data-sgg-sle-hide]').each(function () {
        var $row = $(this),
          rule = $row.attr('data-sgg-sle-show') || $row.attr('data-sgg-sle-hide'),
          parts = rule.split('='),
          match = valueOf(parts[0]) === parts[1];
        $row.toggle($row.is('[data-sgg-sle-show]') ? match : !match);
      });
    }

    // ---------- Live preview
    function refreshPreview() {
      if (request) {
        request.abort();
      }
      $root.addClass('is-updating');
      request = $.post(ajaxUrl, payload('preview'))
        .done(function (response) {
          if (response && !response.error && typeof response.html === 'string') {
            var old = $canvas.find('.sgg-sl')[0];
            if (old && window.SggSliders) {
              window.SggSliders.unmount(old);
            }
            $canvas.html(response.html);
            mountPreview();
          }
        })
        .always(function () {
          request = null;
          $root.removeClass('is-updating');
        });
    }

    $form.on('input change', 'input:not(.sgg-sle-picker-search), select', function () {
      dirty = true;
      setStatus('');
      syncVisibility();
      window.clearTimeout(timer);
      timer = window.setTimeout(refreshPreview, 350);
    });
    // selectWoo fires change on the hidden select
    $form.on('select2:select select2:unselect', 'select', function () {
      $(this).trigger('change');
    });

    // ---------- Save; first a question when the save would reset PRO settings (the free version)
    var guard = document.querySelector('[data-sgg-pg]'),
      guardPassed = !guard || typeof guard.showModal !== 'function';
    if (guard && !guardPassed) {
      guard.addEventListener('click', function (event) {
        var action = event.target.closest('[data-sgg-pg]');
        action = action && action !== guard ? action.getAttribute('data-sgg-pg') : '';
        if (action === 'confirm') {
          guardPassed = true;
          guard.close();
          $save.trigger('click');
        } else if (action === 'cancel') {
          guard.close();
        }
      });
    }

    $save.on('click', function () {
      if (!guardPassed) {
        guard.showModal();
        return;
      }
      $save.prop('disabled', true);
      setStatus('…');
      $.post(ajaxUrl, payload('save'))
        .done(function (response) {
          if (response && !response.error) {
            dirty = false;
            setStatus($root.data('msg-saved'), 'ok');
          } else {
            setStatus((response && response.message) || $root.data('msg-error'), 'error');
          }
        })
        .fail(function () {
          setStatus($root.data('msg-error'), 'error');
        })
        .always(function () {
          $save.prop('disabled', false);
        });
    });
    $(document).on('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 's') {
        event.preventDefault();
        $save.trigger('click');
      }
    });
    $(window).on('beforeunload', function () {
      if (dirty) {
        return $root.data('msg-leave');
      }
    });

    // ---------- Preview width
    // The phone keeps the real size of its screen while the window is high enough; in a lower window the whole
    // phone is scaled down to fit under the sticky preview bar (the layout inside stays 393 px wide)
    var PHONE_H = 882;
    function fitPhone() {
      var frame = $root.find('.sgg-sle-frame')[0];
      if (!frame) {
        return;
      }
      if (frame.getAttribute('data-device') !== 'm') {
        frame.style.removeProperty('--sgg-sle-k');
        return;
      }
      var preview = frame.parentNode,
        sticky = parseInt(window.getComputedStyle(preview).top, 10) || 0,
        above = frame.getBoundingClientRect().top - preview.getBoundingClientRect().top,
        room = window.innerHeight - sticky - above - 24,
        k = Math.max(0.5, Math.min(1, room / PHONE_H));
      frame.style.setProperty('--sgg-sle-k', k.toFixed(3));
    }
    $root.on('click', '[data-sgg-sle-device]', function () {
      var device = $(this).data('sgg-sle-device');
      $root.find('[data-sgg-sle-device]').removeClass('is-active');
      $(this).addClass('is-active');
      $root.find('.sgg-sle-frame').attr('data-device', device);
      fitPhone();
      // The slides in a row, the arrows and the dots follow the picked device, so the preview starts again for it
      mountPreview();
    });
    $(window).on('resize', fitPhone);

    // The phone's clock shows the site's time (its time zone offset comes with the page) and keeps going
    var clock = $root.find('[data-sgg-sle-clock]')[0];
    if (clock) {
      var offset = (parseInt(clock.getAttribute('data-sgg-sle-clock'), 10) || 0) * 1000,
        twelve = clock.getAttribute('data-twelve') === '1';
      var tickClock = function () {
        var now = new Date(Date.now() + offset),
          h = now.getUTCHours(),
          m = now.getUTCMinutes();
        if (twelve) {
          h = h % 12 || 12;
        }
        clock.textContent = h + ':' + (m < 10 ? '0' : '') + m;
      };
      tickClock();
      window.setInterval(tickClock, 10000);
    }

    // ---------- Shortcode
    $root.on('click', '[data-sgg-sle-copy]', function () {
      var input = document.getElementById('sgg-sle-shortcode');
      input.select();
      try {
        navigator.clipboard.writeText(input.value);
      } catch (e) {
        document.execCommand('copy');
      }
      setStatus($(this).text().trim() + ' ✓', 'ok');
    });

    // ---------- Pickers of posts and categories: a search on the site through its REST API
    var restUrl = String($root.data('rest-url') || ''),
      restNonce = $root.data('rest-nonce'),
      searchTimer = null;

    function restGet(path, query) {
      return $.ajax({
        url: restUrl + path + (restUrl.indexOf('?') === -1 ? '?' : '&') + $.param(query),
        headers: { 'X-WP-Nonce': restNonce },
        dataType: 'json',
      });
    }

    function pickerIds($picker) {
      return $picker.find('.sgg-sle-chip').map(function () {
        return String($(this).data('id'));
      }).get();
    }

    function pickerSave($picker) {
      $picker.find('input[type=hidden]').val(pickerIds($picker).join(',')).trigger('change');
    }

    function pickerSearch($picker, term) {
      var $list = $picker.find('.sgg-sle-picker-list'),
        kind = $picker.data('sgg-picker'),
        request = kind === 'cats'
          ? restGet('wp/v2/categories', { search: term, per_page: 20, _fields: 'id,name' })
          : restGet('wp/v2/search', { search: term, per_page: 20, type: 'post', subtype: valueOf('postType') || 'post' });
      request.done(function (items) {
        var taken = pickerIds($picker);
        $list.empty();
        (items || []).forEach(function (item) {
          var id = String(item.id);
          if (taken.indexOf(id) !== -1) {
            return;
          }
          $('<button type="button" role="option" class="sgg-sle-picker-option"></button>')
            .attr('data-id', id)
            .text($('<textarea>').html(item.name || item.title || '#' + id).text())
            .appendTo($list);
        });
        $list.prop('hidden', !$list.children().length);
      });
    }

    $form.on('input', '.sgg-sle-picker-search', function () {
      var $search = $(this),
        $picker = $search.closest('[data-sgg-picker]'),
        term = $search.val().trim();
      window.clearTimeout(searchTimer);
      if (term.length < 2) {
        $picker.find('.sgg-sle-picker-list').prop('hidden', true).empty();
        return;
      }
      searchTimer = window.setTimeout(function () {
        pickerSearch($picker, term);
      }, 250);
    });
    $form.on('keydown', '.sgg-sle-picker-search', function (event) {
      var $picker = $(this).closest('[data-sgg-picker]');
      if (event.key === 'Enter') {
        event.preventDefault();
        $picker.find('.sgg-sle-picker-option').first().trigger('click');
      } else if (event.key === 'Escape') {
        $picker.find('.sgg-sle-picker-list').prop('hidden', true);
      }
    });
    $form.on('click', '.sgg-sle-picker-option', function () {
      var $option = $(this),
        $picker = $option.closest('[data-sgg-picker]'),
        $chip = $('<span class="sgg-sle-chip"><span></span><button type="button" data-sgg-chip-remove>&times;</button></span>');
      $chip.attr('data-id', $option.data('id')).children('span').text($option.text());
      $chip.children('button').attr('aria-label', $picker.find('[data-sgg-chip-remove]').first().attr('aria-label') || '×');
      $picker.find('.sgg-sle-chips').append($chip);
      $picker.find('.sgg-sle-picker-search').val('').trigger('focus');
      $picker.find('.sgg-sle-picker-list').prop('hidden', true).empty();
      pickerSave($picker);
    });
    $form.on('click', '[data-sgg-chip-remove]', function () {
      var $picker = $(this).closest('[data-sgg-picker]');
      $(this).closest('.sgg-sle-chip').remove();
      pickerSave($picker);
    });
    $(document).on('click', function (event) {
      if (!$(event.target).closest('[data-sgg-picker]').length) {
        $form.find('.sgg-sle-picker-list').prop('hidden', true);
      }
    });

    // WooCommerce product and category pickers
    $(document.body).trigger('wc-enhanced-select-init');

    syncVisibility();
    mountPreview();
  });
})(jQuery);
