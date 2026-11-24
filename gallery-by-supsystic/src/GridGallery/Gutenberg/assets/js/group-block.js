(function (blocks, element, components, i18n, blockEditor) {
  var el = element.createElement;
  var __ = i18n.__;
  var blockData = window.SupsysticGalleryGroupBlockData || {};
  var hasGroups = !!(blockData.groups && blockData.groups.length);
  var groupOptions = hasGroups
    ? [{ label: __('— Select a gallery group —', 'sgg'), value: '' }].concat(blockData.groups)
    : [{ label: __('No gallery groups yet', 'sgg'), value: '' }];
  var preview = window.SupsysticGalleryBlockPreview;
  // The "Supsystic" category already names the brand (fix/shared/gutenberg-supsystic-category.md)
  var title = __('Photo Gallery Group', 'sgg');
  // The Supsystic logo, as on the CES blocks (inserter, toolbar, list view, placeholder). Not draggable and no pointer
  // events (B073): otherwise dragging the block from the inserter drags the picture and inserts a core image instead
  var icon = blockData.icon
    ? el('img', { src: blockData.icon, alt: '', width: 24, height: 24, draggable: false, style: { width: 24, height: 24, pointerEvents: 'none' } })
    : 'grid-view';

  // The picker alone, without a preview (used only if the preview script is missing)
  function pickerEdit(props) {
    var attrs = props.attributes;
    var selectedId = attrs.groupId || '';
    // block.json has apiVersion 2: without useBlockProps() the block can't be selected by a click
    // (no toolbar to move or remove it, no Block panel). Saved content is not affected (save is null).
    var blockProps = blockEditor && blockEditor.useBlockProps ? blockEditor.useBlockProps() : { className: props.className };

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
  }

  blocks.registerBlockType('supsystic-gallery/gallery-group', {
    title: title,
    description: __('Insert a Gallery Group - displays every gallery in it.', 'sgg'),
    icon: icon,
    category: 'supsystic',
    keywords: [__('gallery', 'sgg'), __('gallery group', 'sgg'), 'supsystic'],
    attributes: {
      groupId: { type: 'string', default: '' },
    },
    edit: preview
      ? preview.edit({
          block: 'supsystic-gallery/gallery-group',
          attribute: 'groupId',
          options: groupOptions,
          panelTitle: __('Gallery Group', 'sgg'),
          selectLabel: __('Select gallery group', 'sgg'),
          hint: __('Choose the gallery group to show.', 'sgg'),
          emptyText: __('This gallery group is empty, or it was deleted.', 'sgg'),
          title: title,
          icon: icon,
          canManage: !!blockData.canManage,
          editUrl: blockData.editUrl || '',
          createUrl: blockData.createUrl || '',
          editText: __('Edit this gallery group in Photo Gallery', 'sgg'),
          createText: __('Create a gallery group in Photo Gallery', 'sgg'),
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
