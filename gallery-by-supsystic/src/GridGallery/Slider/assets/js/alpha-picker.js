/**
 * Color picker with transparency for the layer editor of the Slider: a saturation square, a hue strip, an opacity
 * slider over a checkerboard, the color code and quick colors (those of the style first). An opaque color stays a
 * hex code as before; a transparent one is saved as rgba(r, g, b, a), which GridGallery_Slider_Module::layerColor()
 * checks on the server.
 *
 * Markup of a field: [data-sgg-cp] holding a button [data-sgg-cp-open] (the chip) and a hidden input
 * [data-sgg-cp-value]. A change writes the input and sends it an "input" event, like any other field.
 * SggAlphaPicker.attach(root, {swatches: function () { return [colors] }, labels: {...}}) sets up the fields of root;
 * SggAlphaPicker.paint(input) shows the value of the input on its chip after it was set from outside.
 */
(function (window, document) {
  'use strict';

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function hex2(number) {
    return ('0' + Math.round(clamp(number, 0, 255)).toString(16)).slice(-2);
  }

  /**
   * @param {string} value #rgb, #rrggbb, #rrggbbaa, rgb() or rgba()
   * @returns {{r: number, g: number, b: number, a: number}|null}
   */
  function parse(value) {
    var text = String(value || '')
        .trim()
        .toLowerCase(),
      match;
    if ((match = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec(text))) {
      return { r: parseInt(match[1] + match[1], 16), g: parseInt(match[2] + match[2], 16), b: parseInt(match[3] + match[3], 16), a: 1 };
    }
    if ((match = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})?$/.exec(text))) {
      return {
        r: parseInt(match[1], 16),
        g: parseInt(match[2], 16),
        b: parseInt(match[3], 16),
        a: match[4] ? Math.round((parseInt(match[4], 16) / 255) * 100) / 100 : 1,
      };
    }
    if ((match = /^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(\d*\.?\d+)(%?)\s*)?\)$/.exec(text))) {
      var alpha = match[4] === undefined ? 1 : parseFloat(match[4]) / (match[5] ? 100 : 1);
      if (+match[1] > 255 || +match[2] > 255 || +match[3] > 255) {
        return null;
      }
      return { r: +match[1], g: +match[2], b: +match[3], a: clamp(isNaN(alpha) ? 1 : alpha, 0, 1) };
    }
    return null;
  }

  /**
   * Opaque: #rrggbb (the old values stay as they were); with transparency: rgba(r, g, b, a).
   */
  function format(color) {
    var alpha = Math.round(clamp(color.a, 0, 1) * 100) / 100;
    if (alpha >= 1) {
      return '#' + hex2(color.r) + hex2(color.g) + hex2(color.b);
    }
    return 'rgba(' + Math.round(color.r) + ', ' + Math.round(color.g) + ', ' + Math.round(color.b) + ', ' + alpha + ')';
  }

  // The same color without transparency, for the ink that reads on it
  function opaque(value) {
    var color = parse(value);
    return color ? '#' + hex2(color.r) + hex2(color.g) + hex2(color.b) : value;
  }

  function toHsv(color) {
    var r = color.r / 255,
      g = color.g / 255,
      b = color.b / 255,
      max = Math.max(r, g, b),
      min = Math.min(r, g, b),
      delta = max - min,
      h = 0;
    if (delta) {
      if (max === r) {
        h = ((g - b) / delta) % 6;
      } else if (max === g) {
        h = (b - r) / delta + 2;
      } else {
        h = (r - g) / delta + 4;
      }
      h = (h * 60 + 360) % 360;
    }
    return { h: h, s: max ? delta / max : 0, v: max };
  }

  function fromHsv(h, s, v) {
    var c = v * s,
      x = c * (1 - Math.abs(((h / 60) % 2) - 1)),
      m = v - c,
      rgb = h < 60 ? [c, x, 0] : h < 120 ? [x, c, 0] : h < 180 ? [0, c, x] : h < 240 ? [0, x, c] : h < 300 ? [x, 0, c] : [c, 0, x];
    return { r: (rgb[0] + m) * 255, g: (rgb[1] + m) * 255, b: (rgb[2] + m) * 255 };
  }

  var pop = null,
    current = null,
    hsv = { h: 0, s: 0, v: 1 },
    alpha = 1,
    settings = { swatches: null, labels: {} };

  function el(tag, className, attrs) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    Object.keys(attrs || {}).forEach(function (name) {
      node.setAttribute(name, attrs[name]);
    });
    return node;
  }

  function label(key, fallback) {
    return (settings.labels && settings.labels[key]) || fallback;
  }

  // The chip shows the color over a checkerboard, so transparency can be seen
  function paint(input) {
    var box = input && input.closest('[data-sgg-cp]'),
      chip = box && box.querySelector('[data-sgg-cp-open] i');
    if (chip) {
      chip.style.background = input.value || 'transparent';
    }
  }

  function build(root) {
    pop = el('div', 'sgg-cp-pop', { role: 'dialog', 'aria-label': label('picker', 'Color'), hidden: '' });
    pop.innerHTML =
      '<div class="sgg-cp-sv" data-sgg-cp-sv tabindex="0" role="slider" aria-label="' + label('shade', 'Saturation and brightness') + '"><i class="sgg-cp-sv-knob"></i></div>' +
      '<input type="range" class="sgg-cp-hue" min="0" max="359" step="1" data-sgg-cp-hue aria-label="' + label('hue', 'Hue') + '">' +
      '<div class="sgg-cp-alpha-row"><span class="sgg-cp-alpha-track"><input type="range" class="sgg-cp-alpha" min="0" max="100" step="1" data-sgg-cp-alpha aria-label="' + label('opacity', 'Opacity') + '"></span><output data-sgg-cp-alpha-out>100%</output></div>' +
      '<div class="sgg-cp-code"><i class="sgg-cp-now" data-sgg-cp-now></i><input type="text" spellcheck="false" data-sgg-cp-text aria-label="' + label('code', 'Color code') + '"></div>' +
      '<div class="sgg-cp-swatches" data-sgg-cp-swatches></div>';
    root.appendChild(pop);

    var sv = pop.querySelector('[data-sgg-cp-sv]'),
      dragging = false;
    function fromPoint(event) {
      var rect = sv.getBoundingClientRect();
      hsv.s = clamp((event.clientX - rect.left) / rect.width, 0, 1);
      hsv.v = 1 - clamp((event.clientY - rect.top) / rect.height, 0, 1);
      if (alpha === 0) {
        alpha = 1;
      }
      emit();
    }
    sv.addEventListener('pointerdown', function (event) {
      dragging = true;
      sv.setPointerCapture(event.pointerId);
      fromPoint(event);
    });
    sv.addEventListener('pointermove', function (event) {
      if (dragging) {
        fromPoint(event);
      }
    });
    sv.addEventListener('pointerup', function () {
      dragging = false;
    });
    sv.addEventListener('keydown', function (event) {
      var step = event.shiftKey ? 0.1 : 0.02,
        moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] },
        move = moves[event.key];
      if (move) {
        event.preventDefault();
        hsv.s = clamp(hsv.s + move[0], 0, 1);
        hsv.v = clamp(hsv.v + move[1], 0, 1);
        emit();
      }
    });
    pop.querySelector('[data-sgg-cp-hue]').addEventListener('input', function () {
      hsv.h = parseFloat(this.value) || 0;
      emit();
    });
    pop.querySelector('[data-sgg-cp-alpha]').addEventListener('input', function () {
      alpha = clamp((parseFloat(this.value) || 0) / 100, 0, 1);
      emit();
    });
    var text = pop.querySelector('[data-sgg-cp-text]');
    function fromText() {
      var color = parse(text.value);
      if (color) {
        load(color);
        emit(true);
      } else {
        text.value = format(rgba());
      }
    }
    text.addEventListener('change', fromText);
    text.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        fromText();
      }
    });
    pop.addEventListener('click', function (event) {
      var swatch = event.target.closest('[data-sgg-cp-swatch]');
      if (swatch) {
        load(parse(swatch.getAttribute('data-sgg-cp-swatch')));
        emit();
      }
    });
    // Escape closes the picker, not the layer editor around it
    pop.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        var chip = current && current.closest('[data-sgg-cp]').querySelector('[data-sgg-cp-open]');
        close();
        if (chip) {
          chip.focus();
        }
      }
    });
  }

  function rgba() {
    var color = fromHsv(hsv.h, hsv.s, hsv.v);
    color.a = alpha;
    return color;
  }

  function load(color) {
    if (!color) {
      return;
    }
    var next = toHsv(color);
    // A grey has no hue of its own: keep the one of the strip
    if (next.s === 0 || next.v === 0) {
      next.h = hsv.h;
    }
    hsv = next;
    alpha = color.a;
  }

  // Draws the picker for the current color; the field gets the value and an input event unless only drawing
  function draw(keepText) {
    var color = rgba(),
      solid = '#' + hex2(color.r) + hex2(color.g) + hex2(color.b),
      value = format(color);
    pop.style.setProperty('--cp-hue', 'hsl(' + Math.round(hsv.h) + ', 100%, 50%)');
    pop.style.setProperty('--cp-solid', solid);
    var knob = pop.querySelector('.sgg-cp-sv-knob');
    knob.style.left = hsv.s * 100 + '%';
    knob.style.top = (1 - hsv.v) * 100 + '%';
    knob.style.background = solid;
    pop.querySelector('[data-sgg-cp-hue]').value = Math.round(hsv.h);
    pop.querySelector('[data-sgg-cp-alpha]').value = Math.round(alpha * 100);
    pop.querySelector('[data-sgg-cp-alpha-out]').textContent = Math.round(alpha * 100) + '%';
    // The color over a checkerboard, in one background
    pop.querySelector('[data-sgg-cp-now]').style.background = 'linear-gradient(' + value + ', ' + value + '), repeating-conic-gradient(#cbd5e1 0% 25%, #fff 0% 50%) 0 0 / 8px 8px';
    if (!keepText) {
      pop.querySelector('[data-sgg-cp-text]').value = value;
    }
    return value;
  }

  function emit(keepText) {
    if (!current) {
      return;
    }
    current.value = draw(keepText);
    paint(current);
    current.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function swatches() {
    var list = [],
      box = pop.querySelector('[data-sgg-cp-swatches]');
    (typeof settings.swatches === 'function' ? settings.swatches() : []).concat(['#ffffff', '#0f172a', 'rgba(15, 23, 42, 0.6)', 'rgba(255, 255, 255, 0.7)', 'rgba(0, 0, 0, 0.35)']).forEach(function (value) {
      var color = parse(value);
      if (color && list.indexOf(format(color)) === -1) {
        list.push(format(color));
      }
    });
    box.innerHTML = '';
    list.slice(0, 12).forEach(function (value) {
      var button = el('button', 'sgg-cp-swatch', { type: 'button', 'data-sgg-cp-swatch': value, title: value, 'aria-label': value });
      button.appendChild(el('i'));
      button.firstChild.style.background = value;
      box.appendChild(button);
    });
  }

  function place(chip) {
    var rect = chip.getBoundingClientRect(),
      width = pop.offsetWidth || 248,
      height = pop.offsetHeight || 300,
      left = clamp(rect.left, 8, window.innerWidth - width - 8),
      top = rect.bottom + 6;
    if (top + height > window.innerHeight - 8) {
      top = Math.max(8, rect.top - height - 6);
    }
    pop.style.left = Math.round(left) + 'px';
    pop.style.top = Math.round(top) + 'px';
  }

  function open(chip) {
    var input = chip.closest('[data-sgg-cp]').querySelector('[data-sgg-cp-value]');
    if (current === input && !pop.hidden) {
      close();
      return;
    }
    current = input;
    load(parse(input.value) || { r: 255, g: 255, b: 255, a: 1 });
    swatches();
    draw();
    pop.hidden = false;
    chip.setAttribute('aria-expanded', 'true');
    place(chip);
  }

  function close() {
    if (!pop || pop.hidden) {
      return;
    }
    pop.hidden = true;
    if (current) {
      var chip = current.closest('[data-sgg-cp]').querySelector('[data-sgg-cp-open]');
      if (chip) {
        chip.setAttribute('aria-expanded', 'false');
      }
    }
    current = null;
  }

  function attach(root, options) {
    if (!root || root.hasAttribute('data-sgg-cp-ready')) {
      return;
    }
    root.setAttribute('data-sgg-cp-ready', '');
    settings = options || settings;
    build(root);
    root.addEventListener('click', function (event) {
      var chip = event.target.closest('[data-sgg-cp-open]');
      if (chip && root.contains(chip)) {
        open(chip);
        return;
      }
      if (!pop.hidden && !pop.contains(event.target)) {
        close();
      }
    });
    // Escape of a <dialog> would close the whole layer editor while the picker is open
    root.addEventListener('cancel', function (event) {
      if (!pop.hidden) {
        event.preventDefault();
        close();
      }
    });
    // The picker follows its chip when the panel scrolls, and goes when the window changes
    root.addEventListener(
      'scroll',
      function () {
        if (!pop.hidden && current) {
          place(current.closest('[data-sgg-cp]').querySelector('[data-sgg-cp-open]'));
        }
      },
      true
    );
    window.addEventListener('resize', close);
    root.addEventListener('close', close);
  }

  window.SggAlphaPicker = { attach: attach, paint: paint, parse: parse, format: format, opaque: opaque, close: close };
})(window, document);
