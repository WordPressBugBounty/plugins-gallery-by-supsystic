(function ($) {
  $(document).ready(function () {
    var $section = $('#gg-galleries'),
      $tbody = $('#gg-galleries-tbody'),
      $table = $('#galleries'),
      state = {
        page: parseInt($section.data('page'), 10) || 1,
        perPage: parseInt($section.data('per-page'), 10) || 20,
        sort: $section.data('sort') || 'id',
        dir: $section.data('dir') || 'desc',
        recordsTotal: parseInt($section.data('records-total'), 10) || 0,
        search: '',
      },
      searchTimer = null;

    function updateSortHeaders() {
      $table.find('.gg-sortable').each(function () {
        var $th = $(this),
          key = $th.data('sort-key'),
          $icon = $th.find('.gg-sort-icon');

        $th.removeClass('gg-sort-active');
        $icon.removeClass('fa-sort-asc fa-sort-desc').addClass('fa-sort');

        if (key === state.sort) {
          $th.addClass('gg-sort-active');
          $icon.removeClass('fa-sort').addClass(state.dir === 'asc' ? 'fa-sort-asc' : 'fa-sort-desc');
        }
      });
    }

    function updatePagination() {
      var totalPages = Math.max(1, Math.ceil(state.recordsTotal / state.perPage)),
        from = state.recordsTotal === 0 ? 0 : (state.page - 1) * state.perPage + 1,
        to = Math.min(state.page * state.perPage, state.recordsTotal),
        $numbers = $('#gg-page-numbers').empty();

      $('#gg-pagination-info').text(from + '–' + to + ' / ' + state.recordsTotal);

      for (var p = 1; p <= totalPages; p++) {
        $('<button type="button" class="gg-page-number"></button>')
          .text(p)
          .toggleClass('gg-page-active', p === state.page)
          .attr('data-page', p)
          .appendTo($numbers);
      }

      $('.gg-page-btn[data-page-action="prev"]').prop('disabled', state.page <= 1);
      $('.gg-page-btn[data-page-action="next"]').prop('disabled', state.page >= totalPages);
    }

    function resetSelection() {
      $('#gg-check-all').prop('checked', false);
      $('#delete-group').attr('disabled', 'disabled');
    }

    function fetchAndRender() {
      $tbody.css('opacity', 0.5);

      $.post(window.wp.ajax.settings.url, {
        action: 'grid-gallery',
        _wpnonce: SupsysticGallery.nonce,
        route: {
          module: 'galleries',
          action: 'galleriesData',
        },
        page: state.page,
        perPage: state.perPage,
        sort: state.sort,
        dir: state.dir,
        search: state.search,
      })
        .done(function (response) {
          state.recordsTotal = response.recordsTotal;

          if (!response.html && state.page > 1) {
            state.page -= 1;
            fetchAndRender();
            return;
          }

          $tbody.html(response.html);
          updateSortHeaders();
          updatePagination();
          resetSelection();
        })
        .fail(function (xhr) {
          window.console && console.error('Failed to load galleries list', xhr.status, xhr.responseText);
        })
        .always(function () {
          $tbody.css('opacity', 1);
        });
    }

    $table.on('click', '.gg-sortable', function () {
      var key = $(this).data('sort-key');

      if (state.sort === key) {
        state.dir = state.dir === 'asc' ? 'desc' : 'asc';
      } else {
        state.sort = key;
        state.dir = 'asc';
      }
      state.page = 1;
      fetchAndRender();
    });

    $section.on('click', '.gg-page-btn', function () {
      var action = $(this).data('page-action'),
        totalPages = Math.max(1, Math.ceil(state.recordsTotal / state.perPage));

      if (action === 'prev' && state.page > 1) {
        state.page -= 1;
        fetchAndRender();
      } else if (action === 'next' && state.page < totalPages) {
        state.page += 1;
        fetchAndRender();
      }
    });

    $section.on('click', '.gg-page-number', function () {
      state.page = parseInt($(this).data('page'), 10) || 1;
      fetchAndRender();
    });

    $('#gg-per-page').on('change', function () {
      state.perPage = parseInt($(this).val(), 10) || 20;
      state.page = 1;
      fetchAndRender();
    });

    $('#gg-galleries-search').on('input', function () {
      var value = $(this).val();

      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        state.search = value;
        state.page = 1;
        fetchAndRender();
      }, 400);
    });

    $('#gg-check-all').on('change', function () {
      var checked = $(this).is(':checked');
      $tbody.find('.gg-row-checkbox').prop('checked', checked);
      updateDeleteGroupButton();
    });

    $(document).on('change', '.gg-row-checkbox', function () {
      if (!$(this).is(':checked')) {
        $('#gg-check-all').prop('checked', false);
      }
      updateDeleteGroupButton();
    });

    function updateDeleteGroupButton() {
      var anyChecked = $tbody.find('.gg-row-checkbox:checked').length > 0;
      $('#delete-group').prop('disabled', !anyChecked);
    }

    $('#delete-group').on('click', function () {
      if (!confirm('Are you sure?')) {
        return;
      }

      var ids = $tbody
        .find('.gg-row-checkbox:checked')
        .map(function () {
          return parseInt($(this).data('gallery-id'), 10);
        })
        .get();

      if (!ids.length) {
        return;
      }

      $.post(window.wp.ajax.settings.url, {
        action: 'grid-gallery',
        _wpnonce: SupsysticGallery.nonce,
        route: {
          module: 'galleries',
          action: 'deleteGroup',
        },
        gallery_ids: ids,
      })
        .done(function () {
          fetchAndRender();
        })
        .fail(function (error) {
          alert(error);
        });

      return false;
    });

    $(document).on('click', '.shortcode, .phpcode', function () {
      $(this).select();
    });

    $(document).on('click', '.gg-clone-gallery', function () {
      var $btn = $(this),
        galleryId = parseInt($btn.data('gallery-id'), 10);

      if (!galleryId || $btn.prop('disabled')) {
        return;
      }

      $btn.prop('disabled', true);

      $.post(window.wp.ajax.settings.url, {
        action: 'grid-gallery',
        _wpnonce: SupsysticGallery.nonce,
        route: {
          module: 'galleries',
          action: 'clone',
          gallery_id: galleryId,
          clone_type: 1,
        },
      })
        .done(function (response) {
          if (response && (response.isError || !response.newGalleryId)) {
            alert((response && response.message) || 'Gallery clone error');
            return;
          }
          fetchAndRender();
        })
        .fail(function () {
          alert('Gallery clone error');
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });

    updateSortHeaders();
    updatePagination();
  });
})(window.jQuery);
