(function (blocks, element, components, i18n) {
  var el = element.createElement;
  var __ = i18n.__;
  var blockData = window.SupsysticGalleryGroupBlockData || {};
  var hasGroups = !!(blockData.groups && blockData.groups.length);
  var groupOptions = hasGroups
    ? [{ label: __('— Select a gallery group —', 'sgg'), value: '' }].concat(blockData.groups)
    : [{ label: __('No gallery groups yet', 'sgg'), value: '' }];

  blocks.registerBlockType('supsystic-gallery/gallery-group', {
    title: __('Gallery Group by Supsystic', 'sgg'),
    description: __('Insert a Gallery Group - displays every gallery in it. Appears on the published page; no live preview here in the editor.', 'sgg'),
    icon: 'grid-view',
    category: 'widgets',
    keywords: [__('gallery', 'sgg'), __('gallery group', 'sgg'), 'supsystic'],
    attributes: {
      groupId: { type: 'string', default: '' },
    },
    edit: function (props) {
      var attrs = props.attributes;
      var selectedId = attrs.groupId || '';
      var blockProps = { className: props.className };

      var selectedLabel = selectedId
        ? (groupOptions.filter(function (o) { return o.value === selectedId; })[0] || {}).label
        : '';

      return el(
        'div',
        blockProps,
        el(
          components.PanelBody,
          { title: __('Gallery Group', 'sgg'), initialOpen: true },
          el(components.SelectControl, {
            label: __('Select gallery group', 'sgg'),
            value: selectedId,
            options: groupOptions,
            onChange: function (value) {
              props.setAttributes({ groupId: value });
            },
          }),
        ),
        el(
          'p',
          {},
          selectedLabel
            ? __('Selected: ', 'sgg') + selectedLabel
            : __('Select a gallery group above. It will appear on the published page.', 'sgg'),
        ),
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.i18n);
