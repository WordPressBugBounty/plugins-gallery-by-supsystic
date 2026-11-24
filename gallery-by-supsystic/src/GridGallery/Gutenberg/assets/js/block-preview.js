/**
 * Live preview of the Photo Gallery blocks in the block editor, as in the Elementor editor: the server renders the
 * same markup as on the page, and the gallery's own CSS/JS is loaded into the editor canvas once.
 *
 * Only when the editor canvas is an iframe (WordPress 7, or 6.3+ in iframe mode). Otherwise the block keeps its
 * picker without a preview, so the gallery's front-end styles never reach the admin screen itself.
 */
(function (wp) {
  if (!wp || !wp.element) {
    return;
  }
  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var useRef = wp.element.useRef;
  var components = wp.components;
  var blockEditor = wp.blockEditor || {};
  var ServerSideRender = wp.serverSideRender;
  var __ = wp.i18n.__;
  var canvasData = window.SupsysticGalleryCanvas || {};

  // Links inside a preview must not take the editor away from the page. Lightboxes and sliders still work:
  // they prevent the default of the clicks they handle, and only clicks nobody handled are stopped here.
  function guardLinks(win) {
    if (win.__sggPreviewLinks) {
      return;
    }
    win.__sggPreviewLinks = true;
    win.addEventListener('click', function (event) {
      if (event.defaultPrevented) {
        return;
      }
      var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
      if (link && link.closest('.sgg-block-preview')) {
        event.preventDefault();
      }
    });
  }

  // The gallery's CSS/JS (printed by the server as tags) added to the canvas document in order, once.
  function loadAssets(doc) {
    if (doc.__sggAssets) {
      return doc.__sggAssets;
    }
    var win = doc.defaultView;
    doc.__sggAssets = new Promise(function (resolve) {
      var holder = doc.createElement('template');
      holder.innerHTML = canvasData.assets || '';
      var nodes = Array.prototype.slice.call(holder.content.childNodes);
      var index = 0;
      function next() {
        if (index >= nodes.length) {
          resolve();
          return;
        }
        var node = nodes[index++];
        if (node.nodeType !== 1 || (node.id && doc.getElementById(node.id))) {
          next();
          return;
        }
        if (node.tagName !== 'SCRIPT') {
          doc.head.appendChild(doc.importNode(node, true));
          next();
          return;
        }
        // The canvas may already have jQuery: a second copy would drop what other scripts attached to the first
        if (win.jQuery && /^jquery(-core|-migrate)?-js$/.test(node.id || '')) {
          next();
          return;
        }
        var script = doc.createElement('script');
        Array.prototype.forEach.call(node.attributes, function (attribute) {
          script.setAttribute(attribute.name, attribute.value);
        });
        if (node.src) {
          script.onload = next;
          script.onerror = next;
          doc.head.appendChild(script);
        } else {
          script.text = node.textContent;
          doc.head.appendChild(script);
          next();
        }
      }
      next();
    });
    guardLinks(win);
    return doc.__sggAssets;
  }

  function externalLink(href, text) {
    return components.ExternalLink
      ? el(components.ExternalLink, { href: href }, text)
      : el('a', { href: href, target: '_blank', rel: 'noopener noreferrer' }, text);
  }

  /**
   * @param {Object} config block, attribute, options, panelTitle, selectLabel, hint, emptyText,
   *   title, icon, canManage, editUrl, createUrl, editText, createText
   * @return {Function} The block's edit component
   */
  function edit(config) {
    return function (props) {
      var ref = useRef(null);
      var blockProps = blockEditor.useBlockProps ? blockEditor.useBlockProps({ ref: ref }) : { className: props.className, ref: ref };
      // null: not known yet; false: no preview here; true: the canvas has the gallery's CSS/JS
      var canvasState = useState(null);
      var canvasReady = canvasState[0];
      var setCanvasReady = canvasState[1];
      var selectedId = props.attributes[config.attribute] || '';

      useEffect(function () {
        var node = ref.current;
        var doc = node && node.ownerDocument;
        if (!doc || doc === window.document || !ServerSideRender || !canvasData.assets) {
          setCanvasReady(false);
          return;
        }
        var alive = true;
        loadAssets(doc).then(function () {
          if (alive) {
            setCanvasReady(true);
          }
        });
        return function () {
          alive = false;
        };
      }, []);

      function select() {
        return el(components.SelectControl, {
          label: config.selectLabel,
          value: selectedId,
          options: config.options,
          onChange: function (value) {
            var attributes = {};
            attributes[config.attribute] = value;
            props.setAttributes(attributes);
          },
        });
      }

      var selectedLabel = selectedId
        ? (config.options.filter(function (o) { return o.value === selectedId; })[0] || {}).label
        : '';
      // "Edit this …" only for an existing one; "Create a …" always (only for users who can open the plugin)
      var panel = [el('div', { key: 'select' }, select())];
      if (config.canManage) {
        if (selectedLabel && config.editUrl) {
          panel.push(el('p', { key: 'edit' }, externalLink(config.editUrl + encodeURIComponent(selectedId), config.editText)));
        }
        if (config.createUrl) {
          panel.push(el('p', { key: 'create' }, externalLink(config.createUrl, config.createText)));
        }
      }
      var inspector = blockEditor.InspectorControls
        ? el(blockEditor.InspectorControls, {}, el(components.PanelBody, { title: config.panelTitle, initialOpen: true }, panel))
        : null;

      if (selectedId && canvasReady) {
        var attributes = {};
        attributes[config.attribute] = selectedId;
        return el(
          'div',
          blockProps,
          inspector,
          el(
            'div',
            { className: 'sgg-block-preview', style: { position: 'relative' } },
            el(ServerSideRender, {
              block: config.block,
              attributes: attributes,
              EmptyResponsePlaceholder: function () {
                return el('p', {}, config.emptyText);
              },
            }),
            // Until the block is selected a click selects it instead of opening a photo
            props.isSelected
              ? null
              : el('div', { className: 'sgg-block-preview__cover', style: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, zIndex: 50 } })
          )
        );
      }

      if (selectedId && canvasReady === null) {
        return el('div', blockProps, inspector, el(components.Spinner, {}));
      }

      // No gallery yet, or no preview in this editor: the picker, with the Supsystic icon
      var body = [el('div', { key: 'select', style: { width: '100%' } }, select())];
      if (!selectedId && config.canManage && config.createUrl) {
        body.push(el('div', { key: 'create', style: { width: '100%', marginTop: '8px' } }, externalLink(config.createUrl, config.createText)));
      }
      var instructions = selectedLabel ? __('Selected: ', 'sgg') + selectedLabel : config.hint;
      if (components.Placeholder) {
        return el(
          'div',
          blockProps,
          inspector,
          el(components.Placeholder, { icon: config.icon, label: config.title, instructions: instructions }, body)
        );
      }
      return el(
        'div',
        blockProps,
        inspector,
        el(components.PanelBody, { title: config.panelTitle, initialOpen: true }, body),
        el('p', {}, instructions)
      );
    };
  }

  window.SupsysticGalleryBlockPreview = { edit: edit };
})(window.wp);
