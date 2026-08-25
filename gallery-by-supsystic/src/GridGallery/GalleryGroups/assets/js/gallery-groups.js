(function ($) {
  'use strict';

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
      }[char];
    });
  }

  function initAjaxSelect($root) {
    if ($root.data('ggAjaxSelectReady')) {
      return;
    }
    $root.data('ggAjaxSelectReady', true);

    var selected = [];
    var inputName = $root.data('name');
    var multipleInputName = $root.data('multiple-name') || inputName;
    var ajaxUrl = $root.data('ajax-url') || window.ajaxurl;
    var action = $root.data('action') || 'sg_gallerygroups_search_galleries';
    var nonce = $root.data('nonce') || '';
    var activeOnly = $root.data('active-only') ? 1 : 0;
    var $chips = $root.find('[data-role="chips"]');
    var $input = $root.find('[data-role="search"]');
    var $results = $root.find('[data-role="results"]');
    var $hidden = $root.find('[data-role="hidden"]');
    var request = null;
    var debounce = null;

    $root.find('[data-selected-id]').each(function () {
      selected.push({
        id: String($(this).data('selected-id')),
        text: $(this).data('selected-text'),
      });
    });

    function allowsMultiple() {
      return String($root.data('multiple')) !== '0';
    }

    function renderSelected() {
      $chips.empty();
      $hidden.empty();

      selected.forEach(function (item) {
        $('<span class="gg-ajax-chip">')
          .append($('<span>').text(item.text))
          .append($('<button type="button" class="gg-ajax-chip-remove" aria-label="Remove">').attr('data-id', item.id).text('x'))
          .appendTo($chips);

        $('<input type="hidden">').attr('name', allowsMultiple() ? multipleInputName : inputName).val(item.id).appendTo($hidden);
      });
    }

    function renderResults(items) {
      $results.empty();
      if (!items.length) {
        $('<div class="gg-ajax-result gg-ajax-result-empty">').text($root.data('empty-text') || 'No results').appendTo($results);
        return;
      }

      items.forEach(function (item) {
        if (selected.some(function (selectedItem) { return String(selectedItem.id) === String(item.id); })) {
          return;
        }
        $('<button type="button" class="gg-ajax-result">')
          .attr('data-id', item.id)
          .attr('data-text', item.text)
          .html(escapeHtml(item.text))
          .appendTo($results);
      });
    }

    function search() {
      if (request) {
        request.abort();
      }
      request = $.ajax({
        url: ajaxUrl,
        method: 'GET',
        dataType: 'json',
        data: {
          action: action,
          nonce: nonce,
          term: $input.val(),
          active_only: activeOnly,
        },
      }).done(function (response) {
        renderResults(response && response.success && response.data ? response.data.items || [] : []);
      });
    }

    $input.on('focus keyup', function () {
      clearTimeout(debounce);
      debounce = setTimeout(search, 180);
    });

    $results.on('click', '.gg-ajax-result:not(.gg-ajax-result-empty)', function () {
      var item = {
        id: String($(this).data('id')),
        text: $(this).data('text'),
      };
      if (!allowsMultiple()) {
        selected = [];
      }
      selected.push(item);
      $input.val('');
      $results.empty();
      renderSelected();
    });

    $chips.on('click', '.gg-ajax-chip-remove', function () {
      var id = String($(this).data('id'));
      selected = selected.filter(function (item) {
        return String(item.id) !== id;
      });
      renderSelected();
    });

    renderSelected();
  }

  $(function () {
    $('.gg-ajax-select').each(function () {
      initAjaxSelect($(this));
    });
  });
})(jQuery);
