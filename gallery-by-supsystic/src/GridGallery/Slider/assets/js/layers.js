/**
 * Layer editor of the Slider: headings, text, buttons and badges over each image, dragged into place separately for
 * desktop, tablet and phone. Until a tablet or a phone gets values of its own, it follows the bigger device with the
 * text a little smaller and a set width a little wider (GridGallery_Slider_Module::follow() does the same). The layers live in the hidden field slider[layersJson] of the Layers card; each change goes to the live
 * preview and to Save like any other setting, and the server checks every value.
 *
 * Place: x and y are percents where 0 is the left or top edge and 100 the right or bottom edge (the layer moves back
 * by the same part of its own size), so a layer never leaves the image, whatever the screen.
 */
(function ($) {
  'use strict';

  var DEVICES = ['d', 't', 'm'];
  var ICONS = { d: 'fa-desktop', t: 'fa-tablet', m: 'fa-mobile' };
  // Screen heights for the "Part of the screen" height mode
  var SCREEN = { d: 900, t: 1180, m: 844 };
  var BOX_LIMITS = { x: [0, 100], y: [0, 100], w: [0, 100], size: [8, 200] };
  var SNAP = 1.5;

  function parse(text, fallback) {
    try {
      var value = JSON.parse(text);
      return value === null || value === undefined ? fallback : value;
    } catch (e) {
      return fallback;
    }
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function round1(value) {
    return Math.round(value * 10) / 10;
  }

  // "%d" and "%1$d" placeholders, as in the PHP strings
  function format(text) {
    var args = arguments,
      next = 0;
    return String(text).replace(/%(?:(\d)\$)?d/g, function (match, position) {
      next++;
      return String(args[position ? parseInt(position, 10) : next]);
    });
  }

  function newId() {
    return (Math.random().toString(36).slice(2, 10) + Date.now().toString(36)).slice(0, 10);
  }

  function normalizeExtras(value) {
    var out = {};
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      return out;
    }
    Object.keys(value).forEach(function (key) {
      if (value[key] && typeof value[key] === 'object' && !Array.isArray(value[key])) {
        out[key] = value[key];
      }
    });
    return out;
  }

  function normalize(value) {
    var out = {};
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      return out;
    }
    Object.keys(value).forEach(function (key) {
      if (Array.isArray(value[key]) && value[key].length) {
        out[key] = value[key];
      }
    });
    return out;
  }

  $(function () {
    var dialog = document.querySelector('[data-sgg-ly]'),
      input = document.querySelector('[data-sgg-ly-input]'),
      form = document.getElementById('sgg-sle-form');
    if (!dialog || !input || !form || typeof dialog.showModal !== 'function') {
      return;
    }

    function q(selector) {
      return dialog.querySelector(selector);
    }

    var slides = parse(dialog.getAttribute('data-slides'), []),
      defaults = parse(dialog.getAttribute('data-defaults'), {}),
      t = parse(dialog.getAttribute('data-i18n'), {}),
      limit = parseInt(dialog.getAttribute('data-limit'), 10) || 2,
      max = parseInt(dialog.getAttribute('data-max'), 10) || 20,
      animOpen = dialog.getAttribute('data-anim') === '1',
      videosOpen = dialog.getAttribute('data-videos') === '1',
      compareOpen = dialog.getAttribute('data-compare') === '1',
      slidesInput = document.querySelector('[data-sgg-ly-slides-input]'),
      extras = normalizeExtras(parse(slidesInput ? slidesInput.value : '', {})),
      mediaFrames = {},
      follows = parse(dialog.getAttribute('data-follow'), { t: [0.8, 1.1], m: [0.85, 1.3] }),
      data = normalize(parse(input.value, {})),
      state = { slide: slides.length ? String(slides[0].id) : '', layer: '', device: 'd', tab: 'layers' },
      drag = null,
      statusTimer = null,
      shown = '',
      summary = document.querySelector('[data-sgg-ly-summary]'),
      ui = {
        stage: q('[data-sgg-ly-stage]'),
        scaler: q('[data-sgg-ly-scaler]'),
        workspace: q('[data-sgg-ly-workspace]'),
        frame: q('[data-sgg-ly-frame]'),
        image: q('[data-sgg-ly-image]'),
        layers: q('[data-sgg-ly-layers]'),
        slides: q('[data-sgg-ly-slides]'),
        list: q('[data-sgg-ly-list]'),
        props: q('[data-sgg-ly-props]'),
        hint: q('[data-sgg-ly-hint]'),
        count: q('[data-sgg-ly-count]'),
        size: q('[data-sgg-ly-size]'),
        status: q('[data-sgg-ly-status]'),
        empty: q('[data-sgg-ly-empty]'),
        guideX: q('[data-sgg-ly-guide="x"]'),
        guideY: q('[data-sgg-ly-guide="y"]'),
        compare: q('[data-sgg-ly-compare]'),
        videoFlag: q('[data-sgg-ly-videoflag]'),
      };

    // ---------- Model
    function setting(path) {
      var field = form.querySelector('[name="slider' + path + '"]');
      return field ? field.value : '';
    }

    function currentSlide() {
      for (var i = 0; i < slides.length; i++) {
        if (String(slides[i].id) === state.slide) {
          return slides[i];
        }
      }
      return null;
    }

    function layersOf(slideId, create) {
      if (!data[slideId] && create) {
        data[slideId] = [];
      }
      return data[slideId] || [];
    }

    function currentLayer() {
      var list = layersOf(state.slide);
      for (var i = 0; i < list.length; i++) {
        if (list[i].id === state.layer) {
          return list[i];
        }
      }
      return null;
    }

    // The values of a device that has none of its own, from those of the bigger device
    function follow(box, device) {
      var copy = clone(box);
      copy.size = Math.max(Math.round(box.size * follows[device][0]), Math.min(box.size, 12));
      if (box.w > 0) {
        copy.w = Math.min(100, Math.round(box.w * follows[device][1]));
      }
      return copy;
    }

    // The values a layer has on a device: its own, or those that follow the bigger device
    function boxOf(layer, device) {
      if (device === 'd') {
        return layer.d;
      }
      var tablet = layer.t || follow(layer.d, 't');
      return device === 't' ? tablet : layer.m || follow(tablet, 'm');
    }

    // The values of a device to change: a tablet or a phone gets its own copy first
    function ownBox(layer, device) {
      if (device !== 'd' && !layer[device]) {
        layer[device] = clone(boxOf(layer, device));
      }
      return layer[device];
    }

    // The size the slider has on a device, from the Size card
    function geometry(device) {
      var unit = setting('[layout][widthUnit]'),
        width = parseInt(setting('[layout][width]'), 10) || 100,
        maxWidth = parseInt(setting('[layout][maxWidth]'), 10) || 0,
        mode = setting('[layout][heightMode]'),
        w,
        h;
      if (device === 'd') {
        w = clamp(unit === 'px' ? width : maxWidth > 0 ? maxWidth : 1200, 1024, 1600);
      } else if (device === 't') {
        w = maxWidth >= 768 && maxWidth < 1024 ? maxWidth : 820;
      } else {
        w = 390;
      }
      if (mode === 'fixed') {
        h = parseInt(setting('[layout][height][' + device + ']'), 10) || 400;
      } else if (mode === 'viewport') {
        h = Math.round((SCREEN[device] * (parseInt(setting('[layout][vh]'), 10) || 70)) / 100);
      } else if (mode === 'ratio') {
        var ratio = setting('[layout][ratio]').split(':');
        h = Math.round((w * (parseFloat(ratio[1]) || 9)) / (parseFloat(ratio[0]) || 16));
      } else {
        var slide = currentSlide();
        h = slide && slide.width && slide.height ? Math.round((w * slide.height) / slide.width) : Math.round((w * 9) / 16);
      }
      return { w: w, h: clamp(h, 80, 2400) };
    }

    // ---------- Settings of each slide: phone image, Before / After, video
    function extrasOf(slideId, create) {
      if (!extras[slideId] && create) {
        extras[slideId] = { mobile: 0, mobileUrl: '', compare: 0, compareUrl: '', before: '', after: '', start: 50, video: '', videoMode: 'click' };
      }
      return extras[slideId] || null;
    }

    function commitSlides() {
      Object.keys(extras).forEach(function (key) {
        var item = extras[key];
        if (!item.mobile && !item.compare && !String(item.video || '').trim()) {
          delete extras[key];
        }
      });
      if (slidesInput) {
        slidesInput.value = JSON.stringify(extras);
        $(slidesInput).trigger('change');
      }
      renderSlides();
    }

    function commit() {
      Object.keys(data).forEach(function (key) {
        if (!data[key].length) {
          delete data[key];
        }
      });
      input.value = JSON.stringify(data);
      $(input).trigger('change');
      renderSummary();
    }

    function setStatus(text) {
      ui.status.textContent = text || '';
      window.clearTimeout(statusTimer);
      if (text) {
        statusTimer = window.setTimeout(function () {
          ui.status.textContent = '';
        }, 3500);
      }
    }

    // ---------- Colors of the style: a layer without colors of its own takes them from the settings of the slider
    function styleColor(name, fallback) {
      var field = form.querySelector('[name="slider[style][' + name + ']"]');
      return field && /^#[0-9a-f]{6}/i.test(field.value) ? field.value.slice(0, 7) : fallback;
    }

    // White or dark ink, whichever reads better on a color (the same rule as readableOn() in PHP); a transparent
    // color counts as its opaque part
    function readableOn(color) {
      var hex = window.SggAlphaPicker ? window.SggAlphaPicker.opaque(color) : String(color);
      if (!/^#[0-9a-f]{6}/i.test(hex)) {
        return '#ffffff';
      }
      var light = 0;
      [0.2126, 0.7152, 0.0722].forEach(function (weight, index) {
        var channel = parseInt(hex.substr(1 + index * 2, 2), 16) / 255;
        light += weight * (channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4));
      });
      return light > 0.2 ? '#0f172a' : '#ffffff';
    }

    function bgOf(layer) {
      return layer.bg === 'auto' ? styleColor('accent', '#377dff') : layer.bg;
    }

    function colorOf(layer) {
      if (layer.color) {
        return layer.color;
      }
      if (layer.bg) {
        return readableOn(bgOf(layer));
      }
      return styleColor('captionColor', '#ffffff');
    }

    // ---------- Drawing
    function styleOf(layer) {
      var vars = [];
      DEVICES.forEach(function (device) {
        var box = boxOf(layer, device);
        vars.push('--x-' + device + ':' + box.x + '%');
        vars.push('--y-' + device + ':' + box.y + '%');
        vars.push('--w-' + device + ':' + (box.w > 0 ? box.w + '%' : 'max-content'));
        vars.push('--fs-' + device + ':' + box.size + 'px');
      });
      vars.push('--lc:' + colorOf(layer), '--lr:' + layer.radius + 'px', '--lw:' + layer.weight);
      if (layer.bg) {
        vars.push('--lb:' + bgOf(layer));
      }
      return vars.join(';');
    }

    function classOf(layer, index) {
      var names = ['sgg-sl-layer', 'sgg-sl-layer--' + layer.type, 'sgg-sl-layer-a-' + layer.align, 'sgg-ly-item'];
      if (layer.shadow) {
        names.push('sgg-sl-layer-shadow');
      }
      if (readableOn(colorOf(layer)) === '#ffffff') {
        names.push('sgg-sl-layer-ink-dark');
      }
      if (boxOf(layer, state.device).hide) {
        names.push('is-off');
      }
      if (index >= limit) {
        names.push('is-over');
      }
      if (layer.id === state.layer) {
        names.push('is-selected');
      }
      if (!String(layer.text).trim()) {
        names.push('is-blank');
      }
      return names.join(' ');
    }

    function fit() {
      if (!dialog.open || ui.scaler.hidden) {
        return;
      }
      var size = geometry(state.device),
        room = ui.workspace.getBoundingClientRect(),
        scale = Math.min(1, (room.width - 48) / size.w, room.height > 160 ? (room.height - 48) / size.h : 1);
      scale = Math.max(0.15, scale);
      ui.stage.style.transform = scale < 1 ? 'scale(' + scale + ')' : '';
      ui.scaler.style.width = Math.round(size.w * scale) + 'px';
      ui.scaler.style.height = Math.round(size.h * scale) + 'px';
    }

    function renderStage() {
      var slide = currentSlide();
      ui.empty.hidden = !!slide;
      ui.scaler.hidden = !slide;
      if (!slide) {
        ui.size.textContent = '';
        return;
      }
      var size = geometry(state.device);
      ui.stage.setAttribute('data-device', state.device);
      ui.stage.style.width = size.w + 'px';
      ui.frame.style.height = size.h + 'px';
      ui.stage.style.setProperty('--sgg-sl-fit', setting('[layout][fit]') || 'cover');
      ui.stage.style.setProperty('--sgg-sl-radius', (parseInt(setting('[style][radius]'), 10) || 0) + 'px');
      var extra = extrasOf(state.slide),
        source = state.device === 'm' && extra && extra.mobileUrl ? extra.mobileUrl : slide.src;
      if (ui.image.getAttribute('src') !== source) {
        ui.image.setAttribute('src', source);
      }
      // The stage shows the comparison only where the site does (PRO)
      ui.compare.hidden = !(compareOpen && extra && extra.compare && extra.compareUrl);
      if (!ui.compare.hidden) {
        ui.compare.firstChild.setAttribute('src', extra.compareUrl);
        ui.compare.style.setProperty('--sgg-cmp', (extra.start === undefined ? 50 : extra.start) + '%');
      }
      ui.videoFlag.hidden = !(extra && String(extra.video || '').trim() && !(compareOpen && extra.compare && extra.compareUrl));
      ui.image.alt = slide.title || '';
      ui.size.textContent = size.w + ' × ' + size.h + ' px';

      ui.layers.textContent = '';
      layersOf(state.slide).forEach(function (layer, index) {
        var node = document.createElement('div'),
          text = String(layer.text).trim() ? layer.text : t.empty;
        node.className = classOf(layer, index);
        node.setAttribute('style', styleOf(layer));
        node.setAttribute('data-id', layer.id);
        node.setAttribute('tabindex', '0');
        node.setAttribute('role', 'button');
        node.setAttribute('aria-label', t.types[layer.type] + ': ' + text);
        node.textContent = text;
        if (layer.id === state.layer) {
          var handle = document.createElement('span');
          handle.className = 'sgg-ly-handle';
          handle.setAttribute('data-sgg-ly-handle', '');
          handle.setAttribute('aria-hidden', 'true');
          node.appendChild(handle);
        }
        ui.layers.appendChild(node);
      });
      fit();
    }

    function renderSlides() {
      ui.slides.textContent = '';
      slides.forEach(function (slide, index) {
        var key = String(slide.id),
          count = layersOf(key).length,
          button = document.createElement('button'),
          image = document.createElement('img'),
          number = document.createElement('span');
        button.type = 'button';
        button.className = 'sgg-ly-thumb' + (key === state.slide ? ' is-active' : '');
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', key === state.slide ? 'true' : 'false');
        button.setAttribute('data-sgg-ly-slide', key);
        button.title = slide.title || '#' + (index + 1);
        image.src = slide.src;
        image.alt = '';
        image.loading = 'lazy';
        number.className = 'sgg-ly-thumb-num';
        number.textContent = String(index + 1);
        button.appendChild(image);
        button.appendChild(number);
        var extra = extrasOf(key);
        if (extra) {
          var flags = document.createElement('span');
          flags.className = 'sgg-ly-thumb-flags';
          [['mobile', 'fa-mobile'], ['compare', 'fa-columns'], ['video', 'fa-video-camera']].forEach(function (flag) {
            if (extra[flag[0]] && String(extra[flag[0]]).trim()) {
              var icon = document.createElement('i');
              icon.className = 'fa ' + flag[1];
              icon.title = t.flags[flag[0]];
              flags.appendChild(icon);
            }
          });
          if (flags.childNodes.length) {
            button.appendChild(flags);
          }
        }
        if (count) {
          var badge = document.createElement('span');
          badge.className = 'sgg-ly-thumb-count';
          badge.textContent = String(count);
          button.appendChild(badge);
        }
        ui.slides.appendChild(button);
      });
    }

    function renderDevices() {
      Array.prototype.forEach.call(dialog.querySelectorAll('[data-sgg-ly-device]'), function (button) {
        button.setAttribute('aria-pressed', button.getAttribute('data-sgg-ly-device') === state.device ? 'true' : 'false');
      });
    }

    function renderList() {
      var list = layersOf(state.slide),
        cap = Math.min(limit, max);
      ui.list.textContent = '';
      // The top layer first, as in design tools
      for (var i = list.length - 1; i >= 0; i--) {
        var layer = list[i],
          item = document.createElement('li'),
          button = document.createElement('button'),
          icon = document.createElement('i'),
          name = document.createElement('span'),
          text = document.createElement('span');
        button.type = 'button';
        button.className = 'sgg-ly-row' + (layer.id === state.layer ? ' is-selected' : '') + (i >= limit ? ' is-over' : '') + (boxOf(layer, state.device).hide ? ' is-off' : '');
        button.setAttribute('data-sgg-ly-pick', layer.id);
        button.setAttribute('aria-current', layer.id === state.layer ? 'true' : 'false');
        icon.className = 'fa ' + (t.icons[layer.type] || 'fa-square');
        icon.setAttribute('aria-hidden', 'true');
        name.className = 'sgg-ly-row-type';
        name.textContent = t.types[layer.type];
        text.className = 'sgg-ly-row-text';
        text.textContent = String(layer.text).trim() || t.empty;
        button.appendChild(icon);
        button.appendChild(name);
        button.appendChild(text);
        if (i >= limit) {
          button.title = t.over;
        } else if (boxOf(layer, state.device).hide) {
          var off = document.createElement('i');
          off.className = 'fa fa-eye-slash sgg-ly-row-off';
          off.title = t.hidden;
          button.appendChild(off);
        }
        item.appendChild(button);
        ui.list.appendChild(item);
      }
      ui.count.textContent = list.length + ' / ' + cap;
      ui.count.title = format(t.limit, cap);
      Array.prototype.forEach.call(dialog.querySelectorAll('[data-sgg-ly-add]'), function (button) {
        button.disabled = !state.slide || list.length >= cap;
        button.title = list.length >= cap ? format(t.limit, cap) : '';
      });
    }

    // A field being typed in keeps its value, unless the panel now shows another layer or device
    function setValue(field, value, force) {
      if (force || field !== document.activeElement) {
        field.value = value;
      }
    }

    function renderProps() {
      var layer = currentLayer(),
        list = layersOf(state.slide);
      ui.props.hidden = !layer;
      ui.hint.hidden = !!layer;
      if (!layer) {
        return;
      }
      var box = boxOf(layer, state.device),
        index = list.indexOf(layer),
        force = shown !== layer.id + state.device;
      shown = layer.id + state.device;

      q('[data-sgg-ly-props-title]').textContent = t.types[layer.type];
      Array.prototype.forEach.call(dialog.querySelectorAll('[data-prop]'), function (field) {
        var prop = field.getAttribute('data-prop'),
          value = layer[prop];
        var picked = field.hasAttribute('data-sgg-cp-value');
        if (picked) {
          // A color of the style shows the color it gives now; an own color keeps its transparency
          value = prop === 'bg' ? (layer.bg ? bgOf(layer) : styleColor('accent', '#000000')) : colorOf(layer);
        }
        setValue(field, value === undefined || value === null ? '' : value, force);
        if (picked && window.SggAlphaPicker) {
          window.SggAlphaPicker.paint(field);
        }
      });
      Array.prototype.forEach.call(dialog.querySelectorAll('[data-prop-switch]'), function (button) {
        button.setAttribute('aria-checked', layer[button.getAttribute('data-prop-switch')] ? 'true' : 'false');
      });
      Array.prototype.forEach.call(dialog.querySelectorAll('[data-prop-align]'), function (button) {
        button.setAttribute('aria-pressed', button.getAttribute('data-prop-align') === layer.align ? 'true' : 'false');
      });
      q('[data-sgg-ly-nobg]').setAttribute('aria-pressed', layer.bg ? 'false' : 'true');
      q('[data-sgg-ly-colorbox="bg"]').classList.toggle('is-none', !layer.bg);
      q('[data-sgg-ly-auto="bg"]').setAttribute('aria-pressed', layer.bg === 'auto' ? 'true' : 'false');
      q('[data-sgg-ly-auto="color"]').setAttribute('aria-pressed', layer.color ? 'false' : 'true');

      // Values of the device
      q('[data-sgg-ly-dev-icon]').className = 'fa ' + ICONS[state.device];
      q('[data-sgg-ly-dev-name]').textContent = t.devices[state.device];
      var inherit = q('[data-sgg-ly-inherit-row]'),
        reset = q('[data-sgg-ly-reset]');
      inherit.hidden = state.device === 'd';
      if (state.device !== 'd') {
        var own = !!layer[state.device];
        q('[data-sgg-ly-inherit]').textContent = own ? t.own : t.sameAs[state.device];
        inherit.classList.toggle('is-own', own);
        reset.hidden = !own;
        reset.textContent = t.reset[state.device];
      }
      Array.prototype.forEach.call(dialog.querySelectorAll('[data-box]'), function (field) {
        setValue(field, box[field.getAttribute('data-box')], force);
      });
      q('[data-box-switch="hide"]').setAttribute('aria-checked', box.hide ? 'true' : 'false');

      q('[data-sgg-ly-act="up"]').disabled = index >= list.length - 1;
      q('[data-sgg-ly-act="down"]').disabled = index <= 0;
      q('[data-sgg-ly-act="duplicate"]').disabled = list.length >= Math.min(limit, max);
      q('[data-sgg-ly-act="everywhere"]').disabled = slides.length < 2;
    }

    function renderSummary() {
      if (!summary) {
        return;
      }
      var withLayers = slides.filter(function (slide) {
        return layersOf(String(slide.id)).length > 0;
      }).length;
      summary.textContent = withLayers ? format(t.summary, withLayers, slides.length) : t.summaryNone;
    }

    function renderTabs() {
      Array.prototype.forEach.call(dialog.querySelectorAll('[data-sgg-ly-tab]'), function (tab) {
        tab.setAttribute('aria-selected', tab.getAttribute('data-sgg-ly-tab') === state.tab ? 'true' : 'false');
      });
      Array.prototype.forEach.call(dialog.querySelectorAll('[data-sgg-ly-pane]'), function (pane) {
        pane.hidden = pane.getAttribute('data-sgg-ly-pane') !== state.tab;
      });
    }

    var shownSlide = '';
    function renderSlidePane() {
      var extra = extrasOf(state.slide) || { start: 50, videoMode: 'click' },
        force = shownSlide !== state.slide;
      shownSlide = state.slide;
      ['mobile', 'compare'].forEach(function (kind) {
        var box = q('[data-sgg-ly-media="' + kind + '"]'),
          image = box.querySelector('img'),
          url = extra[kind + 'Url'];
        image.hidden = !url;
        if (url) {
          image.setAttribute('src', url);
        } else {
          image.removeAttribute('src');
        }
        box.querySelector('[data-sgg-ly-clear]').hidden = !extra[kind];
        box.querySelector('[data-sgg-ly-choose]').textContent = extra[kind] ? t.replace : t.chooseImage;
        // Before / After is PRO: the free version cannot choose the "after" image
        box.querySelector('[data-sgg-ly-choose]').disabled = !state.slide || (kind === 'compare' && !compareOpen);
      });
      q('[data-sgg-ly-compare-fields]').hidden = !extra.compare;
      Array.prototype.forEach.call(dialog.querySelectorAll('[data-slide-prop]'), function (field) {
        var value = extra[field.getAttribute('data-slide-prop')];
        setValue(field, value === undefined || value === null ? '' : value, force);
      });
    }

    function render() {
      renderTabs();
      renderSlidePane();
      renderDevices();
      renderSlides();
      renderStage();
      renderList();
      renderProps();
    }

    // After a change of a layer: redraw, then send it to the field
    function changed() {
      renderStage();
      renderList();
      renderProps();
      commit();
    }

    function select(id) {
      state.layer = id || '';
      renderStage();
      renderList();
      renderProps();
    }

    function focusLayer(id) {
      var node = ui.layers.querySelector('[data-id="' + id + '"]');
      if (node) {
        node.focus({ preventScroll: true });
      }
    }

    // ---------- Media Library: the slide editor steps aside while it is open, as a modal dialog sits above all
    function choose(kind) {
      if (!window.wp || !window.wp.media || !state.slide) {
        return;
      }
      var frame = mediaFrames[kind];
      if (!frame) {
        frame = mediaFrames[kind] = window.wp.media({
          title: t.chooseTitle[kind],
          library: { type: kind === 'video' ? 'video' : 'image' },
          multiple: false,
          button: { text: t.use },
        });
        frame.on('select', function () {
          var item = frame.state().get('selection').first().toJSON(),
            extra = extrasOf(frame.sggSlide, true),
            sizes = item.sizes || {};
          if (kind === 'video') {
            extra.video = item.url;
          } else {
            extra[kind] = item.id;
            extra[kind + 'Url'] = (sizes.large || sizes.full || item).url;
            if (kind === 'compare') {
              extra.before = extra.before || t.before;
              extra.after = extra.after || t.after;
            }
          }
          frame.sggPicked = true;
        });
        frame.on('close', function () {
          window.setTimeout(function () {
            if (!dialog.open) {
              dialog.showModal();
            }
            shownSlide = '';
            if (frame.sggPicked) {
              frame.sggPicked = false;
              commitSlides();
            }
            render();
          }, 0);
        });
      }
      frame.sggSlide = state.slide;
      frame.sggPicked = false;
      dialog.close();
      frame.open();
    }

    // ---------- Actions
    function add(type) {
      if (!state.slide || !defaults[type]) {
        return;
      }
      var list = layersOf(state.slide, true),
        cap = Math.min(limit, max);
      if (list.length >= cap) {
        setStatus(format(t.limit, cap));
        return;
      }
      var layer = clone(defaults[type]),
        same = list.filter(function (item) {
          return item.type === type;
        }).length;
      layer.id = newId();
      layer.type = type;
      layer.text = t.samples[type] || '';
      layer.link = '';
      layer.newTab = 0;
      // A new layer of a type already on the slide goes a little lower, so it does not hide the first one
      if (same) {
        DEVICES.forEach(function (device) {
          if (layer[device]) {
            layer[device].y = clamp(layer[device].y + same * 8, 0, 100);
          }
        });
      }
      list.push(layer);
      state.layer = layer.id;
      renderSlides();
      changed();
      var text = q('[data-prop="text"]');
      text.focus();
      text.select();
    }

    function act(action, layer) {
      var list = layersOf(state.slide),
        index = list.indexOf(layer),
        cap = Math.min(limit, max);
      if (index < 0) {
        return;
      }
      if (action === 'delete') {
        list.splice(index, 1);
        state.layer = '';
      } else if (action === 'up' && index < list.length - 1) {
        list.splice(index + 1, 0, list.splice(index, 1)[0]);
      } else if (action === 'down' && index > 0) {
        list.splice(index - 1, 0, list.splice(index, 1)[0]);
      } else if (action === 'duplicate') {
        if (list.length >= cap) {
          setStatus(format(t.limit, cap));
          return;
        }
        var copy = clone(layer);
        copy.id = newId();
        DEVICES.forEach(function (device) {
          if (copy[device]) {
            copy[device].y = clamp(copy[device].y + 6, 0, 100);
          }
        });
        list.splice(index + 1, 0, copy);
        state.layer = copy.id;
      } else if (action === 'everywhere') {
        var copied = 0;
        slides.forEach(function (slide) {
          var key = String(slide.id);
          if (key === state.slide || layersOf(key).length >= cap) {
            return;
          }
          var item = clone(layer);
          item.id = newId();
          layersOf(key, true).push(item);
          copied++;
        });
        setStatus(copied ? format(t.copied, copied) : t.noRoom);
        if (!copied) {
          return;
        }
      } else {
        return;
      }
      renderSlides();
      changed();
    }

    // ---------- Dragging on the image: move, or pull the right edge for the width
    function snap(value) {
      var targets = [0, 50, 100];
      for (var i = 0; i < targets.length; i++) {
        if (Math.abs(value - targets[i]) < SNAP) {
          return { value: targets[i], hit: true };
        }
      }
      return { value: value, hit: false };
    }

    function guide(line, on, value, vertical) {
      line.classList.toggle('is-on', on);
      if (on) {
        line.style[vertical ? 'left' : 'top'] = 'calc(' + value + '% - ' + value / 100 + 'px)';
      }
    }

    ui.frame.addEventListener('pointerdown', function (event) {
      if (event.button !== 0) {
        return;
      }
      var node = event.target.closest('.sgg-ly-item');
      if (!node) {
        select('');
        return;
      }
      var id = node.getAttribute('data-id'),
        mode = event.target.closest('[data-sgg-ly-handle]') ? 'width' : 'move';
      if (state.layer !== id) {
        select(id);
        node = ui.layers.querySelector('[data-id="' + id + '"]');
      }
      var layer = currentLayer();
      if (!layer || !node) {
        return;
      }
      event.preventDefault();
      node.focus({ preventScroll: true });
      drag = {
        mode: mode,
        node: node,
        layer: layer,
        pointer: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        frame: ui.frame.getBoundingClientRect(),
        rect: node.getBoundingClientRect(),
        box: clone(boxOf(layer, state.device)),
        moved: false,
        value: null,
      };
      node.setPointerCapture(event.pointerId);
    });

    ui.frame.addEventListener('pointermove', function (event) {
      if (!drag || event.pointerId !== drag.pointer) {
        return;
      }
      var dx = event.clientX - drag.startX,
        dy = event.clientY - drag.startY,
        frame = drag.frame,
        rect = drag.rect;
      if (!drag.moved && Math.abs(dx) < 3 && Math.abs(dy) < 3) {
        return;
      }
      drag.moved = true;
      if (drag.mode === 'width') {
        // The right edge moves by (1 - x) of the change of width, so the change is scaled back to follow the pointer
        var share = Math.max(0.25, 1 - drag.box.x / 100),
          width = clamp(Math.round(((rect.width + dx / share) / frame.width) * 100), 5, 100);
        drag.value = { w: width };
        drag.node.style.setProperty('--w-' + state.device, width + '%');
        return;
      }
      var freeX = frame.width - rect.width,
        freeY = frame.height - rect.height,
        left = clamp(rect.left - frame.left + dx, 0, Math.max(0, freeX)),
        top = clamp(rect.top - frame.top + dy, 0, Math.max(0, freeY)),
        x = snap(freeX > 0 ? (left / freeX) * 100 : drag.box.x),
        y = snap(freeY > 0 ? (top / freeY) * 100 : drag.box.y);
      guide(ui.guideX, x.hit, x.value, true);
      guide(ui.guideY, y.hit, y.value, false);
      drag.value = { x: round1(x.value), y: round1(y.value) };
      drag.node.style.setProperty('--x-' + state.device, drag.value.x + '%');
      drag.node.style.setProperty('--y-' + state.device, drag.value.y + '%');
    });

    function endDrag(event) {
      if (!drag || event.pointerId !== drag.pointer) {
        return;
      }
      var done = drag;
      drag = null;
      ui.guideX.classList.remove('is-on');
      ui.guideY.classList.remove('is-on');
      if (done.moved && done.value) {
        var box = ownBox(done.layer, state.device);
        Object.keys(done.value).forEach(function (key) {
          box[key] = done.value[key];
        });
        changed();
        focusLayer(done.layer.id);
      }
    }
    ui.frame.addEventListener('pointerup', endDrag);
    ui.frame.addEventListener('pointercancel', endDrag);

    ui.frame.addEventListener('dblclick', function (event) {
      if (event.target.closest('.sgg-ly-item')) {
        var text = q('[data-prop="text"]');
        text.focus();
        text.select();
      }
    });

    // Keys on a chosen layer: arrows move it, Delete removes it, Enter goes to its text
    ui.layers.addEventListener('keydown', function (event) {
      var node = event.target.closest('.sgg-ly-item');
      if (!node) {
        return;
      }
      var id = node.getAttribute('data-id');
      if (state.layer !== id) {
        select(id);
        focusLayer(id);
      }
      var layer = currentLayer(),
        step = event.shiftKey ? 5 : 0.5,
        keys = { ArrowLeft: ['x', -step], ArrowRight: ['x', step], ArrowUp: ['y', -step], ArrowDown: ['y', step] };
      if (!layer) {
        return;
      }
      if (keys[event.key]) {
        event.preventDefault();
        var box = ownBox(layer, state.device),
          key = keys[event.key][0];
        box[key] = round1(clamp(box[key] + keys[event.key][1], 0, 100));
        changed();
        focusLayer(id);
      } else if (event.key === 'Delete' || event.key === 'Backspace') {
        event.preventDefault();
        act('delete', layer);
      } else if (event.key === 'Enter') {
        event.preventDefault();
        q('[data-prop="text"]').focus();
      }
    });

    // ---------- Panel
    function onSlideField(field) {
      var prop = field.getAttribute('data-slide-prop'),
        extra;
      if (!state.slide || (prop === 'video' && !videosOpen) || (!compareOpen && (prop === 'before' || prop === 'after' || prop === 'start'))) {
        return;
      }
      extra = extrasOf(state.slide, true);
      if (prop === 'start') {
        extra.start = clamp(parseInt(field.value, 10) || 0, 0, 100);
        Array.prototype.forEach.call(dialog.querySelectorAll('[data-slide-prop="start"]'), function (twin) {
          if (twin !== field) {
            twin.value = extra.start;
          }
        });
      } else {
        extra[prop] = field.value;
      }
      renderStage();
      commitSlides();
    }

    function onField(event) {
      if (event.target.hasAttribute('data-slide-prop')) {
        onSlideField(event.target);
        return;
      }
      var field = event.target,
        layer = currentLayer(),
        prop = field.getAttribute('data-prop'),
        key = field.getAttribute('data-box');
      if (!layer || (!prop && !key)) {
        return;
      }
      if (prop) {
        var value = field.value;
        if (prop === 'weight') {
          value = parseInt(value, 10) || 400;
        } else if (prop === 'radius') {
          value = clamp(parseInt(value, 10) || 0, 0, 100);
        } else if (prop === 'delay') {
          value = clamp(parseInt(value, 10) || 0, 0, 3000);
        } else if (prop === 'anim' && !animOpen) {
          value = 'none';
        }
        layer[prop] = value;
      } else {
        var number = parseFloat(field.value);
        if (isNaN(number)) {
          return;
        }
        var box = ownBox(layer, state.device);
        number = clamp(number, BOX_LIMITS[key][0], BOX_LIMITS[key][1]);
        box[key] = key === 'x' || key === 'y' ? round1(number) : Math.round(number);
      }
      renderStage();
      renderList();
      renderProps();
      commit();
    }
    dialog.addEventListener('input', onField);
    dialog.addEventListener('change', onField);

    // Text, background, button and badge colors with transparency; the quick colors start with those of the style
    if (window.SggAlphaPicker) {
      window.SggAlphaPicker.attach(dialog, {
        labels: t.picker || {},
        swatches: function () {
          return ['accent', 'captionBg', 'captionColor', 'arrowBg', 'arrowColor'].map(function (name) {
            return styleColor(name, '');
          });
        },
      });
    }

    dialog.addEventListener('click', function (event) {
      var target = event.target,
        el;
      if ((el = target.closest('[data-sgg-ly-device]'))) {
        state.device = el.getAttribute('data-sgg-ly-device');
        render();
        return;
      }
      if ((el = target.closest('[data-sgg-ly-slide]'))) {
        state.slide = el.getAttribute('data-sgg-ly-slide');
        state.layer = '';
        render();
        return;
      }
      if ((el = target.closest('[data-sgg-ly-add]'))) {
        add(el.getAttribute('data-sgg-ly-add'));
        return;
      }
      if ((el = target.closest('[data-sgg-ly-pick]'))) {
        select(el.getAttribute('data-sgg-ly-pick'));
        return;
      }
      if (target.closest('[data-sgg-ly-close]')) {
        dialog.close();
        return;
      }
      if ((el = target.closest('[data-sgg-ly-tab]'))) {
        state.tab = el.getAttribute('data-sgg-ly-tab');
        renderTabs();
        return;
      }
      if ((el = target.closest('[data-sgg-ly-choose]'))) {
        choose(el.getAttribute('data-sgg-ly-choose'));
        return;
      }
      if ((el = target.closest('[data-sgg-ly-clear]'))) {
        var kind = el.getAttribute('data-sgg-ly-clear'),
          extra = extrasOf(state.slide);
        if (extra) {
          extra[kind] = 0;
          extra[kind + 'Url'] = '';
          commitSlides();
          renderStage();
          renderSlidePane();
        }
        return;
      }
      var layer = currentLayer();
      if (!layer) {
        return;
      }
      if ((el = target.closest('[data-prop-switch]'))) {
        var prop = el.getAttribute('data-prop-switch');
        layer[prop] = layer[prop] ? 0 : 1;
      } else if (target.closest('[data-box-switch]')) {
        var box = ownBox(layer, state.device);
        box.hide = box.hide ? 0 : 1;
      } else if ((el = target.closest('[data-prop-align]'))) {
        layer.align = el.getAttribute('data-prop-align');
      } else if (target.closest('[data-sgg-ly-nobg]')) {
        // Back from no background: the accent of the style
        layer.bg = layer.bg ? '' : 'auto';
      } else if ((el = target.closest('[data-sgg-ly-auto]'))) {
        // The brush: the color of the style, or (pressed again) the same color kept as the layer's own
        if (el.getAttribute('data-sgg-ly-auto') === 'bg') {
          layer.bg = layer.bg === 'auto' ? bgOf(layer) : 'auto';
        } else {
          layer.color = layer.color ? '' : colorOf(layer);
        }
      } else if (target.closest('[data-sgg-ly-reset]')) {
        layer[state.device] = null;
      } else if ((el = target.closest('[data-sgg-ly-act]'))) {
        act(el.getAttribute('data-sgg-ly-act'), layer);
        return;
      } else {
        return;
      }
      changed();
    });

    // ---------- Open and close
    $(document).on('click', '[data-sgg-ly-open]', function () {
      var active = document.querySelector('.sgg-sle [data-sgg-sle-device].is-active');
      state.device = active ? active.getAttribute('data-sgg-sle-device') : 'd';
      if (!currentSlide()) {
        state.slide = slides.length ? String(slides[0].id) : '';
        state.layer = '';
      }
      dialog.showModal();
      render();
    });
    dialog.addEventListener('close', function () {
      drag = null;
    });
    if (window.ResizeObserver) {
      new ResizeObserver(fit).observe(ui.workspace);
    }

    renderSummary();
  });
})(jQuery);
