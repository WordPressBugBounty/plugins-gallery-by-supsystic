/**
 * Pictures of a gallery in the colors and shape of a style: the cards of the Style library and the creation wizard
 * (small, lines instead of words) and the live picture of the wizard (big, with words and the chosen images).
 * Every goal of the wizard has its own layout; the shape of the style decides corners, spacing, frame, shadow,
 * arrows, pagination and captions.
 *
 * SggStyleMock.html(type, swatch, shape, options) returns the markup; options: goal, images (urls), big, labels and
 * extras, the switches of the Additional step of the wizard (option id => true/false). Without extras every picture
 * looks as it always did.
 */
(function (window) {
  'use strict';

  var defaults = { radius: 8, gap: 8, frame: 0, shadow: 'none', arrows: 'circle', arrowPos: 'inside', dots: 'bullets', captions: 'below', align: 'left' };
  var goalsOf = {
    gallery: ['grid', 'justified', 'masonry', 'rounded', 'categories', 'postfeed', 'mosaic', 'fixed', 'horizontal', 'polaroid'],
    carousel: ['carousel', 'logos', 'products', 'posts_carousel'],
    slider: ['slider', 'hero', 'compare', 'viewer', 'posts_slider'],
  };

  function esc(text) {
    return String(text).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // A url for a style attribute: quotes, brackets and spaces are escaped, so the value stays one url
  function cssUrl(url) {
    return "url('" + String(url).replace(/['"()\\\s]/g, function (c) {
      return '%' + c.charCodeAt(0).toString(16).toUpperCase();
    }) + "')";
  }

  // White or dark ink, whichever reads better on a color (the same rule as readableOn() of the Carousel module)
  function readableOn(hex) {
    var light = 0;
    if (!/^#[0-9a-f]{6}/i.test(hex || '')) {
      return '#ffffff';
    }
    [0.2126, 0.7152, 0.0722].forEach(function (weight, index) {
      var channel = parseInt(hex.substr(1 + index * 2, 2), 16) / 255;
      light += weight * (channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4));
    });
    return light > 0.2 ? '#0f172a' : '#ffffff';
  }

  function Builder(type, swatch, shape, options) {
    this.type = type;
    this.swatch = swatch;
    this.shape = shape;
    this.o = options;
    this.big = !!options.big;
    this.labels = options.labels || {};
    this.images = options.images || [];
    this.extras = options.extras || null;
    this.n = 0;
  }

  // A switch of the Additional step: its value when the picture has them, else how the picture always looked
  Builder.prototype.has = function (key, fallback) {
    return this.extras ? !!this.extras[key] : !!fallback;
  };

  // The image after the shown one, for the fade between slides
  Builder.prototype.nextImage = function () {
    return this.images.length > 1 ? this.images[1] : '';
  };

  // Magnifier over an image that opens in the lightbox
  Builder.prototype.zoom = function () {
    return this.has('lightbox', false)
      ? '<i class="sgg-mk-zoom"><svg viewBox="0 0 24 24" focusable="false"><circle cx="10.5" cy="10.5" r="6.2"></circle><path d="M15.1 15.1 19.6 19.6"></path></svg></i>'
      : '';
  };

  // ---------- Around a classic gallery: category buttons, pages, Load more, the scroll bar, a lightbox with tools
  Builder.prototype.cats = function () {
    if (!this.big) {
      return '<span class="sgg-mk-filters"><u class="is-on"></u><u></u><u></u><u></u></span>';
    }
    var labels = this.labels;
    return '<span class="sgg-mk-bt is-cats">' + [['all', 'All'], ['cat1', 'Nature'], ['cat2', 'City'], ['cat3', 'People']].map(function (item, index) {
      return '<b' + (index === 0 ? ' class="is-on"' : '') + '>' + esc(labels[item[0]] || item[1]) + '</b>';
    }).join('') + '</span>';
  };

  Builder.prototype.pages = function () {
    if (!this.big) {
      return '<em class="sgg-mk-pages"><u class="is-on"></u><u></u><u></u></em>';
    }
    return '<span class="sgg-mk-bt is-pages"><b class="is-on">1</b><b>2</b><b>3</b><b class="is-next">&rsaquo;</b></span>';
  };

  Builder.prototype.more = function () {
    return '<span class="sgg-mk-bt is-more"><b class="is-on">' + (this.big ? esc(this.labels.loadMore || 'Load more') : '') + '</b></span>';
  };

  Builder.prototype.hbar = function () {
    return '<i class="sgg-mk-hbar"><b></b></i>';
  };

  // A small lightbox in the corner: the photo, its thumbnails and the line of the slideshow
  Builder.prototype.minilb = function () {
    var image = this.images.length ? this.images[0] : '',
      thumbs = '';
    for (var i = 0; i < 4; i++) {
      var src = this.images.length ? this.images[i % this.images.length] : '';
      thumbs += '<i' + (i === 0 ? ' class="is-on"' : '') + (src ? ' style="' + esc('background-image:' + cssUrl(src)) + '"' : '') + '></i>';
    }
    return '<span class="sgg-mk-mlb"><i class="sgg-mk-mlb-photo"' + (image ? ' style="' + esc('background-image:' + cssUrl(image)) + '"' : '') + '></i>' +
      '<i class="sgg-mk-mlb-play"></i><i class="sgg-mk-mlb-line"><b></b></i><span class="sgg-mk-mlb-strip">' + thumbs + '</span></span>';
  };

  // The progress line of autoplay along the top of the slides
  Builder.prototype.auto = function () {
    return this.has('autoplay', false) ? '<i class="sgg-mk-auto"></i>' : '';
  };

  Builder.prototype.word = function (key, fallback) {
    return this.big ? '<span class="sgg-mk-tx">' + esc(this.labels[key] || fallback) + '</span>' : '<i class="sgg-mk-ln is-strong"></i>';
  };

  Builder.prototype.tile = function (cls, inner, style) {
    var index = this.n++,
      image = this.images.length ? this.images[index % this.images.length] : '',
      css = (style || '') + (image ? 'background-image:' + cssUrl(image) + ';' : '');
    return '<i class="sgg-mk-t is-n' + (index % 3) + (cls ? ' ' + cls : '') + '"' + (css ? ' style="' + esc(css) + '"' : '') + '>' + (inner || '') + '</i>';
  };

  // Caption on hover of a classic gallery: a strip at the bottom with the title, in the overlay colors of the style
  Builder.prototype.hover = function () {
    return (this.has('captions', true) ? '<i class="sgg-mk-ov">' + this.word('title', 'Photo title') + '</i>' : '') + this.zoom();
  };

  Builder.prototype.caption = function () {
    return this.word('title', 'Photo title') + '<i class="sgg-mk-ln"></i>';
  };

  Builder.prototype.arrows = function () {
    return this.has('arrows', true) ? '<b class="sgg-mk-arw is-prev"></b><b class="sgg-mk-arw is-next"></b>' : '';
  };

  Builder.prototype.pager = function () {
    if (!this.has('dots', true)) {
      return '';
    }
    switch (this.shape.dots) {
      case 'fraction':
        return '<em class="sgg-mk-pg is-fraction">2 / 6</em>';
      case 'progressbar':
        return '<em class="sgg-mk-pg is-progress"><i></i></em>';
      case 'dynamic':
        return '<em class="sgg-mk-pg"><u class="is-xs"></u><u class="is-s"></u><u class="is-on"></u><u class="is-s"></u><u class="is-xs"></u></em>';
      default:
        return '<em class="sgg-mk-pg"><u></u><u class="is-on"></u><u></u></em>';
    }
  };

  // ---------- Classic gallery
  Builder.prototype.grid = function () {
    var html = '';
    for (var i = 0; i < 6; i++) {
      html += this.tile(i === 1 ? 'is-hover' : '', i === 1 ? this.hover() : '');
    }
    return '<span class="sgg-mk-stage is-grid">' + html + '</span>';
  };

  Builder.prototype.justified = function () {
    var rows = this.big ? [[1.6, 1, 1.35], [1, 1.7, 1.15], [1.25, 1, 1.6]] : [[1.6, 1, 1.35], [1, 1.7, 1.15]],
      self = this;
    return '<span class="sgg-mk-stage is-rows">' + rows.map(function (row, r) {
      return '<span class="sgg-mk-row">' + row.map(function (weight, i) {
        var hover = r === 0 && i === 1;
        return self.tile(hover ? 'is-hover' : '', hover ? self.hover() : '', 'flex-grow:' + weight + ';');
      }).join('') + '</span>';
    }).join('') + '</span>';
  };

  // Fixed: tiles of one set size in centred rows; the last row stays short
  Builder.prototype.fixed = function () {
    var html = '';
    for (var i = 0; i < 6; i++) {
      html += this.tile(i === 1 ? 'is-hover' : '', i === 1 ? this.hover() : '');
    }
    return '<span class="sgg-mk-stage is-fixed">' + html + '</span>';
  };

  // Horizontal: rows of one height, every photo in its own width, so the rows end unevenly
  Builder.prototype.horizontal = function () {
    var rows = [[34, 22, 28], [26, 40]],
      self = this;
    return '<span class="sgg-mk-stage is-hrows">' + rows.map(function (row, r) {
      return '<span class="sgg-mk-row">' + row.map(function (width, i) {
        var hover = r === 0 && i === 1;
        return self.tile(hover ? 'is-hover' : '', hover ? self.hover() : '', 'flex:none;width:calc(' + width + ' * var(--u) * 1.6);');
      }).join('') + '</span>';
    }).join('') + '</span>';
  };

  Builder.prototype.masonry = function () {
    var cols = [[1.35, 0.8], [0.7, 1.45], [1.05, 1.1]],
      self = this;
    return '<span class="sgg-mk-stage is-cols">' + cols.map(function (col, c) {
      return '<span class="sgg-mk-col">' + col.map(function (weight, i) {
        var hover = c === 1 && i === 1;
        return self.tile(hover ? 'is-hover' : '', hover ? self.hover() : '', 'flex-grow:' + weight + ';');
      }).join('') + '</span>';
    }).join('') + '</span>';
  };

  Builder.prototype.rounded = function () {
    var html = '';
    for (var i = 0; i < 8; i++) {
      html += '<span class="sgg-mk-person">' + this.tile('is-round' + (i === 1 ? ' is-hover' : ''), i === 1 ? this.hover() : '') + '</span>';
    }
    return '<span class="sgg-mk-stage is-people">' + html + '</span>';
  };

  Builder.prototype.categories = function () {
    var html = '';
    for (var i = 0; i < 6; i++) {
      html += this.tile(i === 1 ? 'is-hover' : '', i === 1 ? '<i class="sgg-mk-icons"><b></b><b></b></i>' : '');
    }
    return (this.has('categories', true) ? this.cats() : '') + '<span class="sgg-mk-stage is-grid">' + html + '</span>';
  };

  Builder.prototype.postfeed = function () {
    var html = '';
    for (var i = 0; i < 3; i++) {
      html += '<span class="sgg-mk-card">' + this.tile() + '<i class="sgg-mk-ln is-date"></i>' + this.word('post', 'Post title') + '<i class="sgg-mk-ln"></i></span>';
    }
    return '<span class="sgg-mk-stage is-cards">' + html + '</span>' + (this.has('pagination', true) ? this.pages() : '');
  };

  Builder.prototype.mosaic = function () {
    var html = '';
    for (var i = 0; i < 5; i++) {
      html += this.tile(i === 0 ? 'is-hover' : '', i === 0 ? this.hover() : '');
    }
    return '<span class="sgg-mk-stage is-mosaic">' + html + '</span>';
  };

  // Polaroid: straight instant-photo frames in the paper and ink of the style, the title under each photo (the tilt is
  // an option of the gallery settings); the second one is under the mouse and a little larger
  Builder.prototype.polaroid = function () {
    var html = '';
    for (var i = 0; i < 6; i++) {
      html += '<span class="sgg-mk-pol' + (i === 1 ? ' is-lit' : '') + '">' +
        this.tile('', i === 1 ? this.zoom() : '') + '<span class="sgg-mk-pol-cap">' + this.word('title', 'Photo title') + '</span></span>';
    }
    return '<span class="sgg-mk-stage is-polaroid">' + html + '</span>';
  };

  // ---------- Carousel
  // The running line moves a doubled row of slides; the arrows stay where they are
  Builder.prototype.slides = function (count, inner) {
    var html = '',
      marquee = this.has('marquee', false);
    for (var i = 0; i < (marquee ? count * 2 : count); i++) {
      html += inner.call(this, i % count);
    }
    if (marquee) {
      html = '<span class="sgg-mk-track">' + html + '</span>';
    }
    return '<span class="sgg-mk-stage is-row">' + html + this.auto() + this.arrows() + '</span>' + this.pager();
  };

  Builder.prototype.carousel = function () {
    var overlay = this.shape.captions === 'overlay',
      captions = this.has('captions', true);
    return this.slides(3, function (index) {
      var inner = (overlay && captions ? '<i class="sgg-mk-ov">' + this.caption() + '</i>' : '') + (index === 1 ? this.zoom() : '');
      return '<span class="sgg-mk-slide' + (index === 1 ? ' is-lit' : '') + '">' + this.tile('', inner) + (overlay || !captions ? '' : '<span class="sgg-mk-below">' + this.caption() + '</span>') + '</span>';
    });
  };

  Builder.prototype.logos = function () {
    var html = '',
      marquee = this.has('marquee', false);
    for (var i = 0; i < (marquee ? 12 : 6); i++) {
      html += '<span class="sgg-mk-logo">' + (this.images.length ? this.tile('is-contain') : '<i class="sgg-mk-glyph is-g' + (i % 3) + '"></i>') + '</span>';
    }
    if (marquee) {
      html = '<span class="sgg-mk-track">' + html + '</span>';
    }
    // A logo marquee has no arrows or pagination unless the Additional step turns them on
    return '<span class="sgg-mk-stage is-logos">' + html + (this.extras ? this.arrows() : '') + '</span>' + (this.extras ? this.pager() : '<i class="sgg-mk-flow"></i>');
  };

  Builder.prototype.products = function () {
    return this.slides(3, function () {
      return '<span class="sgg-mk-card is-product">' + this.tile() + this.word('post', 'Product') + '<span class="sgg-mk-price">' + (this.big ? '$29.00' : '') + '</span><i class="sgg-mk-btn">' + (this.big ? esc(this.labels.cart || 'Add to cart') : '') + '</i></span>';
    });
  };

  Builder.prototype.posts_carousel = function () {
    return this.slides(3, function () {
      return '<span class="sgg-mk-card">' + this.tile() + '<i class="sgg-mk-ln is-date"></i>' + this.word('post', 'Post title') + '<i class="sgg-mk-ln"></i></span>';
    });
  };

  // ---------- Slider
  // One slide: the 3D Cube shows the side of the next slide; the strip of thumbnails goes under it when it is on
  Builder.prototype.one = function (inner, plain) {
    return '<span class="sgg-mk-stage is-one">' + inner + this.face() + this.auto() + (plain ? '' : this.arrows() + this.pager()) + '</span>' + (this.has('thumbs', false) ? this.thumbs() : '');
  };

  Builder.prototype.face = function () {
    var next = this.nextImage();
    return this.has('effect3d', false) ? '<i class="sgg-mk-t is-face is-n1"' + (next ? ' style="' + esc('background-image:' + cssUrl(next)) + '"' : '') + '></i>' : '';
  };

  Builder.prototype.thumbs = function () {
    var html = '';
    for (var i = 0; i < 5; i++) {
      html += this.tile(i === 0 ? 'is-on' : '');
    }
    return '<span class="sgg-mk-thumbs">' + html + '</span>';
  };

  // The next image fading in over the slide
  Builder.prototype.fade = function () {
    if (!this.has('fade', false)) {
      return '';
    }
    var next = this.nextImage();
    return '<i class="sgg-mk-fade"' + (next ? ' style="' + esc('background-image:' + cssUrl(next)) + '"' : '') + '></i>';
  };

  Builder.prototype.slider = function () {
    return this.one(this.tile('is-wide', this.fade() + (this.has('captions', true) ? '<i class="sgg-mk-box">' + this.caption() + '</i>' : '') + this.zoom()));
  };

  Builder.prototype.hero = function () {
    var words = this.big
      ? '<b class="sgg-mk-head">' + esc(this.labels.heading || 'Your heading') + '</b><i class="sgg-mk-ln is-light"></i><i class="sgg-mk-btn">' + esc(this.labels.button || 'Learn more') + '</i>'
      : '<i class="sgg-mk-ln is-head"></i><i class="sgg-mk-ln is-light"></i><i class="sgg-mk-btn"></i>';
    return this.one(this.tile('is-wide is-hero', this.fade() + '<i class="sgg-mk-shade"></i><i class="sgg-mk-hero">' + words + '</i>'));
  };

  Builder.prototype.compare = function () {
    var after = this.images.length > 1 ? this.images[1] : '',
      tag = function (key, fallback, big, labels) {
        return '<i class="sgg-mk-tag is-' + key + '">' + (big ? esc(labels[key] || fallback) : '') + '</i>';
      };
    var before = this.tile('is-wide is-before', '<i class="sgg-mk-after"' + (after ? ' style="' + esc('background-image:' + cssUrl(after)) + '"' : '') + '></i>' +
      tag('before', 'Before', this.big, this.labels) + tag('after', 'After', this.big, this.labels) + '<i class="sgg-mk-handle"></i>');
    return this.one(before);
  };

  // Image viewer: one large photo, a strip of thumbnails under it with the shown one marked
  Builder.prototype.viewer = function () {
    var main = this.tile('is-wide', this.fade() + this.zoom());
    return '<span class="sgg-mk-stage is-one">' + main + this.face() + this.auto() + this.arrows() + (this.has('dots', false) ? this.pager() : '') + '</span>' + (this.has('thumbs', true) ? this.thumbs() : '');
  };

  Builder.prototype.posts_slider = function () {
    var words = '<i class="sgg-mk-ln is-date"></i>' + (this.big ? '<b class="sgg-mk-head">' + esc(this.labels.post || 'Post title') + '</b>' : '<i class="sgg-mk-ln is-head"></i>') +
      '<i class="sgg-mk-btn">' + (this.big ? esc(this.labels.more || 'Read more') : '') + '</i>';
    return this.one(this.tile('is-wide', this.fade() + '<i class="sgg-mk-shade is-bottom"></i><i class="sgg-mk-story">' + words + '</i>'));
  };

  function html(type, swatch, shape, options) {
    options = options || {};
    shape = shape || {};
    for (var key in defaults) {
      if (Object.prototype.hasOwnProperty.call(defaults, key) && shape[key] === undefined) {
        shape[key] = defaults[key];
      }
    }
    type = goalsOf[type] ? type : 'gallery';
    var goal = goalsOf[type].indexOf(options.goal) !== -1 ? options.goal : goalsOf[type][0],
      builder = new Builder(type, swatch, shape, options),
      big = !!options.big,
      radius = goal === 'rounded' ? 999 : shape.radius,
      gallery = type === 'gallery',
      vars = [
        '--h:' + swatch.head,
        '--ht:' + swatch.headText,
        '--sf:' + swatch.surface,
        '--tx:' + swatch.text,
        '--sp:' + swatch.stripe,
        '--ac:' + swatch.accent,
        '--oa:' + readableOn(swatch.accent),
        '--bd:' + swatch.border,
        '--fc:' + (shape.frame > 1 ? swatch.surface : swatch.border),
        '--r:' + Math.min(big ? 28 : 12, Math.round(radius * (big ? 1 : 0.55))) + 'px',
        '--sg:' + (gallery ? Math.max(2, Math.round(shape.gap * (big ? 0.9 : 0.45))) : big ? 12 : 5) + 'px',
        '--fw:' + (gallery ? Math.min(big ? 6 : 3, shape.frame) : 0) + 'px',
      ],
      classes = [
        'sgg-mk',
        'is-' + type,
        'is-goal-' + goal,
        'is-arw-' + shape.arrows,
        'is-pos-' + shape.arrowPos,
        'is-dots-' + shape.dots,
        'is-cap-' + shape.captions,
        'is-al-' + shape.align,
        'is-sh-' + shape.shadow,
        'is-pre-' + (shape.preset || 'clean'),
        big ? 'is-big' : 'is-small',
      ],
      body = builder[goal]();
    // The switches of the Additional step that change the whole picture
    if (options.extras) {
      var x = options.extras;
      vars.push('--br:' + (shape.radius >= 14 ? '999px' : Math.min(big ? 14 : 6, Math.round(shape.radius * (big ? 0.9 : 0.5))) + 'px'));
      [['hscroll', 'has-hscroll'], ['autoplay', 'has-auto'], ['marquee', 'has-marquee'], ['effect3d', 'has-3d'], ['vertical', 'has-vertical'], ['animations', 'has-anim']].forEach(function (pair) {
        if (x[pair[0]]) {
          classes.push(pair[1]);
        }
      });
      if (x.grayscale) {
        classes.push('has-gray');
      } else if (goal === 'logos') {
        classes.push('no-gray');
      }
      if (gallery) {
        body = (x.categories && goal !== 'categories' ? builder.cats() : '') + body +
          (x.hscroll ? builder.hbar() : '') +
          (x.pagination && goal !== 'postfeed' ? builder.pages() : '') +
          (x.loadmore ? builder.more() : '');
      }
      if (x.lightboxPro && big) {
        body += builder.minilb();
      }
    }
    return '<span class="' + classes.join(' ') + '" style="' + vars.join(';') + '" aria-hidden="true">' + body + '</span>';
  }

  /**
   * A lightbox theme: the backdrop, the photo, the controls and where the caption goes.
   * @param {string} theme style, dark, light, glass, minimal, side, filmstrip, polaroid or cinema
   * @param {{bg: string, ui: string, accent: string}} colors
   */
  function lightbox(theme, colors) {
    var parts = ['<i class="sgg-mk-lb-bg"></i>'];
    if (theme === 'glass') {
      parts.unshift('<i class="sgg-mk-lb-page"><b></b><b></b><b></b></i>');
    }
    if (theme !== 'cinema') {
      parts.push('<i class="sgg-mk-lb-top"><b class="is-count"></b><b class="is-close"></b></i>');
      parts.push('<i class="sgg-mk-lb-arw is-prev"></i><i class="sgg-mk-lb-arw is-next"></i>');
    }
    parts.push('<i class="sgg-mk-lb-photo"></i>');
    if (theme === 'side') {
      parts.push('<i class="sgg-mk-lb-side"><b class="is-title"></b><b></b><b class="is-short"></b></i>');
    } else if (theme === 'glass') {
      parts.push('<i class="sgg-mk-lb-cap is-card"><b class="is-title"></b><b class="is-short"></b></i>');
    } else if (theme !== 'cinema') {
      parts.push('<i class="sgg-mk-lb-cap"><b class="is-title"></b>' + (theme === 'polaroid' ? '' : '<b class="is-short"></b>') + '</i>');
    }
    if (theme === 'filmstrip') {
      parts.push('<i class="sgg-mk-lb-strip"><b></b><b></b><b class="is-on"></b><b></b><b></b></i>');
    }
    var vars = ['--lb-bg:' + colors.bg, '--lb-ui:' + colors.ui, '--lb-ac:' + colors.accent].join(';');
    return '<span class="sgg-mk sgg-mk-lb is-lb-' + esc(theme) + '" style="' + esc(vars) + '" aria-hidden="true">' + parts.join('') + '</span>';
  }

  window.SggStyleMock = { html: html, goals: goalsOf, lightbox: lightbox };
})(window);
