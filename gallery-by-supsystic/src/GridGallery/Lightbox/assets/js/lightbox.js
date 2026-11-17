/**
 * The lightbox of Photo Gallery (PhotoSwipe 5, MIT): one lightbox for the classic gallery, the Carousel and the
 * Slider, in themes and in the colors of the gallery style.
 *
 * SggLightbox.open(items, index, config, hooks)
 *   items  [{ src, width, height, msrc, element, thumbCropped, alt, title, text, html, link }] (html: a video slide)
 *   config the front settings of GridGallery_Lightbox_Module (theme, colors, caption, tools, i18n)
 *   hooks  { onChange(index, item), onClose(index) }
 */
(function (window, document) {
  'use strict';

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    current = null;

  var ICONS = {
    fullscreen: '<path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5"/>',
    exit: '<path d="M9 4v5H4M20 9h-5V4M15 20v-5h5M4 15h5v5"/>',
    download: '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
    share: '<circle cx="18" cy="5" r="2.4"/><circle cx="6" cy="12" r="2.4"/><circle cx="18" cy="19" r="2.4"/><path d="M8.2 10.9l7.6-4.3M8.2 13.1l7.6 4.3"/>',
    play: '<path d="M8 5.5v13l10.5-6.5z" class="is-fill"/>',
    pause: '<path d="M7.5 5h3.2v14H7.5zM13.3 5h3.2v14h-3.2z" class="is-fill"/>',
  };

  function icon(name) {
    return '<svg class="sgg-lb-icn" viewBox="0 0 24 24" aria-hidden="true">' + ICONS[name] + '</svg>';
  }

  // The previous / next arrow: PhotoSwipe's own arrow, 10.5 units to the right, so it sits in the middle of its button
  var ARROW_SVG = '<svg aria-hidden="true" class="pswp__icn" viewBox="0 0 60 60" width="60" height="60">' +
    '<use class="pswp__icn-shadow" href="#sgg-lb-icn-arrow"/>' +
    '<path d="M39.5 43l-3 3-16-16 16-16 3 3-13 13 13 13z" id="sgg-lb-icn-arrow"/></svg>';

  function fullscreenElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
  }

  function toggleFullscreen(element) {
    if (fullscreenElement()) {
      (document.exitFullscreen || document.webkitExitFullscreen || function () {}).call(document);
      return;
    }
    var request = element.requestFullscreen || element.webkitRequestFullscreen;
    if (request) {
      request.call(element);
    }
  }

  function absolute(url) {
    var a = document.createElement('a');
    a.href = url || '';
    return a.href;
  }

  function close() {
    if (current) {
      current.close();
    }
  }

  // ---------- Parts of the interface, registered in PhotoSwipe's own toolbar and root
  function captionPart(config) {
    return {
      name: 'sgg-caption',
      order: 9,
      isButton: false,
      appendTo: 'root',
      onInit: function (el, pswp) {
        el.setAttribute('aria-live', 'polite');
        function update() {
          var data = pswp.currSlide ? pswp.currSlide.data : {},
            title = data.title || '',
            text = data.text || '';
          el.textContent = '';
          if (title) {
            var strong = document.createElement('strong');
            strong.className = 'sgg-lb-title';
            strong.textContent = title;
            el.appendChild(strong);
          }
          if (text && text !== title) {
            var span = document.createElement('span');
            span.className = 'sgg-lb-text';
            span.textContent = text;
            el.appendChild(span);
          }
          el.classList.toggle('is-empty', !title && !text);
        }
        pswp.on('change', update);
        update();
      },
    };
  }

  function thumbsPart(items) {
    return {
      name: 'sgg-thumbs',
      order: 10,
      isButton: false,
      appendTo: 'root',
      onInit: function (el, pswp) {
        var buttons = items.map(function (item, index) {
          var button = document.createElement('button'),
            image = document.createElement('img');
          button.type = 'button';
          button.className = 'sgg-lb-thumb' + (item.html ? ' is-video' : '');
          button.setAttribute('aria-label', String(index + 1));
          image.src = item.msrc || item.src || '';
          image.alt = '';
          image.loading = 'lazy';
          image.decoding = 'async';
          button.appendChild(image);
          button.addEventListener('click', function () {
            pswp.goTo(index);
          });
          el.appendChild(button);
          return button;
        });
        pswp.on('change', function () {
          buttons.forEach(function (button, index) {
            var on = index === pswp.currIndex;
            button.classList.toggle('is-current', on);
            button.setAttribute('aria-current', on ? 'true' : 'false');
            if (on && button.scrollIntoView) {
              var left = button.offsetLeft - (el.clientWidth - button.offsetWidth) / 2;
              el.scrollTo ? el.scrollTo({ left: left, behavior: reduced ? 'auto' : 'smooth' }) : (el.scrollLeft = left);
            }
          });
        });
      },
    };
  }

  function fullscreenPart(t) {
    return {
      name: 'sgg-fs',
      order: 8,
      isButton: true,
      title: t.fullscreen || 'Full screen',
      html: icon('fullscreen'),
      onClick: function (event, el, pswp) {
        toggleFullscreen(pswp.element);
      },
      onInit: function (el, pswp) {
        function sync() {
          el.innerHTML = icon(fullscreenElement() ? 'exit' : 'fullscreen');
        }
        document.addEventListener('fullscreenchange', sync);
        document.addEventListener('webkitfullscreenchange', sync);
        pswp.on('destroy', function () {
          document.removeEventListener('fullscreenchange', sync);
          document.removeEventListener('webkitfullscreenchange', sync);
        });
      },
    };
  }

  function downloadPart(t) {
    return {
      name: 'sgg-download',
      order: 7,
      isButton: true,
      tagName: 'a',
      title: t.download || 'Download',
      html: icon('download'),
      onInit: function (el, pswp) {
        el.setAttribute('download', '');
        el.setAttribute('target', '_blank');
        el.setAttribute('rel', 'noopener');
        pswp.on('change', function () {
          var data = pswp.currSlide ? pswp.currSlide.data : {};
          el.hidden = !data.src || !!data.html;
          el.href = data.src || '';
        });
      },
    };
  }

  function sharePart(t) {
    var menu = null;
    return {
      name: 'sgg-share',
      order: 6,
      isButton: true,
      title: t.share || 'Share',
      html: icon('share'),
      onClick: function (event) {
        event.stopPropagation();
        if (menu) {
          menu.hidden = !menu.hidden;
        }
      },
      onInit: function (el, pswp) {
        menu = document.createElement('div');
        menu.className = 'sgg-lb-share';
        menu.hidden = true;
        pswp.element.appendChild(menu);
        function build() {
          var data = pswp.currSlide ? pswp.currSlide.data : {},
            url = data.link || window.location.href,
            title = data.title || document.title,
            media = absolute(data.src || data.msrc || ''),
            links = [
              ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url)],
              ['X', 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(title)],
              ['Pinterest', 'https://pinterest.com/pin/create/button/?url=' + encodeURIComponent(url) + '&media=' + encodeURIComponent(media) + '&description=' + encodeURIComponent(title)],
            ];
          menu.textContent = '';
          links.forEach(function (link) {
            var a = document.createElement('a');
            a.href = link[1];
            a.target = '_blank';
            a.rel = 'noopener nofollow';
            a.textContent = link[0];
            menu.appendChild(a);
          });
          var copy = document.createElement('button');
          copy.type = 'button';
          copy.textContent = t.copy || 'Copy link';
          copy.addEventListener('click', function () {
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(url).then(function () {
                copy.textContent = t.copied || 'Link copied';
              });
            }
          });
          menu.appendChild(copy);
        }
        pswp.on('change', function () {
          menu.hidden = true;
          build();
        });
        pswp.element.addEventListener('click', function (event) {
          if (!menu.hidden && !menu.contains(event.target) && !el.contains(event.target)) {
            menu.hidden = true;
          }
        });
      },
    };
  }

  // The slideshow: a play button and a progress line; it stops on close and when the visitor zooms in
  function slideshowParts(t, options) {
    var timer = null,
      playing = false,
      bar = null,
      button = null,
      delay = options.delay || 4000;

    function render() {
      if (button) {
        button.innerHTML = icon(playing ? 'pause' : 'play');
        button.title = playing ? t.pause || 'Pause' : t.play || 'Play';
        button.setAttribute('aria-pressed', playing ? 'true' : 'false');
      }
    }

    function schedule(pswp) {
      window.clearTimeout(timer);
      if (!bar) {
        return;
      }
      bar.style.transition = 'none';
      bar.style.transform = 'scaleX(0)';
      if (!playing) {
        return;
      }
      // Restart the line: read the layout once so the next transition starts from zero
      void bar.offsetWidth;
      bar.style.transition = 'transform ' + delay + 'ms linear';
      bar.style.transform = 'scaleX(1)';
      timer = window.setTimeout(function () {
        pswp.next();
      }, delay);
    }

    return [
      {
        name: 'sgg-play',
        order: 5,
        isButton: true,
        title: t.play || 'Play',
        html: icon('play'),
        onClick: function (event, el, pswp) {
          playing = !playing;
          render();
          schedule(pswp);
        },
        onInit: function (el, pswp) {
          button = el;
          playing = !!options.auto && !reduced;
          render();
          pswp.on('change', function () {
            schedule(pswp);
          });
          pswp.on('zoomPanUpdate', function () {
            if (playing && pswp.currSlide && pswp.currSlide.currZoomLevel > pswp.currSlide.zoomLevels.initial + 0.01) {
              playing = false;
              render();
              schedule(pswp);
            }
          });
          pswp.on('close', function () {
            playing = false;
            window.clearTimeout(timer);
          });
        },
      },
      {
        name: 'sgg-progress',
        order: 11,
        isButton: false,
        appendTo: 'root',
        html: '<i></i>',
        onInit: function (el, pswp) {
          bar = el.firstChild;
          schedule(pswp);
        },
      },
    ];
  }

  function open(items, index, config, hooks) {
    if (typeof window.PhotoSwipe !== 'function' || !items || !items.length) {
      return null;
    }
    config = config || {};
    hooks = hooks || {};
    close();

    var t = config.i18n || {},
      theme = config.theme || 'style',
      caption = config.caption || 'below',
      thumbs = !!config.thumbs && items.length > 1,
      idle = theme === 'minimal' || theme === 'cinema',
      classes = ['sgg-lb', 'sgg-lb--' + theme, 'sgg-lb-arrows-' + (config.arrows || 'circle'), 'sgg-lb-cap-' + caption];
    if (thumbs) {
      classes.push('sgg-lb-has-thumbs');
    }
    if (config.slideshow) {
      classes.push('sgg-lb-has-progress');
    }

    var pswp = new window.PhotoSwipe({
      dataSource: items,
      index: Math.max(0, Math.min(items.length - 1, index || 0)),
      bgOpacity: theme === 'glass' ? 1 : config.opacity || 0.92,
      showHideAnimationType: reduced ? 'none' : config.anim === 'fade' || config.anim === 'none' ? config.anim : 'zoom',
      mainClass: classes.join(' '),
      counter: config.counter !== false && items.length > 1,
      zoom: config.zoom !== false,
      arrowPrev: config.arrows !== 'none',
      arrowNext: config.arrows !== 'none',
      // PhotoSwipe draws its arrow in the left part of the 60 x 60 icon (x 10..29), so in the round or square
      // button it sat off centre, toward the outer edge. This one is the same arrow moved to the middle; the next
      // arrow is the same icon, mirrored by photoswipe.css.
      arrowPrevSVG: ARROW_SVG,
      arrowNextSVG: ARROW_SVG,
      closeTitle: t.close,
      zoomTitle: t.zoom,
      arrowPrevTitle: t.prev,
      arrowNextTitle: t.next,
      errorMsg: t.error,
      wheelToZoom: true,
      // Room for the parts around the photo: the caption bar, the thumbnails, the side panel
      paddingFn: function (viewport) {
        var small = viewport.x < 700,
          room = { top: small ? 50 : 62, bottom: small ? 12 : 22, left: small ? 0 : 76, right: small ? 0 : 76 };
        if (theme === 'cinema') {
          return { top: 0, bottom: 0, left: 0, right: 0 };
        }
        if (thumbs) {
          room.bottom += small ? 66 : 86;
        }
        if (caption === 'below') {
          room.bottom += small ? 54 : 62;
        }
        if (caption === 'side') {
          if (small) {
            room.bottom += 96;
          } else {
            room.right = 380;
          }
        }
        if (theme === 'polaroid') {
          room.top += 18;
          room.bottom += caption === 'below' ? 0 : 30;
          room.left += 18;
          room.right += 18;
        }
        return room;
      },
    });

    pswp.on('uiRegister', function () {
      // The colors of the theme or the style, as variables of the lightbox
      var style = pswp.element.style;
      style.setProperty('--sgg-lb-bg', config.bg || '#0b0f19');
      style.setProperty('--sgg-lb-ui', config.ui || '#f8fafc');
      style.setProperty('--sgg-lb-accent', config.accent || '#0e8a93');
      style.setProperty('--sgg-lb-on-accent', config.onAccent || '#ffffff');
      style.setProperty('--pswp-bg', config.bg || '#0b0f19');
      style.setProperty('--pswp-icon-color', config.ui || '#f8fafc');
      style.setProperty('--pswp-icon-color-secondary', config.bg || '#0b0f19');

      if (caption !== 'none') {
        pswp.ui.registerElement(captionPart(config));
      }
      if (thumbs) {
        pswp.ui.registerElement(thumbsPart(items));
      }
      if (config.slideshow && items.length > 1) {
        slideshowParts(t, config.slideshow).forEach(function (part) {
          pswp.ui.registerElement(part);
        });
      }
      if (config.share) {
        pswp.ui.registerElement(sharePart(t));
      }
      if (config.download) {
        pswp.ui.registerElement(downloadPart(t));
      }
      if (config.fullscreen && (document.fullscreenEnabled || document.webkitFullscreenEnabled)) {
        pswp.ui.registerElement(fullscreenPart(t));
      }
    });

    // Minimal and Cinema: the controls show while the mouse moves
    var idleTimer = null;
    function wake() {
      pswp.element.classList.add('sgg-lb-awake');
      window.clearTimeout(idleTimer);
      idleTimer = window.setTimeout(function () {
        if (pswp.element) {
          pswp.element.classList.remove('sgg-lb-awake');
        }
      }, 2200);
    }

    pswp.on('change', function () {
      if (hooks.onChange) {
        hooks.onChange(pswp.currIndex, pswp.currSlide ? pswp.currSlide.data : null);
      }
    });
    pswp.on('close', function () {
      window.clearTimeout(idleTimer);
      if (fullscreenElement()) {
        toggleFullscreen(pswp.element);
      }
      if (hooks.onClose) {
        hooks.onClose(pswp.currIndex);
      }
    });
    pswp.on('destroy', function () {
      if (current === pswp) {
        current = null;
      }
    });

    pswp.init();
    current = pswp;
    if (idle && pswp.element) {
      pswp.element.addEventListener('pointermove', wake);
      pswp.element.addEventListener('keydown', wake);
      wake();
    }
    return pswp;
  }

  // A video link (YouTube, Vimeo or a file) as the html of a slide
  function videoHtml(url) {
    var match;
    if ((match = /(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:[^#]*&)?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/.exec(url))) {
      return '<div class="sgg-lb-video"><iframe src="https://www.youtube-nocookie.com/embed/' + match[1] + '?autoplay=1&rel=0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
    }
    if ((match = /vimeo\.com\/(?:video\/)?(\d+)/.exec(url))) {
      return '<div class="sgg-lb-video"><iframe src="https://player.vimeo.com/video/' + match[1] + '?autoplay=1&dnt=1" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
    }
    var video = document.createElement('video');
    video.src = url;
    return '<div class="sgg-lb-video"><video src="' + video.src.replace(/"/g, '%22') + '" controls autoplay playsinline></video></div>';
  }

  window.SggLightbox = { open: open, close: close, videoHtml: videoHtml };
})(window, document);
