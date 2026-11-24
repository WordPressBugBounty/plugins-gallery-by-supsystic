(function (blocks, element, components, i18n, blockEditor) {
  var el = element.createElement;
  var __ = i18n.__;
  var blockData = window.SupsysticGalleryBlockData || {};
  var hasGalleries = !!(blockData.galleries && blockData.galleries.length);
  var galleryOptions = hasGalleries
    ? [{ label: __('— Select a gallery —', 'sgg'), value: '' }].concat(blockData.galleries)
    : [{ label: __('No galleries yet', 'sgg'), value: '' }];
  var preview = window.SupsysticGalleryBlockPreview;
  // The "Supsystic" category already names the brand (fix/shared/gutenberg-supsystic-category.md)
  var title = __('Photo Gallery', 'sgg');
  // The Supsystic logo, as on the CES blocks (inserter, toolbar, list view, placeholder). Not draggable and no pointer
  // events (B073): otherwise dragging the block from the inserter drags the picture and inserts a core image instead
  var icon = blockData.icon
    ? el('img', { src: blockData.icon, alt: '', width: 24, height: 24, draggable: false, style: { width: 24, height: 24, pointerEvents: 'none' } })
    : 'format-gallery';

  // The picker alone, without a preview (used only if the preview script is missing)
  function pickerEdit(props) {
    var attrs = props.attributes;
    var selectedId = attrs.galleryId || '';
    // block.json has apiVersion 2: without useBlockProps() the block can't be selected by a click
    // (no toolbar to move or remove it, no Block panel). Saved content is not affected (save is null).
    var blockProps = blockEditor && blockEditor.useBlockProps ? blockEditor.useBlockProps() : { className: props.className };

    var selectedLabel = selectedId
      ? (galleryOptions.filter(function (o) { return o.value === selectedId; })[0] || {}).label
      : '';

    return el(
      'div',
      blockProps,
      el(
        components.PanelBody,
        { title: __('Gallery', 'sgg'), initialOpen: true },
        el(components.SelectControl, {
          label: __('Select gallery', 'sgg'),
          value: selectedId,
          options: galleryOptions,
          onChange: function (value) {
            props.setAttributes({ galleryId: value });
          },
        }),
      ),
      el(
        'p',
        {},
        selectedLabel
          ? __('Selected: ', 'sgg') + selectedLabel
          : __('Select a gallery above. It will appear on the published page.', 'sgg'),
      ),
    );
  }

  blocks.registerBlockType('supsystic-gallery/gallery', {
    title: title,
    description: __('Insert one of your galleries: a grid, a carousel or a slider.', 'sgg'),
    icon: icon,
    category: 'supsystic',
    keywords: [__('gallery', 'sgg'), __('photo gallery', 'sgg'), 'supsystic'],
    attributes: {
      galleryId: { type: 'string', default: '' },
    },
    edit: preview
      ? preview.edit({
          block: 'supsystic-gallery/gallery',
          attribute: 'galleryId',
          options: galleryOptions,
          panelTitle: __('Gallery', 'sgg'),
          selectLabel: __('Select gallery', 'sgg'),
          hint: __('Choose the gallery to show.', 'sgg'),
          emptyText: __('This gallery has no images yet, or it was deleted.', 'sgg'),
          title: title,
          icon: icon,
          canManage: !!blockData.canManage,
          editUrl: blockData.editUrl || '',
          createUrl: blockData.createUrl || '',
          editText: __('Edit this gallery in Photo Gallery', 'sgg'),
          createText: __('Create a gallery in Photo Gallery', 'sgg'),
        })
      : pickerEdit,
    save: function () {
      return null;
    },
  });

  // The Supsystic logo on the shared category too (harmless if another Supsystic plugin also sets it)
  if (blocks.updateCategory && blockData.icon) {
    blocks.updateCategory('supsystic', { icon: icon });
  }
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.i18n, window.wp.blockEditor);
