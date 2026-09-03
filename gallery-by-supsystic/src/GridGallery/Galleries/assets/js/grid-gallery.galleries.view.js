/*global jQuery, ajaxurl*/

(function (app, $) {
  $(document).ready(function () {
    var qs = new URI().query(true);

    if (qs.module !== 'galleries' || qs.action !== 'view') {
      return;
    }

    var $section = $('#gg-tile-grid'),
      $grid = $section.length ? $section : null;

    if (!$grid) {
      return;
    }

    var state = {
        galleryId: parseInt($section.data('gallery-id'), 10),
        page: parseInt($section.data('page'), 10) || 1,
        perPage: $section.data('per-page') === 'all' ? 'all' : parseInt($section.data('per-page'), 10) || 100,
        sort: $section.data('sort') || 'position',
        dir: $section.data('dir') || 'asc',
        recordsTotal: parseInt($section.data('records-total'), 10) || 0,
      },
      TILE_SIZES = { s: 110, m: 160, l: 210, xl: 280 },
      TILE_SIZE_KEY = 'ggImagesTileSize';

    function $list() {
      return $grid.find('.gg-tile-grid-list');
    }

    // ---- Pagination (mirrors the Galleries list page's pagination JS) ----

    function updatePagination() {
      var perPageNum = state.perPage === 'all' ? Math.max(state.recordsTotal, 1) : state.perPage,
        totalPages = Math.max(1, Math.ceil(state.recordsTotal / perPageNum)),
        from = state.recordsTotal === 0 ? 0 : (state.page - 1) * perPageNum + 1,
        to = Math.min(state.page * perPageNum, state.recordsTotal),
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

    $(document).on('click', '.gg-page-btn', function () {
      var action = $(this).data('page-action'),
        perPageNum = state.perPage === 'all' ? Math.max(state.recordsTotal, 1) : state.perPage,
        totalPages = Math.max(1, Math.ceil(state.recordsTotal / perPageNum));

      if (action === 'prev' && state.page > 1) {
        state.page -= 1;
        fetchAndRender();
      } else if (action === 'next' && state.page < totalPages) {
        state.page += 1;
        fetchAndRender();
      }
    });

    $(document).on('click', '.gg-page-number', function () {
      state.page = parseInt($(this).data('page'), 10) || 1;
      fetchAndRender();
    });

    $('#gg-per-page').on('change', function () {
      state.perPage = $(this).val() === 'all' ? 'all' : parseInt($(this).val(), 10) || 100;
      state.page = 1;
      fetchAndRender();
    });

    // ---- Fetch & render (SSP) ----

    function fetchAndRender(onDone) {
      $list().css('opacity', 0.5);

      $.post(window.wp.ajax.settings.url, {
        action: 'grid-gallery',
        _wpnonce: SupsysticGallery.nonce,
        route: { module: 'galleries', action: 'photosData' },
        gallery_id: state.galleryId,
        page: state.page,
        perPage: state.perPage,
        sort: state.sort,
        dir: state.dir,
      })
        .done(function (response) {
          state.recordsTotal = response.recordsTotal;
          state.sort = response.sort || state.sort;
          state.dir = response.dir || state.dir;

          if (!response.html && state.page > 1) {
            state.page -= 1;
            fetchAndRender(onDone);
            return;
          }

          $list().replaceWith(response.html);
          resetSelection();
          updatePagination();
          applyTileSize();
          initTileBehaviors();
          if (onDone) {
            onDone();
          }
        })
        .fail(function (xhr) {
          window.console && console.error('Failed to load images', xhr.status, xhr.responseText);
        })
        .always(function () {
          $list().css('opacity', 1);
        });
    }

    // ---- Checkboxes / Apply button state ----

    function getCheckedTiles() {
      // Not scoped to $list(): in category mode the checked tile may be a
      // clone living in #gg-tile-categories. A photo can have more than one
      // on-screen instance at once (its hidden original in the main list,
      // plus a clone per category it belongs to) - dedupe by photo id so a
      // bulk action doesn't process the same photo twice.
      var seen = {},
        result = [];
      $('.gg-tile input[data-observable]:checked')
        .closest('.gg-tile')
        .each(function () {
          var id = $(this).data('entity-info').id;
          if (!seen[id]) {
            seen[id] = true;
            result.push(this);
          }
        });
      return $(result);
    }

    function resetSelection() {
      updateApplyButton();
    }

    function updateApplyButton() {
      $('[data-button="checkedbtn"]').prop('disabled', getCheckedTiles().length === 0);
    }

    $(document).on('change', '.gg-tile input[data-observable]', updateApplyButton);

    // ---- Search (client-side filter of the currently-rendered page) ----

    function filterImages() {
      var value = ($('#find-by-caption').val() || '').toLowerCase();

      $list()
        .find('.gg-tile')
        .each(function () {
          var $tile = $(this),
            info = $tile.data('entity-info'),
            caption = (info && info.attachment && (info.attachment.caption || info.attachment.title)) || '';

          $tile.toggle(caption.toLowerCase().indexOf(value) !== -1);
        });
    }

    $('#find-by-caption').on('keyup', filterImages);

    // ---- Sort by / direction ----

    $('select[name="sortby"]').on('change', function () {
      var $sortTo = $('#sortToLi'),
        value = $(this).val();

      $sortTo.toggle(value !== 'randomly');
      state.sort = value;
      state.page = 1;
      fetchAndRender();
    });

    $('[data-button="sortbtn"]').on('click', function () {
      var $icon = $(this).find('.fa');
      state.dir = state.dir === 'asc' ? 'desc' : 'asc';
      $icon.toggleClass('fa-arrow-up fa-arrow-down');
      fetchAndRender();
    });

    // ---- Tile size ----

    function applyTileSize(size) {
      size = size || localStorage.getItem(TILE_SIZE_KEY) || 'm';
      if (!TILE_SIZES[size]) {
        size = 'm';
      }
      $grid[0].style.setProperty('--gg-tile-size', TILE_SIZES[size] + 'px');
      $('.gg-tile-size-btn').removeClass('gg-tile-size-active');
      $('.gg-tile-size-btn[data-tile-size="' + size + '"]').addClass('gg-tile-size-active');
      localStorage.setItem(TILE_SIZE_KEY, size);
    }

    $('.gg-tile-size-btn').on('click', function () {
      applyTileSize($(this).data('tile-size'));
    });

    // ---- Drag & drop reorder (auto-saves on drop, whole tile is the drag surface) ----

    // Position is only what's actually displayed when Sort By is "Position" -
    // any other sort (Name, Date, ...) re-sorts by that field on every fetch,
    // silently hiding a successful position save behind the active sort.
    // Dragging clearly means "show it in this order", so switch Sort By to
    // Position for the user rather than leaving the reorder looking like it
    // didn't take effect.
    function ensureSortByPosition() {
      if (state.sort === 'position') {
        return;
      }
      state.sort = 'position';
      state.dir = 'asc';
      $('select[name="sortby"]').val('position');
      $('#sortToLi').show().find('.fa').removeClass('fa-arrow-down').addClass('fa-arrow-up');
      app.Ajax.Post({ module: 'galleries', action: 'saveSortBy' }, { gallery_id: state.galleryId, sortby: 'position', sortto: 'asc' }).send(function () {});
      $.jGrowl('Sort By switched to Position so your new order is visible.');
    }

    function initSortable() {
      var $l = $list();
      if (!$l.length || typeof $l.sortable !== 'function') {
        return;
      }
      $l.sortable({
        cancel: '.gg-tile-flyout, input, button, a, select, label',
        tolerance: 'pointer',
        stop: function () {
          ensureSortByPosition();
          if (app.PositionCtrl) {
            app.PositionCtrl.updatePosition();
          }
        },
      });
    }

    // ---- Colorbox / lazyload re-init for AJAX-injected tiles ----

    function initColorbox() {
      var $links = $list().find('[data-colorbox]');
      if ($links.length && typeof $links.colorbox === 'function') {
        $links.colorbox({ rel: 'grid-gallery-tiles', fixed: true, maxHeight: '90%', innerHeight: '90%', scrolling: false });
      }
    }

    function initLazyload() {
      $list()
        .find('img.supsystic-lazy')
        .each(function () {
          var $img = $(this),
            src = $img.data('original');
          if (src && $img.attr('src') !== src) {
            $img.attr('src', src);
          }
        });
    }

    function initTileBehaviors() {
      initSortable();
      initColorbox();
      initLazyload();
      applyAllFilledIndicators();
    }

    // ---- Shared option dialogs ----

    var $dialogs = {};

    // Some of these (Meta/Attributes/Linked Images/Effect) may already be
    // initialized with their own width/buttons by photos.js - only apply
    // these defaults when nothing has claimed the element yet.
    function isDialogInitialized($el) {
      return $el.hasClass('ui-dialog-content');
    }

    function dialog(id, options) {
      if (!$dialogs[id]) {
        var $el = $('#' + id);
        if ($el.length && typeof $el.dialog === 'function' && !isDialogInitialized($el)) {
          $el.dialog($.extend({ autoOpen: false, modal: true, width: 480, maxWidth: '90%', maxHeight: '85%' }, options));
        }
        $dialogs[id] = $el;
      }
      return $dialogs[id];
    }

    function currentTileFromEvent(event) {
      return $(event.currentTarget).closest('.gg-tile, [data-entity]');
    }

    // The server exposes a few attachment fields under a different key than
    // the one the save dialogs POST them as (e.g. 'link' is reserved by
    // WordPress, so the server stores/returns it as 'external_link').
    var ATTACHMENT_FIELD_ALIASES = { link: 'external_link', hoverCaptionImageInp: 'hoverCaptionImage' },
      NON_ATTACHMENT_FIELDS = { attachment_id: true, gallery_id: true, image_id: true, replace_attachment_id: true };

    // Keeps the tile's cached entity-info in sync with what was just saved,
    // so reopening the same option's dialog without a full page reload shows
    // the just-saved value instead of stale (pre-edit) data.
    function mergeAttachmentFields(info, fields) {
      $.each(fields, function (key, value) {
        if (NON_ATTACHMENT_FIELDS[key]) {
          return;
        }
        info.attachment[ATTACHMENT_FIELD_ALIASES[key] || key] = value;
      });
    }

    function updateAttachment($tile, fields, message, onDone) {
      var info = $tile.data('entity-info'),
        post = app.Ajax.Post(
          { module: 'photos', action: 'updateAttachment' },
          $.extend({ attachment_id: info.attachment.id, gallery_id: state.galleryId }, fields)
        );
      app.Loader.show('Saving...');
      post.send(function (response) {
        app.Loader.hide();
        $.jGrowl(message || 'Information updated.');
        mergeAttachmentFields(info, fields);
        applyFilledIndicators($tile);
        if (onDone) {
          onDone(response);
        }
      });
    }

    // ---- Filled-option indicators (highlight submenu items that already have a value) ----

    var FILLED_CHECKS = {
      caption: function (info) {
        return !!(info.attachment.caption || info.attachment.captionDescription);
      },
      seo: function (info) {
        return !!(info.attachment.alt || info.attachment.description);
      },
      effect: function (info) {
        return !!(info.attachment.captionEffect && info.attachment.captionEffect !== 'none');
      },
      attributes: function (info) {
        return !!(info.attributes && info.attributes.length);
      },
      linked: function (info) {
        return !!info.attachment.linkedImages;
      },
      hover: function (info) {
        return !!info.attachment.hoverCaptionImage;
      },
      categories: function (info) {
        return !!(info.tags && info.tags.length);
      },
      video: function (info) {
        return !!info.attachment.video;
      },
      link: function (info) {
        return !!info.attachment.external_link;
      },
      ecommerce: function (info) {
        return !!info.ecommerceItem;
      },
    };

    function applyFilledIndicators($tile) {
      var info = $tile.data('entity-info');
      if (!info) {
        return;
      }
      $tile.find('> .gg-tile-inner .gg-tile-flyout [data-gg-option]').each(function () {
        var $btn = $(this),
          check = FILLED_CHECKS[$btn.data('gg-option')];
        $btn.toggleClass('gg-tile-option-filled', !!(check && check(info)));
      });
    }

    function applyAllFilledIndicators() {
      $list()
        .find('.gg-tile')
        .each(function () {
          applyFilledIndicators($(this));
        });
    }

    // Caption
    function openCaptionDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = dialog('ggImageCaptionDialog');
      $d.find('textarea[name="caption"]').val(info.attachment.caption || '');
      $d.find('textarea[name="captionDescription"]').val(info.attachment.captionDescription || '');
      $d.data('tile', $tile).dialog('open');
    }

    // SEO
    function openSeoDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = dialog('ggImageSeoDialog');
      $d.find('textarea[name="alt"]').val(info.attachment.alt || info.attachment.title || '');
      $d.find('textarea[name="description"]').val(info.attachment.description || '');
      $d.data('tile', $tile).dialog('open');
    }

    // Link
    function openLinkDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = dialog('ggImageLinkDialog'),
        rel = info.attachment.rel || [];
      $d.find('input[name="link"]').val(info.attachment.external_link || '');
      $d.find('input[name="target"]').prop('checked', info.attachment.target === '_blank');
      $d.find('input[name="rel[]"]').each(function () {
        $(this).prop('checked', rel.indexOf($(this).val()) !== -1);
      });
      $d.data('tile', $tile).dialog('open');
    }

    // Video (Pro)
    function openVideoDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = dialog('ggImageVideoDialog');
      $d.find('input[name="video"]').val(info.attachment.video || '');
      $d.data('tile', $tile).dialog('open');
    }

    // Rotate
    function openRotateDialog($tile) {
      dialog('ggImageRotateDialog').data('tile', $tile).dialog('open');
    }

    function applyRotate($tile) {
      var rotateType = dialog('ggImageRotateDialog').find('#ggRotateSelect').val(),
        info = $tile.data('entity-info'),
        post = app.Ajax.Post({ module: 'photos', action: 'rotatePhoto' }, { rotateType: rotateType, gallery_id: state.galleryId });
      post.add('ids', [info.id]);
      app.Loader.show('Rotating...');
      post.send(function (response) {
        app.Loader.hide();
        if (!response.error) {
          var $img = $tile.find('img.gg-tile-thumb'),
            src = ($img.attr('src') || $img.data('original')).split('?')[0] + '?' + Math.random();
          $img.attr('src', src).data('original', src);
        }
        $.jGrowl(response.message);
      });
    }

    // Crop
    function openCropDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = dialog('ggImageCropDialog');
      $d.find('#ggCropPositionSelect').val(info.attachment.cropPosition || 'center-center');
      $d.data('tile', $tile).dialog('open');
    }

    // Copy / Move (shared transfer dialog)
    function openTransferDialog($tile, mode) {
      var $d = dialog('ggImageTransferDialog');
      $d.dialog('option', 'title', mode === 'move' ? 'Move to' : 'Copy to');
      $d.data({ tile: $tile, mode: mode }).dialog('open');
    }

    function applyTransfer() {
      var $d = dialog('ggImageTransferDialog'),
        $tile = $d.data('tile'),
        mode = $d.data('mode'),
        targetGalleryId = $d.find('#ggTransferGallerySelect').val(),
        info = $tile.data('entity-info');

      if (!targetGalleryId) {
        return;
      }

      var post = app.Ajax.Post(
        { module: 'photos', action: 'add' },
        { attachment_id: info.attachment.id, galleryId: targetGalleryId, view_type: 'list', attachType: 'gallery', save_exif_data: '1' }
      );
      app.Loader.show(mode === 'move' ? 'Moving...' : 'Copying...');
      post.send(function (response) {
        app.Loader.hide();
        $.jGrowl(response.message);
        if (!response.error && mode === 'move') {
          removeTiles([$tile], [info.id]);
        }
      });
    }

    // Delete
    function removeTiles($tiles, ids) {
      var post = app.Ajax.Post({ module: 'galleries', action: 'deleteResource' }, { gallery_id: state.galleryId });
      post.add('ids', ids);
      app.Loader.show('Deleting...');
      post.send(function (response) {
        app.Loader.hide();
        if (!response.error) {
          $.each($tiles, function (i, $t) {
            $t.remove();
          });
          state.recordsTotal = Math.max(0, state.recordsTotal - ids.length);
          if ($list().find('.gg-tile').length === 0 && state.page > 1) {
            state.page -= 1;
            fetchAndRender();
          } else {
            updatePagination();
            updateApplyButton();
          }
        }
        $.jGrowl(response.message);
      });
    }

    function deleteTile($tile) {
      if (!confirm($('#checkedDoLi').data('delete-confirm'))) {
        return;
      }
      var info = $tile.data('entity-info');
      removeTiles([$tile], [info.id]);
    }

    // Replace
    function openReplace($tile) {
      var info = $tile.data('entity-info'),
        frame = window.wp.media({ title: 'Replace image', button: { text: 'Replace' }, multiple: false });

      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        updateAttachment($tile, { image_id: info.id, replace_attachment_id: attachment.id }, 'Image replaced.', function () {
          fetchAndRender();
        });
      });
      frame.open();
    }

    // Meta (read-only, from data-entity-info)
    function openMetaDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = dialog('ggImageMetaDialog'),
        $list = $d.find('.image-meta-list').empty();

      $.each(
        {
          Filename: info.attachment.filename,
          Filesize: info.attachment.filesizeHumanReadable,
          Dimensions: info.attachment.width + 'x' + info.attachment.height,
          Uploaded: info.gg_wp_upload_date,
        },
        function (label, value) {
          $list.append($('<p></p>').append($('<strong></strong>').text(label + ': ')).append(document.createTextNode(value || '')));
        }
      );
      $d.dialog('open');
    }

    // Categories (Pro) - comma-separated, saves per Apply via allImageTags
    function openCategoriesDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = dialog('ggImageCategoriesDialog');
      $d.find('#ggCategoriesInput').val((info.tags || []).join(', '));
      $d.data('tile', $tile).dialog('open');
    }

    function saveCategoriesTag($tile) {
      var value = dialog('ggImageCategoriesDialog').find('#ggCategoriesInput').val(),
        info = $tile.data('entity-info'),
        post = app.Ajax.Post({ module: 'galleries', action: 'addTag' }, { photo_id: info.id, tags: value });
      post.send(function () {
        $.jGrowl('Categories updated.');
        // Re-fetch so the categories view reflects the change - fetchAndRender
        // only swaps the tile list in place (same page/pagination, no
        // navigation), so scroll position and current view aren't disturbed.
        fetchAndRender(function () {
          if ($('#gg-tile-categories').is(':visible')) {
            populateCategoryBins();
          }
        });
      });
    }

    // Suggests the gallery's existing categories while still allowing free
    // text - reuses the bulk-toolbar's #gg-categories-list <option>s (kept
    // fresh by a page reload) instead of duplicating that data server-side.
    function initCategoriesAutocomplete() {
      var $input = $('#ggCategoriesInput');
      if (!$input.length || typeof $input.autocomplete !== 'function') {
        return;
      }

      function split(value) {
        return value.split(/,\s*/);
      }
      function extractLast(term) {
        return split(term).pop();
      }

      $input
        .on('keydown', function (event) {
          if (event.keyCode === $.ui.keyCode.TAB && $(this).autocomplete('instance').menu.active) {
            event.preventDefault();
          }
        })
        .on('focus click', function () {
          $(this).autocomplete('search', extractLast($(this).val()));
        })
        .autocomplete({
          minLength: 0,
          source: function (request, response) {
            var available = $('#gg-categories-list option')
              .map(function () {
                return this.value;
              })
              .get();
            response($.ui.autocomplete.filter(available, extractLast(request.term)));
          },
          open: function () {
            // jQuery UI dialogs bump their own z-index every time they're
            // opened/focused (it only ever grows), so a fixed z-index here
            // eventually falls back behind the dialog again - pin the menu
            // just above whatever the dialog's current z-index actually is.
            var $dlg = $input.closest('.ui-dialog'),
              dlgZ = $dlg.length ? parseInt($dlg.css('z-index'), 10) || 0 : 0;
            $input.autocomplete('widget').css('z-index', dlgZ + 1);
          },
          focus: function () {
            return false;
          },
          select: function (event, ui) {
            var terms = split(this.value);
            terms.pop();
            terms.push(ui.item.value, '');
            this.value = terms.join(', ');
            return false;
          },
        });
    }

    // Image on hover (Pro) - direct media picker, immediate save
    function openHoverPicker($tile) {
      var frame = window.wp.media({ title: 'Choose hover image', button: { text: 'Choose image' }, multiple: false, library: { type: 'image' } });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        updateAttachment($tile, { hoverCaptionImageInp: attachment.url }, 'Hover image set.');
      });
      frame.open();
    }

    // Attributes (Pro) - reuses the shared #ggImageAttributesDialog shell, own open/save logic
    function openAttributesDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = dialog('ggImageAttributesDialog'),
        imgValues = info.attributes || [],
        knownValues;

      // Same source viewCheckedContainer() already reads for the bulk
      // toolbar's attribute dropdown: one shared blob of every value ever
      // entered for each attribute name across the gallery.
      try {
        knownValues = JSON.parse($('#gg-attribute-values').attr('data-values'));
      } catch (err) {
        knownValues = [];
      }
      if (!$.isArray(knownValues)) {
        knownValues = [];
      }

      $d.attr('data-image-id', info.id).data('tile', $tile);
      $d.find('select.gg-attribute-values').each(function () {
        var $sel = $(this).empty(),
          attrName = $sel.closest('tr').find('input.gg-attribute-names').val(),
          current = '',
          values = [];
        $.each(imgValues, function (i, v) {
          if (v.name === attrName) {
            current = v.value;
          }
        });
        $.each(knownValues, function (i, entry) {
          if (entry.name === attrName) {
            values = entry.values;
          }
        });
        // The select starts empty every time (this dialog shell is shared
        // across all tiles) - it needs its <option>s built here before any
        // of them can be marked selected. Without this, the dropdown always
        // has zero options no matter what was saved previously.
        $sel.append($('<option value="">---</option>'));
        $.each(values, function (k, value) {
          $sel.append($('<option value="' + k + '"' + (value === current ? ' selected' : '') + '>' + value + '</option>'));
        });
      });
      // This dialog shell is shared across every tile - without resetting
      // it, a value typed here for one photo (but never submitted, or
      // submitted then reopened for another photo before editing it) stays
      // sitting in the input and gets attributed to whichever photo the
      // dialog is opened for next.
      $d.find('input.gg-attribute-new').val('');
      $d.find('#ggButtonLinkUrl').val(info.attachment.buttonLinkUrl || '');
      $d.find('#ggButtonLinkTitle').val(info.attachment.buttonLinkTitle || '');
      $d.find('#ggImageKeywords').val(info.attachment.imageKeywords || '');

      $d.dialog(
        'option',
        'buttons',
        {
          Save: function () {
            var attrs = [];
            $d.find('select.gg-attribute-values').each(function () {
              var $sel = $(this),
                attrName = $sel.closest('tr').find('input.gg-attribute-names').val(),
                newValue = $sel.closest('tr').find('input.gg-attribute-new').val(),
                value = newValue !== '' ? newValue : $sel.find('option:selected').text();
              if (value !== '' && value !== '---') {
                attrs.push({ name: attrName, value: value });
              }
            });
            var post = app.Ajax.Post({ module: 'galleries', action: 'saveAttributes' }, { gallery_id: state.galleryId });
            post.add('attributes', [{ id: info.id, attributes: attrs }]);
            app.Loader.show('Saving...');
            post.send(function () {
              app.Loader.hide();
              $.jGrowl('Attributes updated.');
              info.attributes = attrs;
              applyFilledIndicators($tile);

              // knownValues was read once when this dialog opened, so a
              // brand-new value typed just now isn't in it yet - without
              // this, it saves fine (a reload picks it up), but reopening
              // the dialog for any photo before reloading shows neither the
              // new option nor this photo's now-current selection.
              var changed = false;
              $.each(attrs, function (i, attr) {
                var entry = null;
                $.each(knownValues, function (j, e) {
                  if (e.name === attr.name) {
                    entry = e;
                  }
                });
                if (!entry) {
                  entry = { name: attr.name, values: [] };
                  knownValues.push(entry);
                }
                if (entry.values.indexOf(attr.value) === -1) {
                  entry.values.push(attr.value);
                  changed = true;
                }
              });
              if (changed) {
                $('#gg-attribute-values').attr('data-values', JSON.stringify(knownValues));
              }
            });
            updateAttachment($tile, {
              buttonLinkUrl: $d.find('#ggButtonLinkUrl').val(),
              buttonLinkTitle: $d.find('#ggButtonLinkTitle').val(),
              imageKeywords: $d.find('#ggImageKeywords').val(),
            });
            $d.dialog('close');
          },
          Cancel: function () {
            $d.dialog('close');
          },
        }
      );
      $d.dialog('open');
    }

    // Linked Images (Pro) - reuses the shared #linkedImagesDialog shell, own open/save logic
    function openLinkedImagesDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = dialog('linkedImagesDialog'),
        $listEl = $d.find('.linked-attachments-list').empty(),
        $addBtn = $d.find('.button.add'),
        $removeBtn = $d.find('.button.remove');

      var current = (info.attachment.linkedImages || '').split(',').filter(function (v) {
        return v !== '';
      });

      function renderThumb(attachment) {
        var url = (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) || attachment.url;
        $listEl.append(
          $('<div class="sc-attachment"><div class="thumbnail"></div></div>')
            .find('.thumbnail')
            .append($('<img />').attr('src', url).attr('data-attachment-id', attachment.id))
            .end()
            .on('click', function () {
              $(this).toggleClass('selected');
            })
        );
      }

      if (current.length) {
        $.get(ajaxurl, { action: 'getAttachmentsById', attachments: current.join(',') }, function (response) {
          $.each(response.attachments || [], function (i, attachment) {
            renderThumb(attachment);
          });
        });
      }

      $addBtn.off('click').on('click', function () {
        var frame = window.wp.media({ multiple: true });
        frame.on('select', function () {
          $.each(frame.state().get('selection').toJSON(), function (i, attachment) {
            renderThumb(attachment);
          });
        });
        frame.open();
      });

      $removeBtn.off('click').on('click', function () {
        $listEl.find('.sc-attachment.selected').fadeOut(200, function () {
          $(this).remove();
        });
      });

      $d.dialog(
        'option',
        'buttons',
        {
          Save: function () {
            var ids = [];
            $listEl.find('img').each(function () {
              ids.push($(this).attr('data-attachment-id'));
            });
            updateAttachment($tile, { linkedImages: ids.join(',') }, 'Linked images updated.');
            $d.dialog('close');
          },
          Cancel: function () {
            $d.dialog('close');
          },
        }
      );
      $d.dialog('open');
    }

    // E-commerce (Pro) - image-specific item.additional settings
    function ecommerceDialog() {
      return dialog('ggImageEcommerceDialog', { width: 560 });
    }

    function normalizeDimensionValue(value) {
      value = parseInt(value, 10);
      return isNaN(value) || value < 0 ? 0 : value;
    }

    function isChecked($input) {
      var $box = $input.closest('.icheckbox_minimal');
      if ($box.length) {
        return $box.hasClass('checked');
      }

      return $input.is(':checked');
    }

    function setChecked($input, checked) {
      checked = !!checked;
      $input.prop('checked', checked);
      if (typeof $.fn.iCheck === 'function') {
        $input.iCheck(checked ? 'check' : 'uncheck').iCheck('update');
      } else {
        $input.closest('.icheckbox_minimal').toggleClass('checked', checked);
      }
    }

    function renderProResolutionRow(dimension) {
      dimension = dimension || {};
      return $('<div class="gg-ecommerce-image-pro-row">')
        .append(
          $('<label>')
            .text('Width ')
            .append($('<input type="number" min="0" class="gg-ecommerce-pro-width">').val(normalizeDimensionValue(dimension.width)))
        )
        .append(
          $('<label>')
            .text('Height ')
            .append($('<input type="number" min="0" class="gg-ecommerce-pro-height">').val(normalizeDimensionValue(dimension.height)))
        )
        .append($('<button type="button" class="button gg-ecommerce-remove-resolution">').text('Remove'));
    }

    function collectEcommerceAdditional($d) {
      var additional = $.extend(true, {}, $d.data('additional') || {}),
        pro = [];

      $d.find('.gg-ecommerce-image-pro-row').each(function () {
        var width = normalizeDimensionValue($(this).find('.gg-ecommerce-pro-width').val()),
          height = normalizeDimensionValue($(this).find('.gg-ecommerce-pro-height').val());
        if (width > 0 || height > 0) {
          pro.push({ width: width, height: height });
        }
      });

      additional.show_original_without_watermark = isChecked($d.find('#ggEcommerceOriginalNoWatermark')) ? 1 : 0;
      additional.show_resolution_sidebar_in_popup = 0;
      additional.img_resolution_free = {
        width: normalizeDimensionValue($d.find('#ggEcommerceFreeWidth').val()),
        height: normalizeDimensionValue($d.find('#ggEcommerceFreeHeight').val()),
      };
      additional.img_resolution_pro = pro;

      return additional;
    }

    function updateEcommerceJsonPreview($d) {
      $d.find('#ggEcommerceAdditionalPreview').val(JSON.stringify(collectEcommerceAdditional($d), null, 2));
    }

    function populateEcommerceDialog($d, response) {
      var additional = response.additional || {},
        item = response.item || null,
        $state = $d.find('.gg-ecommerce-image-item-state'),
        $proRows = $d.find('#ggEcommerceProResolutions').empty();

      $d.data('additional', $.extend(true, {}, additional));
      setChecked($d.find('#ggEcommerceOriginalNoWatermark'), !!parseInt(additional.show_original_without_watermark, 10));
      $d.find('#ggEcommerceFreeWidth').val((additional.img_resolution_free && additional.img_resolution_free.width) || 0);
      $d.find('#ggEcommerceFreeHeight').val((additional.img_resolution_free && additional.img_resolution_free.height) || 0);

      $.each(additional.img_resolution_pro || [], function (i, dimension) {
        $proRows.append(renderProResolutionRow(dimension));
      });
      if (!$proRows.children().length) {
        $proRows.append(renderProResolutionRow({ width: 0, height: 0 }));
      }

      $state.empty();
      if (item) {
        $('<p>')
          .append($('<strong>').text('Item #' + item.id + ': '))
          .append(document.createTextNode(item.name || ''))
          .append(' ')
          .append($('<a target="_blank">').attr('href', item.edit_url).text('Open full item settings'))
          .appendTo($state);
      } else {
        $('<p class="description">')
          .text('No E-commerce Item exists for this image yet. Saving creates an inactive draft item for this gallery image.')
          .appendTo($state);
      }

      updateEcommerceJsonPreview($d);
    }

    function openEcommerceDialog($tile) {
      var info = $tile.data('entity-info'),
        $d = ecommerceDialog(),
        post = app.Ajax.Post({ module: 'ecommerce', action: 'getImageSettings' }, { photo_id: info.id, gallery_id: state.galleryId });

      app.Loader.show('Loading...');
      post.send(function (response) {
        app.Loader.hide();
        if (response.error) {
          $.jGrowl(response.message || 'Unable to load e-commerce settings.');
          return;
        }
        populateEcommerceDialog($d, response);
        $d.data('tile', $tile).dialog('open');
      });
    }

    function saveEcommerceDialog($tile, $d) {
      var info = $tile.data('entity-info'),
        additional = collectEcommerceAdditional($d),
        post = app.Ajax.Post(
          { module: 'ecommerce', action: 'saveImageSettings' },
          {
            photo_id: info.id,
            gallery_id: state.galleryId,
            show_original_without_watermark: additional.show_original_without_watermark,
            show_resolution_sidebar_in_popup: additional.show_resolution_sidebar_in_popup,
            img_resolution_free: additional.img_resolution_free,
            img_resolution_pro: additional.img_resolution_pro,
          }
        );

      app.Loader.show('Saving...');
      post.send(function (response) {
        app.Loader.hide();
        if (response.error) {
          $.jGrowl(response.message || 'Unable to save e-commerce settings.');
          return;
        }
        info.ecommerceItem = response.item;
        info.ecommerceAdditional = response.additional;
        $tile.data('entity-info', info);
        populateEcommerceDialog($d, response);
        applyFilledIndicators($tile);
        $.jGrowl(response.message || 'E-commerce image settings saved.');
        $d.dialog('close');
      });
    }

    // Effect (reuses the existing shared #effectDialog, incl. Pro's block overrides)
    var effectTile = null;

    function openEffectDialog($tile) {
      effectTile = $tile;
      var info = $tile.data('entity-info'),
        current = info.attachment.captionEffect || 'none';
      // photos.js's initEffectsDialog() already initializes this at
      // document-ready (width 740); dialog() below leaves that alone and
      // just opens it.
      var $d = dialog('effectDialog');
      $d.find('.grid-gallery-caption').removeClass('selected');
      $d.find('.grid-gallery-caption[data-grid-gallery-type="' + current + '"]').addClass('selected');
      $d.dialog('open');
    }

    $(document).on('click', '#effectDialog .grid-gallery-caption:not(.available-in-pro)', function () {
      if (!effectTile) {
        return;
      }
      var effect = $(this).data('grid-gallery-type');
      updateAttachment(effectTile, { captionEffect: effect }, 'Effect updated.');
      dialog('effectDialog').dialog('close');
    });

    // ---- Flyout dispatch ----

    var OPTION_HANDLERS = {
      caption: openCaptionDialog,
      seo: openSeoDialog,
      effect: openEffectDialog,
      attributes: openAttributesDialog,
      linked: openLinkedImagesDialog,
      hover: openHoverPicker,
      categories: openCategoriesDialog,
      video: openVideoDialog,
      ecommerce: openEcommerceDialog,
      copy: function ($tile) {
        openTransferDialog($tile, 'copy');
      },
      move: function ($tile) {
        openTransferDialog($tile, 'move');
      },
      link: openLinkDialog,
      rotate: openRotateDialog,
      crop: openCropDialog,
      meta: openMetaDialog,
      replace: openReplace,
      delete: deleteTile,
    };

    // Delegated on document, not $section: in category mode, tiles are
    // clones living inside #gg-tile-categories (a sibling of #gg-tile-grid),
    // so a handler scoped to $section would never see clicks bubble up
    // from them.
    $(document).on('click', '[data-gg-option]', function (event) {
      event.preventDefault();
      var option = $(this).data('gg-option'),
        $tile = currentTileFromEvent(event),
        handler = OPTION_HANDLERS[option];
      if (handler && $tile.length) {
        handler($tile);
      }
    });

    $(document).on('click', '.gg-dialog-save', function () {
      var $dlg = $(this).closest('.gg-option-dialog'),
        $tile = $dlg.data('tile');

      if (!$tile) {
        return;
      }

      switch ($dlg.attr('id')) {
        case 'ggImageCaptionDialog':
          updateAttachment($tile, {
            caption: $dlg.find('textarea[name="caption"]').val(),
            captionDescription: $dlg.find('textarea[name="captionDescription"]').val(),
          });
          break;
        case 'ggImageSeoDialog':
          updateAttachment($tile, {
            alt: $dlg.find('textarea[name="alt"]').val(),
            description: $dlg.find('textarea[name="description"]').val(),
          });
          break;
        case 'ggImageLinkDialog':
          var rel = [];
          $dlg.find('input[name="rel[]"]:checked').each(function () {
            rel.push($(this).val());
          });
          updateAttachment($tile, {
            link: $dlg.find('input[name="link"]').val(),
            target: $dlg.find('input[name="target"]').is(':checked') ? '_blank' : '',
            rel: rel,
          });
          break;
        case 'ggImageVideoDialog':
          updateAttachment($tile, { video: $dlg.find('input[name="video"]').val() });
          break;
        case 'ggImageCropDialog':
          updateAttachment($tile, { cropPosition: $dlg.find('#ggCropPositionSelect').val() });
          break;
        case 'ggImageRotateDialog':
          applyRotate($tile);
          break;
        case 'ggImageTransferDialog':
          applyTransfer();
          break;
        case 'ggImageEcommerceDialog':
          saveEcommerceDialog($tile, $dlg);
          return;
      }
      $dlg.dialog('close');
    });

    $(document).on('input change', '#ggImageEcommerceDialog input', function () {
      updateEcommerceJsonPreview(ecommerceDialog());
    });

    $(document).on('ifChecked ifUnchecked ifChanged', '#ggImageEcommerceDialog input[type="checkbox"]', function () {
      window.setTimeout(function () {
        updateEcommerceJsonPreview(ecommerceDialog());
      }, 0);
    });

    $(document).on('click', '#ggEcommerceAddResolution', function () {
      var $d = ecommerceDialog();
      $d.find('#ggEcommerceProResolutions').append(renderProResolutionRow({ width: 0, height: 0 }));
      updateEcommerceJsonPreview($d);
    });

    $(document).on('click', '.gg-ecommerce-remove-resolution', function () {
      var $d = ecommerceDialog();
      $(this).closest('.gg-ecommerce-image-pro-row').remove();
      if (!$d.find('.gg-ecommerce-image-pro-row').length) {
        $d.find('#ggEcommerceProResolutions').append(renderProResolutionRow({ width: 0, height: 0 }));
      }
      updateEcommerceJsonPreview($d);
    });

    $(document).on('click', '.gg-ecommerce-toggle-json', function () {
      var $button = $(this),
        $target = $($button.data('target')),
        isOpen = !$target.is(':visible');
      $target.toggle(isOpen);
      $button.text(isOpen ? $button.data('hide-label') || 'Hide JSON' : $button.data('show-label') || 'Show JSON');
    });

    $(document).on('click', '.gg-dialog-close', function () {
      var $dlg = $(this).closest('.gg-option-dialog');
      if ($dlg.attr('id') === 'ggImageCategoriesDialog') {
        saveCategoriesTag($dlg.data('tile'));
      }
      $dlg.dialog('close');
    });

    // ---- Bulk toolbar ----

    function viewCheckedContainer(action, attrName) {
      var $div = $('ul.gg-checked-options');
      $div.find('li.gg-checked-container').addClass('ggSettingsDisplNone');
      if (action === 'attributes') {
        var $container = $('#gg-attribute-values').empty();
        if (typeof attrName !== 'undefined') {
          var attrValues;
          try {
            attrValues = JSON.parse($container.attr('data-values'));
          } catch (err) {
            attrValues = [];
          }
          $.each(attrValues, function (i, entry) {
            if (entry.name === attrName) {
              $.each(entry.values, function (k, value) {
                $container.append($('<option value="' + k + '"></option>').text(value));
              });
            }
          });
        }
      }
      $div.find('li.gg-checked-' + action).removeClass('ggSettingsDisplNone');
    }

    $('[name="checkedDo"]').on('change', function () {
      var $selected = $(this).find('option:selected');
      viewCheckedContainer($selected.is('[data-attribute]') ? 'attributes' : $(this).val(), $selected.text());
    });

    function bulkRotate(rotateType) {
      var $tiles = getCheckedTiles(),
        ids = $tiles
          .map(function () {
            return $(this).data('entity-info').id;
          })
          .get();
      if (!ids.length) {
        return;
      }
      var post = app.Ajax.Post({ module: 'photos', action: 'rotatePhoto' }, { rotateType: rotateType, gallery_id: state.galleryId });
      post.add('ids', ids);
      app.Loader.show('Rotating...');
      post.send(function (response) {
        app.Loader.hide();
        $.each($tiles, function () {
          var $tile = $(this),
            $img = $tile.find('img.gg-tile-thumb'),
            src = ($img.attr('src') || $img.data('original')).split('?')[0] + '?' + Math.random();
          $img.attr('src', src).data('original', src);
        });
        $.jGrowl(response.message);
      });
    }

    function bulkTransfer(targetGalleryId, isMove) {
      var $tiles = getCheckedTiles();
      if (!targetGalleryId || !$tiles.length) {
        return;
      }
      var remaining = $tiles.length;
      $tiles.each(function () {
        var $tile = $(this),
          info = $tile.data('entity-info'),
          post = app.Ajax.Post(
            { module: 'photos', action: 'add' },
            { attachment_id: info.attachment.id, galleryId: targetGalleryId, view_type: 'list', attachType: 'gallery', save_exif_data: '1' }
          );
        app.Loader.show(isMove ? 'Moving...' : 'Copying...');
        post.send(function (response) {
          if (!--remaining) {
            app.Loader.hide();
          }
          if (!response.error && isMove) {
            removeTiles([$tile], [info.id]);
          }
          $.jGrowl(response.message);
        });
      });
    }

    function bulkDelete() {
      var $tiles = getCheckedTiles(),
        ids = $tiles
          .map(function () {
            return $(this).data('entity-info').id;
          })
          .get();
      if (!ids.length || !confirm($('#checkedDoLi').data('delete-confirm'))) {
        return;
      }
      removeTiles(
        $tiles.map(function () {
          return $(this);
        }).get(),
        ids
      );
    }

    function bulkCrop(position) {
      getCheckedTiles().each(function () {
        var $tile = $(this);
        updateAttachment($tile, { cropPosition: position });
      });
    }

    function bulkCategoryTags(tag, type) {
      var ids = getCheckedTiles()
        .map(function () {
          return $(this).data('entity-info').id;
        })
        .get();
      if (!tag || !ids.length) {
        $.jGrowl('Select images.');
        return;
      }
      var post = app.Ajax.Post({ module: 'galleries', action: 'allImageTags' }, { gallery_id: state.galleryId, type: type, tag: tag });
      post.add('ids', ids);
      app.Loader.show('Updating category...');
      post.send(function () {
        app.Loader.hide();
        $.jGrowl('Category updated.');
      });
    }

    function bulkAttribute(attrName, attrValue) {
      if (!attrName || !attrValue) {
        return;
      }
      var data = [];
      getCheckedTiles().each(function () {
        var info = $(this).data('entity-info'),
          attrs = info.attributes || [],
          found = false;
        $.each(attrs, function (i, entry) {
          if (entry.name === attrName) {
            entry.value = attrValue;
            found = true;
          }
        });
        if (!found) {
          attrs.push({ name: attrName, value: attrValue });
        }
        data.push({ id: info.id, attributes: attrs });
      });
      var post = app.Ajax.Post({ module: 'galleries', action: 'saveAttributes' }, { gallery_id: state.galleryId });
      post.add('attributes', data);
      app.Loader.show('Saving...');
      post.send(function () {
        app.Loader.hide();
        $.jGrowl('Custom attributes updated.');
      });
    }

    $('[data-button="checkedbtn"]').on('click', function () {
      var $action = $('[name="checkedDo"]');
      if (!$action.length) {
        return;
      }
      var $selected = $action.find('option:selected');

      if ($selected.is('[data-attribute]')) {
        bulkAttribute($selected.text(), $('#gg-attribute-values option:selected').text());
        return;
      }

      switch ($action.val()) {
        case 'copy':
          bulkTransfer($('#gg-galleries-list').val(), false);
          break;
        case 'move':
          bulkTransfer($('#gg-galleries-list').val(), true);
          break;
        case 'rotate-clock':
          bulkRotate('clockwise');
          break;
        case 'rotate-cclock':
          bulkRotate('counter');
          break;
        case 'delete':
          bulkDelete();
          break;
        case 'crop':
          bulkCrop($('#gg-crop-positions').val());
          break;
        case 'add-category':
          bulkCategoryTags($('#gg-categories-list').val(), 'add');
          break;
        case 'del-category':
          bulkCategoryTags($('#gg-categories-del').val(), 'delcat');
          break;
        case 'new-category':
          bulkCategoryTags($('#gg-new-category').val(), 'add');
          break;
      }
    });

    // ---- Pro: Show/Hide Categories (bin-grouped layout) ----

    function applySavedCategoryOrder() {
      var $categories = $('#gg-tile-categories'),
        order,
        $blocks = $categories.children('.gg-category');

      try {
        order = JSON.parse($categories.attr('data-category-order') || '[]');
      } catch (e) {
        order = [];
      }
      if (!order.length) {
        return;
      }

      $.each(order, function (i, category) {
        var $block = $blocks.filter(function () {
          return $(this).find('.gg-tile-category-bin').data('category') === category;
        });
        if ($block.length) {
          $categories.append($block);
        }
      });
      // Uncategorized always stays last regardless of saved order.
      $categories.append($categories.children('.gg-category-uncategorized'));
    }

    function initCategorySortable() {
      var $categories = $('#gg-tile-categories');
      if (typeof $categories.sortable !== 'function') {
        return;
      }
      $categories.sortable({
        items: '> .gg-category:not(.gg-category-uncategorized)',
        handle: '.gg-category-caption',
        axis: 'y',
        stop: function () {
          var order = $categories
            .children('.gg-category:not(.gg-category-uncategorized)')
            .find('.gg-tile-category-bin')
            .map(function () {
              return $(this).data('category');
            })
            .get();
          app.Ajax.Post({ module: 'galleries', action: 'saveCategoryOrder' }, { gallery_id: state.galleryId, order: order }).send(function () {
            $.jGrowl('Category order saved.');
          });
        },
      });
    }

    function updateBinCount($bin) {
      $bin.closest('.gg-category').find('[data-count]').text($bin.find('.gg-tile').length);
    }

    function populateCategoryBins() {
      var $bins = $('#gg-tile-categories .gg-tile-category-bin');
      $bins.empty();
      $list()
        .find('.gg-tile')
        .each(function () {
          var $tile = $(this),
            tags = $tile.data('entity-info').tags || [];

          if (!tags.length) {
            $bins.filter('[data-category=""]').append($tile.clone(true));
            return;
          }
          // A photo can belong to several categories at once - it needs a
          // clone in every matching bin, not just the first tag's.
          $.each(tags, function (i, tag) {
            var $bin = $bins.filter('[data-category="' + tag + '"]');
            if ($bin.length) {
              $bin.append($tile.clone(true));
            }
          });
        });
      $bins.each(function () {
        updateBinCount($(this));
      });
    }

    // Images inside the categories view are deliberately static (no
    // per-image drag): they keep their own single global gallery-wide sort
    // order untouched here, matching legacy behavior. Only the category
    // blocks themselves (initCategorySortable, above) are reorderable in
    // this view.

    $('[data-button="show-categories"]').on('click', function () {
      $(this).addClass('ggSettingsDisplNone');
      $('[data-button="hide-categories"]').removeClass('ggSettingsDisplNone');
      $grid.addClass('ggSettingsDisplNone');
      $('.gg-flat-view-only').addClass('ggSettingsDisplNone');
      $('#gg-sort-by-category-order-li').removeClass('ggSettingsDisplNone');
      $('#gg-tile-categories').removeClass('ggSettingsDisplNone');
      // In category-bins mode, images are placed by dragging into a bin -
      // the per-tile flyout only makes sense for manually assigning the
      // Categories tag, so every other option is disabled while it's on.
      $('body').addClass('gg-categories-mode');

      applySavedCategoryOrder();
      initCategorySortable();
      populateCategoryBins();
    });

    $('[data-button="hide-categories"]').on('click', function () {
      $(this).addClass('ggSettingsDisplNone');
      $('[data-button="show-categories"]').removeClass('ggSettingsDisplNone');
      $grid.removeClass('ggSettingsDisplNone');
      $('.gg-flat-view-only').removeClass('ggSettingsDisplNone');
      $('#gg-tile-categories').addClass('ggSettingsDisplNone');
      $('body').removeClass('gg-categories-mode');
      // Bins hold cloned tiles (the originals stay in the main list) -
      // drop them now rather than leaving stale clones sitting in the DOM
      // until the next "Show Categories" click empties them.
      $('#gg-tile-categories .gg-tile-category-bin').empty();
    });

    // Renaming a category (.gg-rename-category / #ggRenameCategory) is
    // already fully handled by Photos/assets/js/photos.js
    // (initRenameCategoryDialog, loaded unconditionally on this page) -
    // nothing to wire up here.

    // ---- Init ----

    applyTileSize();
    updatePagination();
    initTileBehaviors();
    initCategoriesAutocomplete();
  });
})((window.SupsysticGallery = window.SupsysticGallery || {}), jQuery);
