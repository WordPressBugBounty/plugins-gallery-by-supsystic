(function (blocks, element, components, i18n) {
  var el = element.createElement;
  var __ = i18n.__;
  var blockData = window.SupsysticGalleryBlockData || {};
  var hasGalleries = !!(blockData.galleries && blockData.galleries.length);
  var galleryOptions = hasGalleries
    ? [{ label: __('— Select a gallery —', 'sgg'), value: '' }].concat(blockData.galleries)
    : [{ label: __('No galleries yet', 'sgg'), value: '' }];

  blocks.registerBlockType('supsystic-gallery/gallery', {
    title: __('Photo Gallery by Supsystic', 'sgg'),
    description: __('Insert one of your galleries. Appears on the published page; no live preview here in the editor.', 'sgg'),
    icon: 'format-gallery',
    category: 'widgets',
    keywords: [__('gallery', 'sgg'), __('photo gallery', 'sgg'), 'supsystic'],
    attributes: {
      galleryId: { type: 'string', default: '' },
    },
    edit: function (props) {
      var attrs = props.attributes;
      var selectedId = attrs.galleryId || '';
      var blockProps = { className: props.className };

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
    },
    save: function () {
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.i18n);
