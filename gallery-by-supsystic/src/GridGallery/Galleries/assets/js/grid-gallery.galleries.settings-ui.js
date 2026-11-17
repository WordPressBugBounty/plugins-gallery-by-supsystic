/**
 * Gallery settings page: the sections are split into groups shown as tabs, every section is a card and a search finds
 * any setting. Only the presentation changes: no field is moved, renamed or rebuilt, so the page scripts, the saved
 * values and the collapsible sections keep working as before.
 */
(function ($) {
  'use strict';

  var STORAGE_KEY = 'sggSettingsGroup';

  // A section belongs to the group of the first table in it. Sections that are not listed go to "More".
  var GROUPS = [
    { key: 'layout', icon: 'fa-th-large', label: 'Layout', tables: ['gg-anl-main', 'gg-anl-horiz-scroll', 'gg-anl-lazyload', 'gg-anl-preloader'] },
    { key: 'lightbox', icon: 'fa-expand', label: 'Lightbox', tables: ['gg-anl-popup'] },
    { key: 'captions', icon: 'fa-font', label: 'Captions & Icons', tables: ['captionSettingsType'] },
    { key: 'style', icon: 'fa-paint-brush', label: 'Style', tables: ['gg-anl-border-type'] },
    // Custom Buttons style the buttons of categories, pages and Load more
    { key: 'categories', icon: 'fa-tags', label: 'Categories & Pages', tables: ['cats', 'gg-anl-cust-button', 'custom-buttons'] },
    { key: 'sharing', icon: 'fa-share-alt', label: 'Sharing & Watermark', tables: ['gg-anl-soc-share', 'water-m-tab'] },
    { key: 'advanced', icon: 'fa-sliders', label: 'Advanced', tables: ['gg-anl-attributes', 'gg-anl-cdn'] },
  ];
  var MORE = { key: 'more', icon: 'fa-ellipsis-h', label: 'More', tables: [] };

  function readStore() {
    try {
      return window.localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      return null;
    }
  }

  function writeStore(value) {
    try {
      window.localStorage.setItem(STORAGE_KEY, value);
    } catch (e) {}
  }

  function translate(text) {
    return window.sggSettingsUi && window.sggSettingsUi[text] ? window.sggSettingsUi[text] : text;
  }

  /** The key of a section: the id or name of its first table, or a mark for the sections without one. */
  function sectionKey(section) {
    var table = section.querySelector('table');
    if (table && (table.id || table.getAttribute('name'))) {
      return table.id || table.getAttribute('name');
    }
    if (section.querySelector('.custom-buttons-preview')) {
      return 'custom-buttons';
    }
    return '';
  }

  function groupOf(key) {
    for (var i = 0; i < GROUPS.length; i++) {
      if (GROUPS[i].tables.indexOf(key) !== -1) {
        return GROUPS[i];
      }
    }
    return MORE;
  }

  function init() {
    var wrap = document.querySelector('.settings-wrap'),
      form = document.getElementById('form-settings');
    if (!wrap || !form || wrap.classList.contains('sgg-settings-ui')) {
      return;
    }

    // The table of Custom Buttons (PRO) sits in the card of Social Sharing: it gets a card of its own, after the one
    // of Categories. Moved inside the form, so its fields are saved as before.
    function topOf(element) {
      while (element && element.parentElement !== form) {
        element = element.parentElement;
      }
      return element;
    }
    var buttonsTable = form.querySelector('table#gg-anl-cust-button'),
      buttonsHome = topOf(buttonsTable);
    if (buttonsTable && buttonsHome && buttonsHome.querySelector('table') !== buttonsTable) {
      var buttonsCard = document.createElement('div'),
        catsHome = topOf(form.querySelector('table#cats, table[name="cats"]')) || buttonsHome;
      buttonsCard.setAttribute('data-tab', 'settings');
      buttonsCard.appendChild(buttonsTable);
      form.insertBefore(buttonsCard, catsHome.nextSibling);
    }

    var sections = Array.prototype.filter.call(form.children, function (child) {
      return child.getAttribute('data-tab') === 'settings';
    });
    if (!sections.length) {
      return;
    }

    var used = {};
    sections.forEach(function (section) {
      var group = groupOf(sectionKey(section));
      section.classList.add('sgg-section');
      section.setAttribute('data-sgg-group', group.key);
      used[group.key] = group;
    });
    var groups = GROUPS.concat([MORE]).filter(function (group) {
      return used[group.key];
    });

    var nav = document.createElement('div');
    nav.className = 'sgg-settings-nav';
    var list = document.createElement('div');
    list.className = 'sgg-settings-groups';
    list.setAttribute('role', 'tablist');
    list.setAttribute('aria-label', translate('Settings groups'));
    function addButton(key, icon, label) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'sgg-settings-group';
      button.setAttribute('role', 'tab');
      button.setAttribute('data-sgg-group', key);
      button.innerHTML = '<i class="fa fa-fw ' + icon + '" aria-hidden="true"></i><span></span>';
      button.querySelector('span').textContent = label;
      list.appendChild(button);
    }
    // Posts is the other tab of the page (settings.js switches it); it stands among the groups, before More, and the
    // old Settings / Posts bar goes (its links stay hidden for the scripts that click them)
    var postsLink = document.querySelector('.change-tab[href="posts"]');
    function addPosts() {
      if (postsLink && !list.querySelector('[data-sgg-group="posts"]')) {
        addButton('posts', 'fa-list-alt', postsLink.textContent.trim() || translate('Posts'));
      }
    }
    groups.forEach(function (group) {
      if (group.key === 'more') {
        addPosts();
      }
      addButton(group.key, group.icon, translate(group.label));
    });
    addPosts();
    var tabsRow = document.querySelector('.gg-settings-header-row');
    if (tabsRow && postsLink) {
      tabsRow.classList.add('sgg-tabs-moved');
    }
    var search = document.createElement('label');
    search.className = 'sgg-settings-search';
    search.innerHTML = '<i class="fa fa-search" aria-hidden="true"></i><input type="search" autocomplete="off">';
    var input = search.querySelector('input');
    input.placeholder = translate('Search settings');
    input.setAttribute('aria-label', translate('Search settings'));
    nav.appendChild(list);
    nav.appendChild(search);
    form.insertBefore(nav, sections[0]);

    var empty = document.createElement('div');
    empty.className = 'sgg-settings-empty';
    empty.hidden = true;
    empty.textContent = translate('No settings match your search.');
    form.insertBefore(empty, sections[0]);

    wrap.classList.add('sgg-settings-ui');

    function activate(key, remember) {
      if (!used[key]) {
        key = groups[0].key;
      }
      wrap.setAttribute('data-sgg-active', key);
      Array.prototype.forEach.call(list.children, function (button) {
        var on = button.getAttribute('data-sgg-group') === key;
        button.classList.toggle('is-active', on);
        button.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      sections.forEach(function (section) {
        section.classList.toggle('sgg-section-off', section.getAttribute('data-sgg-group') !== key);
      });
      if (remember) {
        writeStore(key);
      }
    }

    // The Settings or the Posts tab of the page, through its own link
    function openTab(tab) {
      var link = document.querySelector('.change-tab[href="' + tab + '"]');
      if (link && !link.classList.contains('active')) {
        $(link).trigger('click');
      }
    }

    list.addEventListener('click', function (event) {
      var button = event.target.closest('.sgg-settings-group');
      if (!button) {
        return;
      }
      if (button.getAttribute('data-sgg-group') === 'posts') {
        openTab('posts');
        return;
      }
      openTab('settings');
      input.value = '';
      filter('');
      activate(button.getAttribute('data-sgg-group'), true);
    });

    // A card whose rows the page has all hidden (options of another type, a switched-off feature) is not drawn as an
    // empty frame
    function shown(element, stop) {
      for (var node = element; node && node !== stop; node = node.parentElement) {
        if (node.hidden || window.getComputedStyle(node).display === 'none') {
          return false;
        }
      }
      return true;
    }
    function markEmpty() {
      sections.forEach(function (section) {
        var rows = section.querySelectorAll('tr'),
          visible = Array.prototype.some.call(rows, function (row) {
            return shown(row, section);
          });
        section.classList.toggle('sgg-section-empty', rows.length > 0 && !visible);
      });
    }
    var emptyTimer = null;
    function markEmptySoon() {
      window.clearTimeout(emptyTimer);
      emptyTimer = window.setTimeout(markEmpty, 60);
    }
    if (window.MutationObserver) {
      new MutationObserver(markEmptySoon).observe(form, { attributes: true, attributeFilter: ['style', 'class', 'hidden'], subtree: true });
    }
    form.addEventListener('change', markEmptySoon);

    // Search through the labels of every group; the rows the page itself hides stay hidden
    function filter(query) {
      query = query.trim().toLowerCase();
      wrap.classList.toggle('is-searching', query !== '');
      var found = 0;
      sections.forEach(function (section) {
        var hits = 0;
        Array.prototype.forEach.call(section.querySelectorAll('tr'), function (row) {
          var label = row.querySelector('th');
          var match = !query || (label && label.textContent.toLowerCase().indexOf(query) !== -1);
          row.classList.toggle('sgg-row-miss', !match);
          if (match && query) {
            hits++;
          }
        });
        section.classList.toggle('sgg-section-miss', query !== '' && hits === 0);
        found += hits;
      });
      empty.hidden = !query || found > 0;
    }

    var timer = null;
    input.addEventListener('input', function () {
      openTab('settings');
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        filter(input.value);
      }, 120);
    });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        input.value = '';
        filter('');
      }
    });
    document.addEventListener('keydown', function (event) {
      var tag = event.target && event.target.tagName;
      if (event.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT' && !event.target.isContentEditable) {
        event.preventDefault();
        input.focus();
      }
    });

    // The feature chips and the edit pencils jump to a section: open its group first, before the page scrolls to it
    document.addEventListener(
      'click',
      function (event) {
        var link = event.target.closest && event.target.closest('.gg-feature-status-link, .gg-info-edit-link');
        if (!link) {
          return;
        }
        var target = document.getElementById(link.getAttribute('data-gg-anchor') || '');
        var section = target && target.closest('.sgg-section');
        if (section) {
          input.value = '';
          filter('');
          activate(section.getAttribute('data-sgg-group'), true);
        }
      },
      true
    );

    // The page switches its Settings and Posts tabs by itself (settings.js, also from the feature chips): the groups
    // follow, with Posts marked while its tab is open
    function showForTab(tab) {
      var other = tab !== 'settings';
      wrap.classList.toggle('is-other-tab', other);
      Array.prototype.forEach.call(list.children, function (button) {
        var key = button.getAttribute('data-sgg-group'),
          on = other ? key === tab : key === wrap.getAttribute('data-sgg-active');
        button.classList.toggle('is-active', on);
        button.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }
    $(document).on('click', '.change-tab', function () {
      showForTab($(this).attr('href'));
    });

    activate(readStore() || groups[0].key, false);
    var active = document.querySelector('.change-tab.active');
    showForTab(active ? active.getAttribute('href') : 'settings');
    markEmpty();
  }

  $(function () {
    // After the page's own ready handlers: iCheck, the collapsible sections and the tabs are set up by then
    window.setTimeout(init, 0);
  });
})(jQuery);

/**
 * Gallery type as picture cards. The select area[grid] (and area[justified] for Justified rows) stays the field of the
 * form with its options as they were: a card sets its value and fires its change, so every script that follows the
 * type (rows to show, the live preview, PRO) works as before. A type the version does not have opens the PRO window.
 */
(function ($) {
  'use strict';

  var NEUTRAL = { head: '#1d2b3c', headText: '#ffffff', surface: '#ffffff', text: '#475569', stripe: '#eef2f6', accent: '#0e8a93', border: '#dde3ea' },
    SHAPE = { radius: 6, gap: 8, frame: 0, shadow: 'none' },
    CARDS = [
      { key: 'fixed', value: '0', goal: 'fixed' },
      { key: 'vertical', value: '1', goal: 'masonry' },
      { key: 'horizontal', value: '2', justified: '0', goal: 'horizontal' },
      { key: 'justified', value: '2', justified: '1', goal: 'justified' },
      { key: 'columns', value: '3', goal: 'grid' },
      { key: 'mosaic', value: '4', goal: 'mosaic', upsell: 'mosaic' },
    ];

  function init() {
    var select = document.querySelector('select[name="area[grid]"]'),
      data = document.getElementById('sggGalleryTypeCards');
    if (!select || !data || select.parentNode.querySelector('.sgg-gt')) {
      return;
    }
    var texts = {};
    try {
      texts = JSON.parse(data.getAttribute('data-cards')) || {};
    } catch (e) {
      texts = {};
    }
    var justified = document.querySelector('select[name="area[justified]"]'),
      box = document.createElement('div'),
      buttons = [];
    box.className = 'sgg-gt';
    box.setAttribute('role', 'radiogroup');
    box.setAttribute('aria-label', texts.label || 'Gallery type');

    CARDS.forEach(function (card) {
      var option = select.querySelector('option[value="' + card.value + '"]');
      if (!option || (card.justified === '1' && !justified)) {
        return;
      }
      var info = texts[card.key] || {},
        locked = option.disabled,
        button = document.createElement('button'),
        picture = document.createElement('span'),
        name = document.createElement('span'),
        text = document.createElement('span');
      button.type = 'button';
      button.className = 'sgg-gt-card' + (locked ? ' is-locked' : '');
      button.setAttribute('role', 'radio');
      button.setAttribute('data-gt-key', card.key);
      if (locked && card.upsell) {
        // The PRO window of the Upsell module opens on this attribute
        button.setAttribute('data-sgg-upsell', card.upsell);
      }
      picture.className = 'sgg-gt-pic';
      if (window.SggStyleMock) {
        picture.innerHTML = window.SggStyleMock.html('gallery', NEUTRAL, $.extend({}, SHAPE), { goal: card.goal });
      }
      name.className = 'sgg-gt-name';
      name.textContent = info.title || option.textContent;
      if (locked) {
        var tag = document.createElement('em');
        tag.textContent = 'PRO';
        name.appendChild(tag);
      }
      text.className = 'sgg-gt-text';
      text.textContent = info.text || '';
      button.appendChild(picture);
      button.appendChild(name);
      button.appendChild(text);
      button.sggCard = card;
      box.appendChild(button);
      buttons.push(button);
    });
    if (!buttons.length) {
      return;
    }

    select.parentNode.insertBefore(box, select);
    select.classList.add('sgg-gt-native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');
    var table = select.closest('table');
    if (table) {
      table.classList.add('sgg-gt-ready');
    }

    function currentKey() {
      var value = select.value;
      if (value === '2' && justified) {
        return justified.value === '1' ? 'justified' : 'horizontal';
      }
      for (var i = 0; i < CARDS.length; i++) {
        if (CARDS[i].value === value) {
          return CARDS[i].key;
        }
      }
      return '';
    }

    function mark() {
      var key = currentKey();
      buttons.forEach(function (button) {
        var on = button.getAttribute('data-gt-key') === key;
        button.classList.toggle('is-current', on);
        button.setAttribute('aria-checked', on ? 'true' : 'false');
        button.tabIndex = on || (!key && button === buttons[0]) ? 0 : -1;
      });
    }

    function choose(button) {
      var card = button.sggCard;
      if (!card || button.classList.contains('is-locked')) {
        return;
      }
      if (justified && card.justified !== undefined && justified.value !== card.justified) {
        $(justified).val(card.justified).trigger('change');
      }
      if (select.value !== card.value) {
        $(select).val(card.value).trigger('change');
      }
      mark();
    }

    box.addEventListener('click', function (event) {
      var button = event.target.closest('.sgg-gt-card');
      if (button) {
        choose(button);
      }
    });
    // Arrow keys move between the cards, as in a group of radio buttons
    box.addEventListener('keydown', function (event) {
      var index = buttons.indexOf(document.activeElement),
        step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
      if (index === -1 || !step) {
        return;
      }
      event.preventDefault();
      var next = buttons[(index + step + buttons.length) % buttons.length];
      next.focus();
      choose(next);
    });
    $(select).on('change', mark);
    if (justified) {
      $(justified).on('change', mark);
    }
    mark();
  }

  $(function () {
    // After the settings page has built its sections
    window.setTimeout(init, 0);
  });
})(jQuery);

/**
 * The lightbox of a classic gallery: the new one (box.type 3, Lightbox module) or the classic popup themes (box.type 0
 * to 2, kept as they were). The rows of the other one step back; the classic choice comes back as it was.
 */
(function ($) {
  'use strict';

  // Backdrop and ink of the themes that have their own (the same as GridGallery_Lightbox_Module)
  var LOOKS = { dark: ['#0b0f19', '#f8fafc'], light: ['#f8fafc', '#0f172a'], polaroid: ['#ece6da', '#3f3a33'], cinema: ['#000000', '#ffffff'] },
    CLASSIC_ROWS = ['sgg-t-popup-theme', 'popup-border-enable', 'border-type-popup', 'border-color-popup', 'border-width-popup', 'box-background', 'box-transparency', 'box-popupsize'],
    OWN_ROWS = ['sgg-lb-bg', 'sgg-lb-ui', 'sgg-lb-accent'];

  function value(name) {
    var field = document.querySelector('[name="' + name + '"]');
    return field ? field.value : '';
  }

  function hex(color) {
    return /^#[0-9a-f]{6}$/i.test(color || '') ? color : '';
  }

  function readableOn(color) {
    var light = 0;
    [0.2126, 0.7152, 0.0722].forEach(function (weight, index) {
      var channel = parseInt(color.substr(1 + index * 2, 2), 16) / 255;
      light += weight * (channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4));
    });
    return light > 0.2 ? '#0f172a' : '#ffffff';
  }

  function colorsOf(theme) {
    var look = LOOKS[theme] || [],
      bg = look[0] || hex(value('lightbox[paletteBg]')) || '#0b0f19',
      ui = look[1] || hex(value('lightbox[paletteUi]')) || readableOn(bg),
      accent = hex(value('lightbox[paletteAccent]')) || '#0e8a93';
    if (value('lightbox[colors]') === 'own') {
      bg = hex(value('lightbox[bg]')) || bg;
      ui = hex(value('lightbox[ui]')) || ui;
      accent = hex(value('lightbox[accent]')) || accent;
    }
    return { bg: bg, ui: ui, accent: accent };
  }

  function rowOf(id) {
    var cell = document.getElementById(id);
    return cell ? cell.closest('tr') : null;
  }

  function init() {
    var engine = document.querySelector('.sgg-lbe'),
      rows = document.querySelector('[data-sgg-lb-rows]'),
      typeField = document.querySelector('[name="box[type]"]'),
      themeField = document.querySelector('[name="box[theme]"]'),
      themeInput = document.querySelector('[data-sgg-lbt-input]');
    if (!engine || !rows || !typeField || !themeField || !themeInput) {
      return;
    }
    // The classic choice the gallery had, to bring back when the classic popup is chosen again
    var classic = {
      type: typeField.value === '3' ? '0' : typeField.value || '0',
      theme: themeField.value === 'modern' || !themeField.value ? 'theme_1' : themeField.value,
    };

    function drawPictures() {
      if (!window.SggStyleMock || !window.SggStyleMock.lightbox) {
        return;
      }
      Array.prototype.forEach.call(document.querySelectorAll('[data-sgg-lbt-pic]'), function (box) {
        var theme = box.getAttribute('data-sgg-lbt-pic');
        box.innerHTML = window.SggStyleMock.lightbox(theme, colorsOf(theme));
      });
    }

    function showOwnColors() {
      var own = value('lightbox[colors]') === 'own';
      OWN_ROWS.forEach(function (id) {
        var row = rowOf(id);
        if (row) {
          row.classList.toggle('sgg-lb-off', !own);
        }
      });
    }

    function setEngine(modern, quiet) {
      Array.prototype.forEach.call(engine.querySelectorAll('[data-sgg-lbe]'), function (card) {
        var on = (card.getAttribute('data-sgg-lbe') === 'modern') === modern;
        card.classList.toggle('is-current', on);
        card.setAttribute('aria-checked', on ? 'true' : 'false');
      });
      rows.hidden = !modern;
      CLASSIC_ROWS.forEach(function (id) {
        var row = rowOf(id);
        if (row) {
          row.classList.toggle('sgg-lb-off', modern);
        }
      });
      if (quiet) {
        return;
      }
      if (modern) {
        if (typeField.value !== '3') {
          classic = { type: typeField.value || '0', theme: themeField.value || 'theme_1' };
        }
        typeField.value = '3';
        themeField.value = 'modern';
      } else {
        typeField.value = classic.type;
        themeField.value = classic.theme;
      }
      $(typeField).trigger('change');
      $(themeField).trigger('change');
    }

    engine.addEventListener('click', function (event) {
      var card = event.target.closest('[data-sgg-lbe]');
      if (card) {
        setEngine(card.getAttribute('data-sgg-lbe') === 'modern');
      }
    });

    document.querySelector('.sgg-lbt').addEventListener('click', function (event) {
      var card = event.target.closest('[data-sgg-lbt]');
      // A locked theme opens the PRO window (Upsell module) on its own
      if (!card || card.classList.contains('is-locked')) {
        return;
      }
      themeInput.value = card.getAttribute('data-sgg-lbt');
      $(themeInput).trigger('change');
      Array.prototype.forEach.call(document.querySelectorAll('[data-sgg-lbt]'), function (other) {
        var on = other === card;
        other.classList.toggle('is-current', on);
        other.setAttribute('aria-checked', on ? 'true' : 'false');
      });
    });

    $(document).on('change input', '[name="lightbox[colors]"], [name="lightbox[bg]"], [name="lightbox[ui]"], [name="lightbox[accent]"], [name^="lightbox[palette"]', function () {
      showOwnColors();
      drawPictures();
    });
    // The color pickers change their field without a change event until they close
    $(document).on('irischange', '[name="lightbox[bg]"], [name="lightbox[ui]"], [name="lightbox[accent]"]', function () {
      window.setTimeout(drawPictures, 0);
    });

    setEngine(typeField.value === '3', true);
    showOwnColors();
    drawPictures();
  }

  $(function () {
    window.setTimeout(init, 0);
  });
})(jQuery);

/**
 * The classic popup style: its picture and name beside the "Choose theme" button, so the row shows what is chosen.
 * The card of the dialog that is chosen follows the field when another script sets it (the classic popup coming back
 * after the new lightbox). A locked style keeps the card the free version really shows.
 */
(function ($) {
  'use strict';

  function init() {
    var button = document.getElementById('chooseTheme'),
      field = document.getElementById('bigImageTheme');
    if (!button || !field || !document.querySelector('#themeDialog .gg-pth-card')) {
      return;
    }
    var chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'sgg-pth-current';
    chip.innerHTML = '<img alt="" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="><span class="themeName"></span>';
    button.parentNode.insertBefore(chip, button);

    function cards() {
      return Array.prototype.slice.call(document.querySelectorAll('#themeDialog .gg-pth-card'));
    }

    function draw() {
      var match = document.querySelector('#themeDialog .gg-pth-card[data-val="' + field.value + '"]');
      if (match && String(match.getAttribute('data-locked')) !== '1' && !match.classList.contains('is-active')) {
        cards().forEach(function (card) {
          card.classList.toggle('is-active', card === match);
        });
      }
      var active = document.querySelector('#themeDialog .gg-pth-card.is-active'),
        image = active ? active.querySelector('img') : null;
      chip.hidden = !active;
      if (active) {
        chip.querySelector('.themeName').textContent = active.getAttribute('data-name') || '';
        if (image) {
          chip.querySelector('img').src = image.getAttribute('src');
        }
        chip.title = button.value || '';
      }
    }

    chip.addEventListener('click', function () {
      $(button).trigger('click');
    });
    // After the handler of settings.js, which marks the card and sets the field
    $(document).on('click', '#themeDialog .gg-pth-card', function () {
      window.setTimeout(draw, 0);
    });
    $(field).on('change', draw);
    draw();
  }

  $(function () {
    window.setTimeout(init, 0);
  });
})(jQuery);

/**
 * Where a part of the Caption Builder sits (title, description, icons): the nine radios become a 3 x 3 grid over a
 * picture. The radios stay and keep the value; a cell checks its radio through iCheck, so the PRO handlers that move
 * the other parts to free rows run as before, and the grid follows them.
 */
(function ($) {
  'use strict';

  var ROWS = ['top', 'center', 'bottom'],
    COLUMNS = ['left', 'center', 'right'];

  function check(radio) {
    if ($.fn.iCheck && radio.parentNode && /iradio/.test(radio.parentNode.className)) {
      $(radio).iCheck('check');
    } else {
      radio.checked = true;
      $(radio).trigger('change');
    }
  }

  function labelOf(radio) {
    var label = radio.id ? document.querySelector('label[for="' + radio.id + '"]') : null;
    return label ? label.textContent.trim() : radio.value;
  }

  function build(radios) {
    var cell = radios[0].closest('td');
    if (!cell || cell.querySelector('.sgg-pos')) {
      return null;
    }
    var byValue = {};
    radios.forEach(function (radio) {
      byValue[radio.value] = radio;
    });
    var native = document.createElement('div'),
      picker = document.createElement('div'),
      grid = document.createElement('div'),
      caption = document.createElement('span');
    native.className = 'sgg-pos-native';
    while (cell.firstChild) {
      native.appendChild(cell.firstChild);
    }
    picker.className = 'sgg-pos';
    grid.className = 'sgg-pos-grid';
    grid.setAttribute('role', 'radiogroup');
    caption.className = 'sgg-pos-label';
    ROWS.forEach(function (row) {
      COLUMNS.forEach(function (column) {
        var radio = byValue[column + '_' + row],
          button = document.createElement('button');
        button.type = 'button';
        button.className = 'sgg-pos-cell is-h-' + column;
        button.setAttribute('role', 'radio');
        if (radio) {
          button.setAttribute('data-value', radio.value);
          button.setAttribute('aria-label', labelOf(radio));
          button.title = labelOf(radio);
        } else {
          button.disabled = true;
        }
        grid.appendChild(button);
      });
    });
    picker.appendChild(grid);
    picker.appendChild(caption);
    cell.appendChild(picker);
    cell.appendChild(native);

    function draw() {
      var current = radios.filter(function (radio) {
        return radio.checked;
      })[0];
      Array.prototype.forEach.call(grid.children, function (button) {
        var on = !!current && button.getAttribute('data-value') === current.value;
        button.classList.toggle('is-current', on);
        button.setAttribute('aria-checked', on ? 'true' : 'false');
        button.tabIndex = on || (!current && button === grid.children[4]) ? 0 : -1;
      });
      caption.textContent = current ? labelOf(current) : '';
    }

    grid.addEventListener('click', function (event) {
      var button = event.target.closest('.sgg-pos-cell');
      var radio = button ? byValue[button.getAttribute('data-value')] : null;
      if (radio && !radio.checked) {
        check(radio);
      }
    });
    // Arrow keys move inside the grid
    grid.addEventListener('keydown', function (event) {
      var steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -3, ArrowDown: 3 },
        buttons = Array.prototype.slice.call(grid.children),
        from = buttons.indexOf(document.activeElement);
      if (!(event.key in steps) || from < 0) {
        return;
      }
      event.preventDefault();
      var to = from + steps[event.key];
      if (to >= 0 && to < 9 && !buttons[to].disabled) {
        buttons[to].focus();
        buttons[to].click();
      }
    });

    return draw;
  }

  function init() {
    var groups = {},
      draws = [];
    Array.prototype.forEach.call(document.querySelectorAll('input.captionBuilderPosRadio[type="radio"]'), function (radio) {
      (groups[radio.name] = groups[radio.name] || []).push(radio);
    });
    Object.keys(groups).forEach(function (name) {
      var draw = build(groups[name]);
      if (draw) {
        draws.push(draw);
      }
    });
    if (!draws.length) {
      return;
    }
    function drawAll() {
      draws.forEach(function (draw) {
        draw();
      });
    }
    // Any part may move another one, so every grid draws again
    $(document).on('ifChanged change', 'input.captionBuilderPosRadio', function () {
      window.setTimeout(drawAll, 0);
    });
    drawAll();
  }

  $(function () {
    window.setTimeout(init, 0);
  });
})(jQuery);

/**
 * Shadow presets as cards: the photo of the gallery with the real shadow under it and the name; the chosen one
 * marked. Beside "Choose preset" a chip shows the shadow in use, drawn from the four shadow fields.
 */
(function ($) {
  'use strict';

  function cover() {
    var image = document.querySelector('img.ggImg');
    return image ? image.currentSrc || image.src || '' : '';
  }

  function init() {
    var dialog = document.getElementById('shadowDialog');
    if (!dialog || !$.fn.dialog || !$(dialog).dialog('instance')) {
      return;
    }
    var $dialog = $(dialog),
      presets = Array.prototype.slice.call(dialog.querySelectorAll('.shadow-preset'));
    $dialog.dialog('widget').addClass('gg-pth-dialog sgg-sh-dialog');

    // The cover gets its address after the editor starts (lazy loading), so it is taken each time
    function paint(scope) {
      var photo = cover();
      if (photo) {
        Array.prototype.forEach.call(scope.querySelectorAll('img.dialog-image, .sgg-sh-pic img'), function (image) {
          if (image.getAttribute('src') !== photo) {
            image.setAttribute('src', photo);
          }
        });
      }
    }

    presets.forEach(function (preset) {
      var card = preset.parentNode,
        image = preset.querySelector('img.dialog-image'),
        label = document.createElement('span');
      card.classList.add('sgg-sh-card');
      label.className = 'sgg-sh-name';
      label.textContent = card.getAttribute('data-sgg-name') || '';
      card.appendChild(label);
      if (image) {
        image.removeAttribute('data-src');
      }
      // A click anywhere on the card picks the preset
      card.addEventListener('click', function (event) {
        if (!event.target.closest('.shadow-preset')) {
          $(preset).trigger('click');
        }
      });
    });

    $dialog.on('dialogopen', function () {
      $dialog.dialog('option', 'width', Math.min(940, Math.max(320, window.innerWidth - 80)));
      paint(dialog);
    });
    paint(dialog);

    var button = document.getElementById('chooseShadowPreset');
    if (!button) {
      return;
    }
    var chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'sgg-pth-current sgg-sh-current';
    chip.innerHTML = '<span class="sgg-sh-pic"><img alt=""></span><span class="sgg-sh-current-name"></span>';
    button.parentNode.insertBefore(chip, button);
    chip.addEventListener('click', function () {
      $(button).trigger('click');
    });

    function field(name) {
      var input = document.querySelector('[name="thumbnail[shadow][' + name + ']"]');
      return input ? input.value : '';
    }

    function draw() {
      var x = parseInt(field('x'), 10) || 0,
        y = parseInt(field('y'), 10) || 0,
        blur = parseInt(field('blur'), 10) || 0,
        color = field('color') || '#000000',
        match = presets.filter(function (preset) {
          return parseInt(preset.getAttribute('data-offset-x'), 10) === x && parseInt(preset.getAttribute('data-offset-y'), 10) === y && parseInt(preset.getAttribute('data-blur'), 10) === blur;
        })[0];
      // Half size, as the picture is small
      chip.querySelector('.sgg-sh-pic').style.boxShadow = Math.round(x / 2) + 'px ' + Math.round(y / 2) + 'px ' + Math.round(blur / 2) + 'px ' + color;
      chip.querySelector('.sgg-sh-current-name').textContent = match ? match.parentNode.getAttribute('data-sgg-name') : dialog.getAttribute('data-sgg-own') || '';
      paint(chip);
      // The cover gets its address a moment after the editor starts
      if (!cover() && tries++ < 40) {
        window.setTimeout(draw, 250);
      }
    }
    var tries = 0;

    $(document).on('change input', '[name^="thumbnail[shadow]"]', draw);
    $(document).on('irischange', '[name="thumbnail[shadow][color]"]', function () {
      window.setTimeout(draw, 0);
    });
    $(presets).on('click', function () {
      window.setTimeout(draw, 0);
    });
    draw();
  }

  $(function () {
    window.setTimeout(init, 0);
  });
})(jQuery);

/**
 * Custom Buttons (PRO): the editor shows the buttons as the gallery does (categories, pages, Load more; the chosen
 * one in full color, the others lighter, like frontend.js), the presets as tiles with the modern ones first, and a
 * chip beside "Show preset Editor" with the style in use. The PRO dialog, its fields and its handlers stay.
 */
(function ($) {
  'use strict';

  var PX = { fontSize: 1, paddingV: 1, paddingH: 1, borderWidth: 1, borderRadius: 1 };

  // The same lighter tone as frontend.js gives the buttons that are not chosen
  function shade(color, percent) {
    var f = parseInt(color.slice(1), 16),
      t = percent < 0 ? 0 : 255,
      p = percent < 0 ? percent * -1 : percent,
      R = f >> 16,
      G = (f >> 8) & 0x00ff,
      B = f & 0x0000ff;
    return '#' + (0x1000000 + (Math.round((t - R) * p) + R) * 0x10000 + (Math.round((t - G) * p) + G) * 0x100 + (Math.round((t - B) * p) + B)).toString(16).slice(1);
  }

  function longHex(color) {
    color = String(color || '').trim();
    if (/^#[0-9a-f]{3}$/i.test(color)) {
      color = '#' + color.charAt(1) + color.charAt(1) + color.charAt(2) + color.charAt(2) + color.charAt(3) + color.charAt(3);
    }
    return /^#[0-9a-f]{6}$/i.test(color) ? color : '';
  }

  function apply(element, props, active) {
    var style = element.style,
      background = longHex(props.backgroundColor),
      hasBackground = !!props.backgroundColor && !/^(transparent|#fff|#ffffff)$/i.test(props.backgroundColor);
    style.cssText = '';
    Object.keys(props).forEach(function (key) {
      var value = props[key];
      if (value === '' || key === 'class') {
        return;
      }
      value = PX[key] ? value + 'px' : value;
      if (key === 'paddingV') {
        style.paddingTop = style.paddingBottom = value;
      } else if (key === 'paddingH') {
        style.paddingLeft = style.paddingRight = value;
      } else if (key === 'fontFamily') {
        style.fontFamily = value === 'Default' ? 'inherit' : value;
      } else {
        style[key] = value;
      }
    });
    if (!active) {
      if (hasBackground && background) {
        style.backgroundColor = shade(background, 0.5);
      } else if (longHex(props.color)) {
        style.color = shade(longHex(props.color), 0.5);
      }
    }
    // Only the classes of the plugin: the classes of a theme have no styles on this page
    element.className = element.className.replace(/(^|\s)sgg-btn\S*/g, '').trim();
    String(props['class'] || '')
      .split(/\s+/)
      .forEach(function (name) {
        if (/^sgg-btn/.test(name)) {
          element.classList.add(name);
        }
      });
  }

  function sameStyle(a, b) {
    return Object.keys(a).every(function (key) {
      return String(a[key] || '').toLowerCase() === String(b[key] || '').toLowerCase();
    });
  }

  function init() {
    var content = document.querySelector('.buttons-edit-preset-dialog'),
      list = document.getElementById('buttons-presets-list');
    if (!content || !list || !$.fn.dialog || !$(content).dialog('instance')) {
      return;
    }
    var $content = $(content),
      builtin = [],
      presets = {};
    try {
      builtin = JSON.parse(list.getAttribute('data-builtin') || '[]');
      presets = JSON.parse(list.getAttribute('data-presets-data') || '{}');
    } catch (error) {}
    $content.dialog('widget').addClass('gg-pth-dialog sgg-bp-dialog supsystic-plugin');
    $content.on('dialogopen', function () {
      $content.dialog('option', 'width', Math.min(1080, Math.max(320, window.innerWidth - 80)));
    });

    function props() {
      var out = {};
      Array.prototype.forEach.call(content.querySelectorAll('[data-property]'), function (input) {
        out[input.getAttribute('data-property')] = input.value || '';
      });
      return out;
    }

    // The presets: the modern ones under their own heading, before the saved and classic ones (the items stay
    // direct children of the list, which the PRO click handler needs)
    var heading = document.createElement('h4'),
      first = list.querySelector('p');
    heading.className = 'sgg-bp-head';
    heading.textContent = list.getAttribute('data-modern') || 'Modern';
    if (first) {
      first.classList.add('sgg-bp-head');
    }
    list.insertBefore(heading, list.firstChild);
    builtin
      .slice()
      .reverse()
      .forEach(function (name) {
        var item = list.querySelector('.presets-list-item[data-button-name="' + name.replace(/"/g, '\\"') + '"]');
        if (item) {
          var remove = item.querySelector('.fa-times');
          if (remove) {
            remove.remove();
          }
          item.classList.add('is-builtin');
          heading.parentNode.insertBefore(item, heading.nextSibling);
        }
      });
    function decorate() {
      Array.prototype.forEach.call(list.querySelectorAll('.presets-list-item > a'), function (link) {
        if (link.getAttribute('data-sgg-done')) {
          return;
        }
        link.setAttribute('data-sgg-done', '1');
        var preset = {};
        try {
          preset = JSON.parse(link.getAttribute('data-preset') || '{}');
        } catch (error) {}
        String(preset['class'] || '')
          .split(/\s+/)
          .forEach(function (name) {
            if (/^sgg-btn/.test(name)) {
              link.classList.add(name);
            }
          });
      });
    }
    decorate();
    // A preset saved now joins the list
    new MutationObserver(decorate).observe(list, { childList: true });

    // The samples, and the chip beside the button that opens the editor
    var samples = Array.prototype.slice.call(content.querySelectorAll('.sgg-bp-sample')),
      opener = document.querySelector('[name="buttons-presets-editor"]'),
      chip = null;
    if (opener) {
      chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'sgg-pth-current sgg-bp-current';
      chip.innerHTML = '<span class="sgg-bp-chip-stage"><span class="sgg-bp-chip-button"></span></span><span class="sgg-bp-current-name"></span>';
      chip.querySelector('.sgg-bp-chip-button').textContent = list.getAttribute('data-sample') || 'Button';
      opener.parentNode.insertBefore(chip, opener);
      chip.addEventListener('click', function () {
        $(opener).trigger('click');
      });
    }

    function draw() {
      var current = props();
      samples.forEach(function (sample) {
        apply(sample, current, sample.classList.contains('is-active') || sample.classList.contains('is-hover'));
      });
      if (chip) {
        apply(chip.querySelector('.sgg-bp-chip-button'), current, true);
        var name = Object.keys(presets).filter(function (key) {
          return sameStyle(presets[key], current);
        })[0];
        chip.querySelector('.sgg-bp-current-name').textContent = name || list.getAttribute('data-own') || '';
      }
    }

    // Hover shows the full color, as on the site
    samples.forEach(function (sample) {
      sample.addEventListener('mouseenter', function () {
        sample.classList.add('is-hover');
        draw();
      });
      sample.addEventListener('mouseleave', function () {
        sample.classList.remove('is-hover');
        draw();
      });
      sample.addEventListener('click', function (event) {
        event.preventDefault();
      });
    });

    // PRO restyles its own preview button after every change of a field or a preset: draw again then
    var legacy = document.getElementById('preview-button');
    if (legacy && window.MutationObserver) {
      new MutationObserver(draw).observe(legacy, { attributes: true, attributeFilter: ['style'] });
    }
    $(document).on('change input', '.buttons-edit-preset-dialog [data-property]', function () {
      window.setTimeout(draw, 0);
    });
    $(document).on('irischange', '.buttons-edit-preset-dialog .gg-color-picker-buttons', function () {
      window.setTimeout(draw, 0);
    });
    draw();
  }

  $(function () {
    window.setTimeout(init, 0);
  });
})(jQuery);
