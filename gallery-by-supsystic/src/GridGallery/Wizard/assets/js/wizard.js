/**
 * Creation wizard of Photo Gallery: goal, images, style with a live picture, additional options, name. The pictures
 * of the goals and styles come from SggStyleMock (Styles module), so each goal shows its own layout and each style
 * its shape on it; the switches of the Additional step are drawn on the same picture at once.
 * "Create gallery" sends the answers to the wizard module, which makes the gallery with its type, settings, style,
 * options and images; then the editor opens.
 */
(function ($) {
  'use strict';

  // Neutral colors of the goal pictures: the layout speaks, not the colors
  var NEUTRAL = { head: '#1d2b3c', headText: '#ffffff', surface: '#ffffff', text: '#475569', stripe: '#eef2f6', accent: '#0e8a93', border: '#dde3ea' };

  $(function () {
    var root = document.querySelector('[data-sgg-wz]');
    if (!root || !window.SggStyleMock) {
      return;
    }
    var config = {};
    try {
      config = JSON.parse(root.getAttribute('data-config')) || {};
    } catch (e) {
      return;
    }
    var goals = config.goals || {},
      labels = config.labels || {},
      // extras: the switches of the Additional step; trying: a closed PRO option shown on the picture while the
      // mouse is over it
      state = { step: 'goal', goal: '', images: [], style: '', tier: 'all', collection: '', extras: {}, trying: '' },
      frame = null,
      dragFrom = null;

    function q(selector) {
      return root.querySelector(selector);
    }

    function all(selector) {
      return Array.prototype.slice.call(root.querySelectorAll(selector));
    }

    function msg(name) {
      return root.getAttribute('data-msg-' + name) || '';
    }

    function goal() {
      return goals[state.goal] || null;
    }

    function kind() {
      return goal() ? goal().kind : 'gallery';
    }

    function steps() {
      var list = ['goal'];
      if (!goal() || !goal().noImages) {
        list.push('images');
      }
      list.push('style');
      if (!goal() || Object.keys(goal().extras || {}).length) {
        list.push('extras');
      }
      list.push('name');
      return list;
    }

    // ---------- Options of the Additional step
    function options() {
      return (config.extras && config.extras[kind()]) || {};
    }

    // The options that cannot work together with one: the ones it turns off and the ones that turn it off
    function conflicts(id) {
      var list = options(),
        out = ((list[id] && list[id].off) || []).slice();
      Object.keys(list).forEach(function (other) {
        if ((list[other].off || []).indexOf(id) !== -1 && out.indexOf(other) === -1) {
          out.push(other);
        }
      });
      return out;
    }

    // Turns an option on or off in a set: on turns off what cannot work with it and on what it needs; off turns off
    // what needs it
    function setExtra(set, id, on) {
      var list = options();
      set[id] = on;
      if (on) {
        conflicts(id).forEach(function (other) {
          if (other in set) {
            set[other] = false;
          }
        });
        var needs = list[id] && list[id].needs;
        if (needs && needs in set) {
          set[needs] = true;
        }
      } else {
        Object.keys(set).forEach(function (other) {
          if (list[other] && list[other].needs === id) {
            set[other] = false;
          }
        });
      }
      return set;
    }

    // What the picture shows: the chosen options, and a closed PRO option while it is tried
    function shownExtras(withTry) {
      var list = options(),
        set = {};
      Object.keys(state.extras).forEach(function (id) {
        set[id] = !!state.extras[id] && !(list[id] && list[id].locked);
      });
      if (withTry && state.trying && state.trying in set) {
        setExtra(set, state.trying, true);
      }
      return set;
    }

    function chosenNames() {
      var list = options(),
        set = shownExtras(false);
      return Object.keys(set).filter(function (id) {
        return set[id] && list[id];
      }).map(function (id) {
        return list[id].name;
      });
    }

    function stylesOf(type) {
      return (config.styles && config.styles[type || kind()]) || [];
    }

    function styleById(id, type) {
      var list = stylesOf(type);
      for (var i = 0; i < list.length; i++) {
        if (list[i].id === id) {
          return list[i];
        }
      }
      return list[0] || null;
    }

    function picture(type, style, options) {
      return window.SggStyleMock.html(type, style.swatch, $.extend({}, style.shape), options);
    }

    // ---------- Goal cards: the layout of each goal in neutral colors and the shape of its own style
    function renderGoalPictures() {
      all('[data-wz-goal-pic]').forEach(function (box) {
        var id = box.getAttribute('data-wz-goal-pic'),
          item = goals[id];
        if (!item) {
          return;
        }
        var style = styleById(item.style, item.kind) || { shape: {} };
        box.innerHTML = window.SggStyleMock.html(item.kind, NEUTRAL, $.extend({}, style.shape), { goal: id });
      });
    }

    // ---------- Steps
    function show(step, quiet) {
      state.step = step;
      var list = steps(),
        index = list.indexOf(step);
      all('[data-wz-step]').forEach(function (pane) {
        pane.hidden = pane.getAttribute('data-wz-step') !== step;
      });
      all('[data-wz-step-label]').forEach(function (label) {
        var at = list.indexOf(label.getAttribute('data-wz-step-label')),
          button = label.querySelector('[data-wz-jump]');
        label.hidden = at === -1;
        button.querySelector('span').textContent = String(at + 1);
        label.className = at === index ? 'is-current' : at < index ? 'is-done' : '';
        button.disabled = !(at < index);
        button.setAttribute('aria-current', at === index ? 'step' : 'false');
      });
      stepValues();
      q('[data-wz-back]').hidden = index <= 0;
      q('[data-wz-next]').hidden = step === 'goal' || step === 'name';
      if (step === 'images') {
        renderImages();
      } else if (step === 'style') {
        q('[data-wz-style-lead]').textContent = styleLead();
        renderStyles();
        renderPreview();
      } else if (step === 'extras') {
        state.trying = '';
        q('[data-wz-extras-lead]').textContent = labels['lead_extras_' + kind()] || '';
        renderExtras();
        renderExtrasPreview();
      } else if (step === 'name') {
        renderSummary();
        q('[data-wz-title]').focus();
      }
      if (!quiet) {
        root.scrollIntoView({ block: 'start', behavior: 'smooth' });
      }
    }

    function move(offset) {
      var list = steps(),
        index = list.indexOf(state.step) + offset;
      if (index >= 0 && index < list.length) {
        show(list[index]);
      }
    }

    // What was chosen, next to the step names
    function stepValues() {
      var style = goal() ? styleById(state.style) : null,
        values = {
          goal: goal() ? goal().name : '',
          images: goal() && !goal().noImages && state.images.length ? String(state.images.length) : '',
          style: style && state.step !== 'style' && steps().indexOf(state.step) > steps().indexOf('style') ? style.name : '',
          extras: goal() && steps().indexOf(state.step) > steps().indexOf('extras') ? msg('extras-count').replace('%d', chosenNames().length) : '',
          name: '',
        };
      all('[data-wz-step-value]').forEach(function (small) {
        var value = values[small.getAttribute('data-wz-step-value')] || '';
        small.textContent = value;
        small.hidden = !value;
      });
    }

    // ---------- Images
    function renderImages() {
      var box = q('[data-wz-picked]'),
        item = goal() || {},
        pairs = !!item.pairs;
      q('[data-wz-hint]').textContent = item.hint || '';
      box.textContent = '';
      box.classList.toggle('is-pairs', pairs);
      state.images.forEach(function (image, index) {
        var thumb = document.createElement('span'),
          img = document.createElement('img'),
          badge = document.createElement('em'),
          tools = document.createElement('span');
        thumb.className = 'sgg-wz-thumb' + (pairs ? (index % 2 ? ' is-after' : ' is-before') : '');
        thumb.setAttribute('draggable', 'true');
        thumb.setAttribute('data-wz-index', String(index));
        img.src = image.thumb;
        img.alt = '';
        img.draggable = false;
        badge.className = 'sgg-wz-badge';
        badge.textContent = pairs
          ? (index % 2 ? labels.after || 'After' : labels.before || 'Before') + ' · ' + (Math.floor(index / 2) + 1)
          : String(index + 1);
        tools.className = 'sgg-wz-thumb-tools';
        tools.innerHTML =
          '<button type="button" data-wz-shift="-1" aria-label="' + msg('left') + '">&lsaquo;</button>' +
          '<button type="button" data-wz-shift="1" aria-label="' + msg('right') + '">&rsaquo;</button>' +
          '<button type="button" class="is-remove" data-wz-remove aria-label="' + msg('remove') + '">&times;</button>';
        thumb.appendChild(img);
        thumb.appendChild(badge);
        thumb.appendChild(tools);
        box.appendChild(thumb);
      });

      var count = state.images.length,
        min = item.min || 0;
      q('[data-wz-count]').textContent = count ? msg('count').replace('%d', count) : msg('none');
      var minBox = q('[data-wz-min]');
      if (pairs && count % 2) {
        minBox.textContent = msg('odd');
        minBox.className = 'sgg-wz-min is-warn';
      } else if (min && count < min) {
        minBox.textContent = msg('min').replace('%d', min);
        minBox.className = 'sgg-wz-min';
      } else {
        minBox.textContent = '';
        minBox.className = 'sgg-wz-min';
      }
      q('[data-wz-order-note]').hidden = count < 2;
      stepValues();
    }

    function chooseImages() {
      if (!window.wp || !window.wp.media) {
        return;
      }
      if (!frame) {
        frame = window.wp.media({
          title: msg('media'),
          library: { type: 'image' },
          multiple: 'add',
          button: { text: msg('use') },
        });
        frame.on('select', function () {
          var taken = state.images.map(function (image) {
            return image.id;
          });
          frame.state().get('selection').each(function (attachment) {
            var item = attachment.toJSON(),
              sizes = item.sizes || {};
            if (taken.indexOf(item.id) === -1) {
              state.images.push({ id: item.id, thumb: (sizes.medium || sizes.thumbnail || item).url, large: (sizes.large || sizes.full || item).url });
            }
          });
          renderImages();
        });
      }
      frame.open();
    }

    function moveImage(from, to) {
      if (from === to || from < 0 || to < 0 || from >= state.images.length || to >= state.images.length) {
        return;
      }
      var image = state.images.splice(from, 1)[0];
      state.images.splice(to, 0, image);
      renderImages();
    }

    // ---------- Styles
    function styleLead() {
      var type = kind();
      return labels['lead_' + state.goal] || labels['lead_' + type] || '';
    }

    function renderStyles() {
      var box = q('[data-wz-styles]'),
        list = stylesOf(),
        anyLocked = list.some(function (style) {
          return style.locked;
        });
      // The Free / PRO filter only while some styles are locked
      q('[data-wz-tiers]').hidden = !anyLocked;
      if (!anyLocked) {
        state.tier = 'all';
      }
      if (!state.style || !styleById(state.style)) {
        state.style = goal() ? goal().style : '';
      }
      box.textContent = '';
      var shown = 0;
      list.forEach(function (style) {
        if ((state.tier === 'free' && style.pro) || (state.tier === 'pro' && !style.pro)) {
          return;
        }
        if (state.collection && style.collection !== state.collection) {
          return;
        }
        var button = document.createElement('button'),
          current = style.id === state.style;
        button.type = 'button';
        button.className = 'sgg-wz-sc' + (current ? ' is-current' : '') + (style.locked ? ' is-locked' : '');
        button.setAttribute('data-wz-style', style.id);
        button.setAttribute('aria-pressed', current ? 'true' : 'false');
        button.title = style.name + ' · ' + style.collection;
        button.innerHTML = picture(kind(), style, { goal: state.goal });
        var name = document.createElement('span'),
          strong = document.createElement('strong'),
          small = document.createElement('small');
        name.className = 'sgg-wz-sc-name';
        strong.textContent = style.name;
        name.appendChild(strong);
        if (style.locked) {
          var tag = document.createElement('em');
          tag.textContent = 'PRO';
          name.appendChild(tag);
        }
        small.textContent = style.collection;
        button.appendChild(name);
        button.appendChild(small);
        box.appendChild(button);
        shown++;
      });
      if (!shown) {
        var empty = document.createElement('p');
        empty.className = 'sgg-wz-empty';
        empty.textContent = labels.noStyles || '';
        box.appendChild(empty);
      }
      var current = box.querySelector('.is-current');
      if (current) {
        current.scrollIntoView({ block: 'nearest' });
      }
    }

    function images(size) {
      return goal() && goal().noImages
        ? []
        : state.images.map(function (image) {
          return image[size];
        });
    }

    function renderPreview() {
      var style = styleById(state.style),
        box = q('[data-wz-preview]');
      if (!style) {
        box.textContent = '';
        return;
      }
      box.innerHTML = picture(kind(), style, { goal: state.goal, big: true, images: images('large'), labels: labels, extras: shownExtras(false) });
      q('[data-wz-style-name]').textContent = style.name;
      q('[data-wz-style-collection]').textContent = style.collection;
      var s = style.swatch;
      q('[data-wz-palette]').innerHTML = [s.head, s.surface, s.accent, s.text, s.border].map(function (color) {
        return '<i style="background:' + color + '"></i>';
      }).join('');
      renderSpecs(style);
    }

    // What the style sets on this type, in words
    function renderSpecs(style) {
      var shape = style.shape || {},
        type = kind(),
        id = state.goal,
        px = function (value) {
          return value + ' ' + (labels.px || 'px');
        },
        specs = [];
      var dot = function (color) {
        return '<i class="sgg-wz-dot" style="background:' + color + '"></i>';
      };
      if (id === 'polaroid') {
        // Polaroid keeps its own spacing, corners and frames: the style gives the paper and the ink
        specs.push([labels.paper, dot(style.swatch.surface)]);
        specs.push([labels.ink, dot(style.swatch.text)]);
      } else if (type === 'gallery') {
        specs.push([labels.spacing, px(shape.gap)]);
        specs.push([labels.corners, id === 'rounded' ? labels.circle : px(shape.radius)]);
        specs.push([labels.shadow, labels[shape.shadow] || shape.shadow]);
        specs.push([labels.frame, shape.frame > 0 ? px(shape.frame) : labels.none]);
        // The colors of the caption on hover, where the gallery gets that caption (PRO in a classic gallery)
        var captions = state.extras.captions;
        if (id !== 'postfeed' && captions !== false) {
          specs.push([labels.hover, dot(style.swatch.head)]);
        }
      } else {
        var noNav = id === 'logos',
          compare = id === 'compare';
        if (!noNav && !compare) {
          specs.push([labels.arrows, (labels[shape.arrows] || shape.arrows) + ', ' + (labels[shape.arrowPos] || shape.arrowPos)]);
        }
        if (!noNav) {
          specs.push([labels.pagination, labels[shape.dots] || shape.dots]);
        }
        if (type === 'carousel' && (id === 'carousel')) {
          specs.push([labels.captions, (labels[shape.captions] || shape.captions) + ', ' + (labels[shape.align] || shape.align)]);
        } else if (type === 'slider' && id === 'slider') {
          specs.push([labels.box, '<i class="sgg-wz-dot" style="background:' + style.swatch.head + '"></i>']);
        }
        specs.push([labels.corners, px(shape.radius)]);
        specs.push([labels.shadow, labels[shape.shadow] || shape.shadow]);
      }
      q('[data-wz-specs]').innerHTML = specs.map(function (spec) {
        return '<li><span>' + $('<i>').text(spec[0] || '').html() + '</span><b>' + spec[1] + '</b></li>';
      }).join('');
    }

    // ---------- Additional: switches by heading, each one drawn on the picture at once
    function renderExtras() {
      var box = q('[data-wz-extras]'),
        list = options(),
        ids = Object.keys((goal() && goal().extras) || {}),
        groups = config.extraGroups || {};
      box.textContent = '';
      Object.keys(groups).forEach(function (group) {
        var inGroup = ids.filter(function (id) {
          return list[id] && list[id].group === group;
        });
        if (!inGroup.length) {
          return;
        }
        var section = document.createElement('div'),
          title = document.createElement('h3');
        section.className = 'sgg-wz-xg';
        title.textContent = groups[group];
        section.appendChild(title);
        inGroup.forEach(function (id) {
          section.appendChild(extraButton(id, list[id]));
        });
        box.appendChild(section);
      });
    }

    function extraButton(id, option) {
      var on = !!state.extras[id] && !option.locked,
        button = document.createElement('button'),
        icon = document.createElement('i'),
        text = document.createElement('span'),
        name = document.createElement('strong'),
        note = document.createElement('small'),
        knob = document.createElement('b');
      button.type = 'button';
      button.className = 'sgg-wz-tg' + (on ? ' is-on' : '') + (option.locked ? ' is-locked' : '');
      button.setAttribute('data-wz-extra', id);
      button.setAttribute('aria-pressed', on ? 'true' : 'false');
      icon.className = 'fa ' + option.icon;
      icon.setAttribute('aria-hidden', 'true');
      text.className = 'sgg-wz-tg-text';
      name.textContent = option.name;
      // The PRO tag only while the option is closed; with PRO the option is like any other
      if (option.locked) {
        var tag = document.createElement('em');
        tag.textContent = 'PRO';
        name.appendChild(tag);
      }
      note.textContent = option.note || '';
      text.appendChild(name);
      text.appendChild(note);
      knob.className = 'sgg-wz-tg-knob';
      knob.setAttribute('aria-hidden', 'true');
      button.appendChild(icon);
      button.appendChild(text);
      button.appendChild(knob);
      if (option.locked) {
        // A closed PRO option shows on the picture while the mouse or the focus is on it
        var tryOn = function () {
          state.trying = id;
          renderExtrasPreview();
        };
        var tryOff = function () {
          if (state.trying === id) {
            state.trying = '';
            renderExtrasPreview();
          }
        };
        button.addEventListener('mouseenter', tryOn);
        button.addEventListener('focus', tryOn);
        button.addEventListener('mouseleave', tryOff);
        button.addEventListener('blur', tryOff);
      }
      return button;
    }

    function renderExtrasPreview() {
      var style = styleById(state.style),
        box = q('[data-wz-extras-preview]'),
        note = q('[data-wz-extras-note]');
      if (!style) {
        box.textContent = '';
        return;
      }
      box.innerHTML = picture(kind(), style, { goal: state.goal, big: true, images: images('large'), labels: labels, extras: shownExtras(true) });
      var option = state.trying ? options()[state.trying] : null;
      note.hidden = !option;
      note.textContent = option ? (labels.proPreview || '') + ': ' + option.name : '';
    }

    // ---------- Finish
    function renderSummary() {
      var list = q('[data-wz-summary]'),
        style = styleById(state.style),
        item = goal() || {},
        count = state.images.length;
      list.innerHTML = '';
      [
        ['fa-flag', item.name || ''],
        ['fa-image', item.noImages ? msg('no-images') : count ? msg('count').replace('%d', item.pairs ? count : count) : msg('none')],
        ['fa-paint-brush', style ? style.name + ' · ' + style.collection : ''],
        ['fa-sliders', steps().indexOf('extras') === -1 ? '' : chosenNames().join(', ') || msg('no-extras')],
      ].forEach(function (row) {
        if (!row[1]) {
          return;
        }
        var li = document.createElement('li'),
          icon = document.createElement('i');
        icon.className = 'fa ' + row[0];
        icon.setAttribute('aria-hidden', 'true');
        li.appendChild(icon);
        li.appendChild(document.createTextNode(' ' + row[1]));
        list.appendChild(li);
      });
      q('[data-wz-finish-pic]').innerHTML = style ? picture(kind(), style, { goal: state.goal, big: true, images: images('large'), labels: labels, extras: shownExtras(false) }) : '';
      var title = q('[data-wz-title]');
      if (!title.value && goal()) {
        title.placeholder = goal().name;
      }
    }

    function create() {
      var button = q('[data-wz-create]'),
        status = q('[data-wz-status]'),
        title = q('[data-wz-title]');
      button.disabled = true;
      status.textContent = '…';
      var extras = {},
        chosen = shownExtras(false);
      Object.keys(chosen).forEach(function (id) {
        extras[id] = chosen[id] ? 1 : 0;
      });
      $.post(config.ajaxUrl, {
        action: 'grid-gallery',
        _wpnonce: config.nonce,
        route: { module: 'wizard', action: 'create' },
        title: title.value.trim() || title.placeholder,
        goal: state.goal,
        style: state.style,
        extras: extras,
        images: goal() && goal().noImages
          ? []
          : state.images.map(function (image) {
            return image.id;
          }),
      })
        .done(function (response) {
          if (response && !response.error && response.url) {
            window.location.href = response.url;
            return;
          }
          status.textContent = (response && response.message) || msg('error');
          button.disabled = false;
        })
        .fail(function () {
          status.textContent = msg('error');
          button.disabled = false;
        });
    }

    // ---------- Events
    root.addEventListener('click', function (event) {
      var el;
      if ((el = event.target.closest('[data-wz-goal]'))) {
        // A locked goal opens its PRO window (Upsell module) before this listener
        if (el.classList.contains('is-locked')) {
          return;
        }
        state.goal = el.getAttribute('data-wz-goal');
        state.style = goal().style;
        state.extras = $.extend({}, goal().extras || {});
        state.trying = '';
        state.collection = '';
        q('[data-wz-collection]').value = '';
        all('[data-wz-goal]').forEach(function (card) {
          card.classList.toggle('is-current', card === el);
        });
        move(1);
        return;
      }
      if ((el = event.target.closest('[data-wz-jump]'))) {
        if (!el.disabled) {
          show(el.getAttribute('data-wz-jump'));
        }
        return;
      }
      if (event.target.closest('[data-wz-media]')) {
        chooseImages();
        return;
      }
      if ((el = event.target.closest('[data-wz-remove]'))) {
        state.images.splice(parseInt(el.closest('[data-wz-index]').getAttribute('data-wz-index'), 10), 1);
        renderImages();
        return;
      }
      if ((el = event.target.closest('[data-wz-shift]'))) {
        var from = parseInt(el.closest('[data-wz-index]').getAttribute('data-wz-index'), 10);
        moveImage(from, from + parseInt(el.getAttribute('data-wz-shift'), 10));
        return;
      }
      if ((el = event.target.closest('[data-wz-tier]'))) {
        state.tier = el.getAttribute('data-wz-tier');
        all('[data-wz-tier]').forEach(function (button) {
          button.setAttribute('aria-pressed', button === el ? 'true' : 'false');
        });
        renderStyles();
        return;
      }
      if ((el = event.target.closest('[data-wz-style]'))) {
        var style = styleById(el.getAttribute('data-wz-style'));
        if (style && style.locked) {
          if (window.SggUpsell) {
            window.SggUpsell.open('styles');
          }
          return;
        }
        state.style = el.getAttribute('data-wz-style');
        all('[data-wz-style]').forEach(function (card) {
          var on = card === el;
          card.classList.toggle('is-current', on);
          card.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        renderPreview();
        return;
      }
      if ((el = event.target.closest('[data-wz-extra]'))) {
        var id = el.getAttribute('data-wz-extra'),
          option = options()[id];
        if (!option) {
          return;
        }
        // A closed PRO option opens its PRO window
        if (option.locked) {
          if (window.SggUpsell && option.upsell) {
            window.SggUpsell.open(option.upsell);
          }
          return;
        }
        setExtra(state.extras, id, !state.extras[id]);
        renderExtras();
        renderExtrasPreview();
        var again = q('[data-wz-extra="' + id + '"]');
        if (again) {
          again.focus();
        }
        return;
      }
      if (event.target.closest('[data-wz-back]')) {
        move(-1);
        return;
      }
      if (event.target.closest('[data-wz-next]')) {
        move(1);
        return;
      }
      if (event.target.closest('[data-wz-create]')) {
        create();
      }
    });

    q('[data-wz-collection]').addEventListener('change', function () {
      state.collection = this.value;
      renderStyles();
    });

    // Drag a thumbnail onto another one to change the order
    var picked = q('[data-wz-picked]');
    picked.addEventListener('dragstart', function (event) {
      var thumb = event.target.closest('[data-wz-index]');
      if (!thumb) {
        return;
      }
      dragFrom = parseInt(thumb.getAttribute('data-wz-index'), 10);
      thumb.classList.add('is-dragging');
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(dragFrom));
      }
    });
    picked.addEventListener('dragover', function (event) {
      var thumb = event.target.closest('[data-wz-index]');
      if (dragFrom === null || !thumb) {
        return;
      }
      event.preventDefault();
      all('.sgg-wz-thumb.is-target').forEach(function (other) {
        other.classList.toggle('is-target', other === thumb);
      });
      thumb.classList.add('is-target');
    });
    picked.addEventListener('drop', function (event) {
      var thumb = event.target.closest('[data-wz-index]');
      if (dragFrom === null || !thumb) {
        return;
      }
      event.preventDefault();
      var from = dragFrom;
      dragFrom = null;
      moveImage(from, parseInt(thumb.getAttribute('data-wz-index'), 10));
    });
    picked.addEventListener('dragend', function () {
      dragFrom = null;
      all('.sgg-wz-thumb').forEach(function (thumb) {
        thumb.classList.remove('is-dragging', 'is-target');
      });
    });

    q('[data-wz-title]').addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        create();
      }
    });

    renderGoalPictures();
    show('goal', true);
  });
})(jQuery);
