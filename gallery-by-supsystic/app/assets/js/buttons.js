/**
 * Photo Gallery buttons for the classic editor (TinyMCE: the Gutenberg Classic block, Elementor's Text Editor, any
 * wp_editor): "Insert a Photo Gallery" and "Insert a Photo Gallery Group". Each opens a small dialog with the list
 * (newest first, with a search box for long lists) and inserts the shortcode at the cursor.
 * Spec: fix/shared/classic-editor-buttons.md. Plain ES5, no jQuery and no other script needed.
 */
(function () {
  if (typeof tinymce === 'undefined') {
    return;
  }

  var STYLE_ID = 'sgg-mce-style';
  var CSS =
    // Toolbar: the Supsystic group turns Supsystic cyan on hover, as in the admin sidebar
    '.mce-btn.mce-supsystic-btn:hover .mce-ico,.mce-btn.mce-supsystic-btn:focus .mce-ico{color:#4ae8ea}' +
    '.sgg-mce-overlay{position:fixed;inset:0;z-index:170000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;color:#1e1e1e}' +
    '.sgg-mce-dialog{box-sizing:border-box;width:440px;max-width:calc(100vw - 32px);background:#fff;border-radius:8px;box-shadow:0 12px 40px rgba(0,0,0,.3);overflow:hidden}' +
    '.sgg-mce-dialog *{box-sizing:border-box}' +
    '.sgg-mce-head{display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid #e0e0e0}' +
    '.sgg-mce-head .dashicons{color:#4ae8ea;font-size:22px;width:22px;height:22px}' +
    '.sgg-mce-head h2{flex:1;margin:0;font-size:16px;font-weight:600;line-height:1.4;color:#1e1e1e}' +
    '.sgg-mce-x{border:0;background:none;cursor:pointer;color:#757575;font-size:22px;line-height:1;padding:2px 6px;border-radius:4px}' +
    '.sgg-mce-x:hover,.sgg-mce-x:focus{color:#1e1e1e;background:#f0f0f0;outline:none}' +
    '.sgg-mce-body{padding:18px 20px}' +
    '.sgg-mce-body label{display:block;margin:0 0 6px;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.02em;color:#3c434a}' +
    '.sgg-mce-body input,.sgg-mce-body select{display:block;width:100%;max-width:none;height:36px;margin:0 0 12px;padding:0 10px;border:1px solid #8c8f94;border-radius:4px;background:#fff;font-size:14px;color:#1e1e1e;box-shadow:none}' +
    '.sgg-mce-body input:focus,.sgg-mce-body select:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:none}' +
    '.sgg-mce-note{margin:0 0 12px;color:#646970}' +
    '.sgg-mce-create{margin:4px 0 0}' +
    '.sgg-mce-create a{color:#2271b1;text-decoration:none}' +
    '.sgg-mce-create a:hover{text-decoration:underline}' +
    '.sgg-mce-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;background:#f6f7f7;border-top:1px solid #e0e0e0}' +
    '.sgg-mce-btn{height:34px;padding:0 16px;border-radius:4px;border:1px solid #2271b1;background:#fff;color:#2271b1;font-size:13px;font-weight:500;cursor:pointer}' +
    '.sgg-mce-btn:hover{background:#f0f6fc}' +
    '.sgg-mce-btn.is-primary{background:#2271b1;color:#fff}' +
    '.sgg-mce-btn.is-primary:hover{background:#135e96;border-color:#135e96}' +
    '.sgg-mce-btn:disabled{opacity:.5;cursor:default}' +
    '.sgg-mce-btn:focus{outline:2px solid #4ae8ea;outline-offset:1px}';

  var TYPES = {
    gallery: { listKey: 'galleries', titleKey: 'insertGallery', labelKey: 'gallery', emptyKey: 'noGalleries', createKey: 'createGallery', icon: 'dashicons-format-gallery', shortcode: 'supsystic-gallery' },
    group: { listKey: 'groups', titleKey: 'insertGroup', labelKey: 'group', emptyKey: 'noGroups', createKey: 'createGroup', icon: 'dashicons-images-alt2', shortcode: 'supsystic-gallery-group' },
  };

  // The "sgg_editor" TinyMCE setting: an object from wp_editor(), a JSON string from the block editor's Classic block
  function getSettings(editor) {
    var settings = editor.getParam('sgg_editor') || {};
    if (typeof settings === 'string') {
      try {
        settings = JSON.parse(settings);
      } catch (e) {
        settings = {};
      }
    }
    return settings || {};
  }

  function ensureStyle(doc) {
    if (!doc || doc.getElementById(STYLE_ID)) {
      return;
    }
    var style = doc.createElement('style');
    style.id = STYLE_ID;
    style.textContent = CSS;
    (doc.head || doc.documentElement).appendChild(style);
  }

  function make(doc, tag, attrs, text) {
    var node = doc.createElement(tag);
    for (var key in attrs) {
      if (Object.prototype.hasOwnProperty.call(attrs, key)) {
        node.setAttribute(key, attrs[key]);
      }
    }
    if (text) {
      node.textContent = text;
    }
    return node;
  }

  function load(settings, done) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', settings.ajaxUrl);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.onload = function () {
      var json = null;
      try {
        json = JSON.parse(xhr.responseText);
      } catch (e) {}
      done(json && json.success ? json.data : null);
    };
    xhr.onerror = function () {
      done(null);
    };
    xhr.send('action=sgg_editor_objects&nonce=' + encodeURIComponent(settings.nonce || ''));
  }

  function openDialog(editor, typeName) {
    var settings = getSettings(editor);
    var i18n = settings.i18n || {};
    var type = TYPES[typeName];
    var doc = document;
    // The cursor, to put the shortcode back where it was after the dialog took the focus
    // (a copy of the DOM range: TinyMCE's index bookmarks land in the wrong place when text nodes are not merged)
    var bookmark = null;
    try {
      bookmark = editor.selection.getRng().cloneRange();
    } catch (e) {}

    ensureStyle(doc);
    var lastFocus = doc.activeElement;
    var overlay = make(doc, 'div', { class: 'sgg-mce-overlay' });
    var dialog = make(doc, 'div', { class: 'sgg-mce-dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'sgg-mce-title' });
    var head = make(doc, 'div', { class: 'sgg-mce-head' });
    head.appendChild(make(doc, 'span', { class: 'dashicons ' + type.icon, 'aria-hidden': 'true' }));
    head.appendChild(make(doc, 'h2', { id: 'sgg-mce-title' }, i18n[type.titleKey]));
    var closeX = make(doc, 'button', { type: 'button', class: 'sgg-mce-x', 'aria-label': i18n.close || 'Close' }, '×');
    head.appendChild(closeX);
    var body = make(doc, 'div', { class: 'sgg-mce-body' });
    body.appendChild(make(doc, 'p', { class: 'sgg-mce-note' }, i18n.loading));
    var foot = make(doc, 'div', { class: 'sgg-mce-foot' });
    var cancel = make(doc, 'button', { type: 'button', class: 'sgg-mce-btn' }, i18n.cancel);
    var insert = make(doc, 'button', { type: 'button', class: 'sgg-mce-btn is-primary' }, i18n.insert);
    insert.disabled = true;
    foot.appendChild(cancel);
    foot.appendChild(insert);
    dialog.appendChild(head);
    dialog.appendChild(body);
    dialog.appendChild(foot);
    overlay.appendChild(dialog);
    doc.body.appendChild(overlay);
    closeX.focus();

    var select = null;

    function close() {
      doc.removeEventListener('keydown', onKey, true);
      if (overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
      if (lastFocus && lastFocus.focus) {
        try {
          lastFocus.focus();
        } catch (e) {}
      }
    }

    function doInsert() {
      if (!select || !select.value) {
        return;
      }
      var shortcode = '[' + type.shortcode + " id='" + parseInt(select.value, 10) + "']";
      close();
      editor.focus();
      if (bookmark) {
        try {
          editor.selection.setRng(bookmark);
        } catch (e) {}
      }
      editor.execCommand('mceInsertContent', false, shortcode);
      // Let the Classic block / Elementor pick up the new content right away
      editor.fire('change');
    }

    function onKey(event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close();
      } else if (event.key === 'Enter' && (event.target === select || (event.target && event.target.getAttribute('type') === 'search'))) {
        event.preventDefault();
        event.stopPropagation();
        doInsert();
      }
    }

    doc.addEventListener('keydown', onKey, true);
    closeX.addEventListener('click', close);
    cancel.addEventListener('click', close);
    insert.addEventListener('click', doInsert);
    overlay.addEventListener('mousedown', function (event) {
      if (event.target === overlay) {
        close();
      }
    });

    load(settings, function (data) {
      if (!overlay.parentNode) {
        return;
      }
      body.innerHTML = '';
      if (!data) {
        body.appendChild(make(doc, 'p', { class: 'sgg-mce-note' }, i18n.error));
        return;
      }
      var items = data[type.listKey] || [];
      if (!items.length) {
        body.appendChild(make(doc, 'p', { class: 'sgg-mce-note' }, i18n[type.emptyKey]));
      } else {
        var search = null;
        if (items.length > 15) {
          search = make(doc, 'input', { type: 'search', placeholder: i18n.search, 'aria-label': i18n.search });
          body.appendChild(search);
        }
        body.appendChild(make(doc, 'label', { for: 'sgg-mce-select' }, i18n[type.labelKey]));
        select = make(doc, 'select', { id: 'sgg-mce-select' });
        body.appendChild(select);
        var fill = function (filter) {
          var needle = (filter || '').toLowerCase();
          select.innerHTML = '';
          items.forEach(function (item) {
            var label = item.title + ' (#' + item.id + ')';
            if (!needle || label.toLowerCase().indexOf(needle) !== -1) {
              select.appendChild(make(doc, 'option', { value: String(item.id) }, label));
            }
          });
          if (!select.options.length) {
            select.appendChild(make(doc, 'option', { value: '' }, i18n.noMatch));
          }
          insert.disabled = !select.value;
        };
        fill('');
        if (search) {
          search.addEventListener('input', function () {
            fill(search.value);
          });
        }
        select.addEventListener('change', function () {
          insert.disabled = !select.value;
        });
        select.addEventListener('dblclick', doInsert);
        (search || select).focus();
      }
      if (settings[type.createKey]) {
        var create = make(doc, 'p', { class: 'sgg-mce-create' });
        create.appendChild(make(doc, 'a', { href: settings[type.createKey], target: '_blank', rel: 'noopener' }, i18n[type.createKey]));
        body.appendChild(create);
      }
    });
  }

  tinymce.PluginManager.add('supsysticGallery', function (editor) {
    var i18n = getSettings(editor).i18n || {};

    function button(typeName) {
      return {
        tooltip: i18n[TYPES[typeName].titleKey] || 'Photo Gallery',
        icon: 'dashicon ' + TYPES[typeName].icon,
        onclick: function () {
          openDialog(editor, typeName);
        },
        onPostRender: function () {
          var el = this.getEl();
          if (el) {
            el.className += ' mce-supsystic-btn';
            ensureStyle(el.ownerDocument);
          }
        },
      };
    }

    editor.addCommand('supsysticGallery', function () {
      openDialog(editor, 'gallery');
    });
    editor.addCommand('supsysticGalleryGroup', function () {
      openDialog(editor, 'group');
    });
    editor.addButton('supsysticGallery', button('gallery'));
    editor.addButton('supsysticGalleryGroup', button('group'));
    // Old ids: custom toolbars (e.g. TinyMCE Advanced) may list them. "selectShortcode" was also pushed by Data
    // Tables, so it is taken only if nobody has it yet.
    editor.addButton('addShortcode', button('gallery'));
    if (!editor.buttons || !editor.buttons.selectShortcode) {
      editor.addButton('selectShortcode', button('gallery'));
    }
  });
})();
