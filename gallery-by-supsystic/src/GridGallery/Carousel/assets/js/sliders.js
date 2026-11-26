/**
 * Carousel and Slider of Photo Gallery (Swiper 14, PhotoSwipe 5). The Swiper options are built here from the
 * settings in data-sgg-sl, so the editor's live preview runs exactly the same code. On the page the screen decides
 * the device (desktop, tablet, phone), the way the editor names them; a slider in a narrow place (a sidebar, a small
 * column) steps down so its slides stay readable. In the editor's preview the device switch above the frame decides.
 */
(function () {
  'use strict';

  var ONE_SLIDE_EFFECTS = ['fade', 'cube', 'flip', 'cards', 'creative'];
  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function readConfig(root) {
    try {
      return JSON.parse(root.getAttribute('data-sgg-sl') || '{}');
    } catch (e) {
      return {};
    }
  }

  function deviceFor(width, breakpoints) {
    if (width >= breakpoints.d) {
      return 'd';
    }
    return width >= breakpoints.t ? 't' : 'm';
  }

  var RANK = { m: 0, t: 1, d: 2 };

  function inFrame(root) {
    return !!(root.closest && root.closest('.sgg-sle-frame'));
  }

  // The device picked above the editor's preview. The preview column is narrower than a desktop screen, so by its
  // width alone a carousel there always showed the phone's single slide.
  function frameDevice(root) {
    var frame = root.closest && root.closest('.sgg-sle-frame'),
      device = frame ? frame.getAttribute('data-device') : '';
    return Object.prototype.hasOwnProperty.call(RANK, device) ? device : '';
  }

  function deviceOf(root, breakpoints) {
    var width = root.clientWidth;
    if (inFrame(root)) {
      return frameDevice(root) || deviceFor(width, breakpoints);
    }
    var screen = deviceFor(window.innerWidth || document.documentElement.clientWidth, breakpoints),
      room = width < 480 ? 'm' : width < 640 ? 't' : 'd';
    return RANK[room] < RANK[screen] ? room : screen;
  }

  /**
   * @param {string|null} device The device (on the page, or picked in the editor); null lets Swiper follow the width
   */
  function swiperOptions(root, config, slideCount, device) {
    var responsive = config.responsive,
      motion = config.motion,
      nav = config.nav,
      bp = config.breakpoints,
      single = ONE_SLIDE_EFFECTS.indexOf(motion.effect) !== -1;

    function perView(device) {
      return single ? 1 : Math.max(1, parseInt(responsive.perView[device], 10) || 1);
    }

    var most = Math.max(perView('d'), perView('t'), perView('m'));
    // Swiper's loop needs more slides than fit on the screen; with fewer slides it goes back to the first instead
    var loop = !!motion.loop && slideCount >= most * 2 + 1;
    var options = {
      direction: motion.direction === 'vertical' ? 'vertical' : 'horizontal',
      speed: motion.speed,
      effect: motion.effect,
      loop: loop,
      rewind: !!motion.loop && !loop,
      grabCursor: !!motion.grabCursor,
      centeredSlides: !!motion.centered || motion.effect === 'coverflow',
      freeMode: !!motion.freeMode,
      keyboard: motion.keyboard ? { enabled: true, onlyInViewport: true } : false,
      mousewheel: motion.mousewheel ? { forceToAxis: true } : false,
      slidesPerView: perView('m'),
      spaceBetween: responsive.gap.m,
      breakpointsBase: 'container',
      breakpoints: {},
      watchOverflow: true,
      autoHeight: config.layout.heightMode === 'auto' && most === 1,
      // Layers with an entrance animation and background videos start on every slide that comes into view
      watchSlidesProgress: !!root.querySelector('.sgg-sl-layer[data-anim], .sgg-sl-video.is-bg'),
      a11y: { enabled: true },
      navigation: {
        prevEl: root.querySelector('.sgg-sl-prev'),
        nextEl: root.querySelector('.sgg-sl-next'),
      },
      pagination: {
        el: root.querySelector('.sgg-sl-dots'),
        clickable: true,
        type: nav.dotsType === 'dynamic' ? 'bullets' : nav.dotsType,
        dynamicBullets: nav.dotsType === 'dynamic',
        // A vertical slider keeps the progress bar horizontal, the way the style draws it
        progressbarOpposite: motion.direction === 'vertical',
      },
    };
    if (device) {
      options.slidesPerView = perView(device);
      options.spaceBetween = responsive.gap[device];
    } else {
      options.breakpoints[bp.t] = { slidesPerView: perView('t'), spaceBetween: responsive.gap.t };
      options.breakpoints[bp.d] = { slidesPerView: perView('d'), spaceBetween: responsive.gap.d };
    }

    if (motion.autoplay && !reducedMotion) {
      options.autoplay = { delay: motion.delay, pauseOnMouseEnter: !!motion.pauseOnHover, disableOnInteraction: false };
    }
    // Running line: an endless autoplay without stops at an even speed (the CSS makes the motion linear)
    if (motion.marquee && config.type === 'carousel') {
      options.loop = true;
      options.rewind = false;
      options.freeMode = false;
      options.speed = motion.marqueeSpeed;
      options.autoplay = reducedMotion ? false : { delay: 0, disableOnInteraction: false, pauseOnMouseEnter: false, reverseDirection: !!motion.marqueeReverse };
    }
    if (motion.effect === 'fade') {
      options.fadeEffect = { crossFade: true };
    } else if (motion.effect === 'coverflow') {
      options.coverflowEffect = { rotate: 28, stretch: 0, depth: 140, modifier: 1, slideShadows: false };
    } else if (motion.effect === 'cube') {
      options.cubeEffect = { shadow: true, slideShadows: true, shadowOffset: 20, shadowScale: 0.94 };
    } else if (motion.effect === 'cards') {
      options.cardsEffect = { slideShadows: false };
    } else if (motion.effect === 'creative') {
      options.creativeEffect = { prev: { shadow: true, translate: ['-20%', 0, -1] }, next: { translate: ['100%', 0, 0] } };
    }

    return options;
  }

  // ---------- Video slides (PRO): a player only on the slide that is on screen
  // Only the kinds the plugin writes (youtube, vimeo, file); for anything else there is no player
  function fileSource(url) {
    var parsed;
    try {
      parsed = new URL(String(url || ''), document.baseURI);
    } catch (e) {
      return '';
    }
    return parsed.protocol === 'https:' || parsed.protocol === 'http:' ? parsed.href : '';
  }

  function videoSource(video, background) {
    if (video.kind === 'file') {
      return fileSource(video.src);
    }
    if (video.kind === 'youtube') {
      return 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(video.id) + '?' +
        (background
          ? 'autoplay=1&mute=1&loop=1&playlist=' + encodeURIComponent(video.id) + '&controls=0&disablekb=1&playsinline=1&rel=0&modestbranding=1'
          : 'autoplay=1&playsinline=1&rel=0&modestbranding=1');
    }
    if (video.kind === 'vimeo') {
      return 'https://player.vimeo.com/video/' + encodeURIComponent(video.id) + '?' +
        (background ? 'autoplay=1&muted=1&loop=1&controls=0&title=0&byline=0&portrait=0&autopause=0&dnt=1' : 'autoplay=1&dnt=1');
    }
    return '';
  }

  function startVideo(box) {
    if (box.firstChild) {
      return;
    }
    var video;
    try {
      video = JSON.parse(box.getAttribute('data-sgg-video'));
    } catch (e) {
      return;
    }
    var background = box.classList.contains('is-bg'),
      frame = box.closest('.sgg-sl-frame'),
      source = video && frame ? videoSource(video, background) : '',
      player;
    if (!source) {
      return;
    }
    if (video.kind === 'file') {
      player = document.createElement('video');
      player.src = source;
      player.autoplay = true;
      player.playsInline = true;
      player.setAttribute('playsinline', '');
      if (background) {
        player.muted = true;
        player.loop = true;
      } else {
        player.controls = true;
      }
    } else {
      player = document.createElement('iframe');
      player.src = source;
      player.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
      player.allowFullscreen = true;
      player.title = box.getAttribute('data-sgg-video-title') || 'Video';
      if (background) {
        player.tabIndex = -1;
      }
    }
    if (background) {
      player.style.opacity = '0';
      player.addEventListener(video.kind === 'file' ? 'playing' : 'load', function () {
        player.style.opacity = '1';
      });
    }
    box.appendChild(player);
    frame.classList.add('is-playing');
  }

  function stopVideo(box) {
    var frame = box.closest('.sgg-sl-frame');
    if (box.firstChild && frame) {
      box.textContent = '';
      frame.classList.remove('is-playing');
    }
  }

  // Background videos play on the slides in view; any other video stops when its slide leaves
  function syncVideos(root, swiper) {
    Array.prototype.forEach.call(root.querySelectorAll('[data-sgg-video]'), function (box) {
      var slide = box.closest('.swiper-slide'),
        inView = slide && (slide.classList.contains('swiper-slide-active') || slide.classList.contains('swiper-slide-visible'));
      if (!inView) {
        stopVideo(box);
      } else if (box.classList.contains('is-bg') && !reducedMotion) {
        startVideo(box);
      }
    });
    return swiper;
  }

  function slideIndex(element) {
    var slide = element.closest('[data-sgg-index]');
    return slide ? parseInt(slide.getAttribute('data-sgg-index'), 10) || 0 : 0;
  }

  /**
   * A running line needs more slides than fit on the screen for an endless loop; with few slides the whole set is
   * repeated. The copies are marked and hidden from screen readers.
   */
  function fillMarquee(container, config) {
    var wrapper = container.querySelector('.swiper-wrapper'),
      originals = Array.prototype.slice.call(wrapper.children),
      most = Math.max(1, parseInt(config.responsive.perView.d, 10) || 1, parseInt(config.responsive.perView.t, 10) || 1),
      count = originals.length;
    if (!count) {
      return;
    }
    while (count < most * 2 + 2 && count < 60) {
      originals.forEach(function (slide) {
        var copy = slide.cloneNode(true);
        copy.setAttribute('data-sgg-clone', '');
        copy.setAttribute('aria-hidden', 'true');
        Array.prototype.forEach.call(copy.querySelectorAll('a, button'), function (link) {
          link.setAttribute('tabindex', '-1');
        });
        wrapper.appendChild(copy);
      });
      count += originals.length;
    }
  }

  /**
   * Pause of a running line under the mouse: it stops where it is, not at the end of the slide.
   */
  function marqueePause(root, swiper) {
    var wrapper = swiper.wrapperEl;
    root._sggEnter = function () {
      if (!swiper.autoplay || swiper.destroyed) {
        return;
      }
      var matrix = new DOMMatrixReadOnly(window.getComputedStyle(wrapper).transform);
      swiper.autoplay.stop();
      swiper.setTransition(0);
      swiper.setTranslate(matrix.m41);
      // The move was cut short, so it never ends by itself; a loop does not slide while a move is running
      swiper.animating = false;
    };
    root._sggLeave = function () {
      if (swiper.autoplay && !swiper.destroyed) {
        swiper.autoplay.start();
      }
    };
    root.addEventListener('mouseenter', root._sggEnter);
    root.addEventListener('mouseleave', root._sggLeave);
  }

  /**
   * The lightbox over the slides: the one of the plugin with its themes (Lightbox module), or PhotoSwipe as it is
   * when that script is not on the page. The list is in the original order (loop mode moves the slides around), and
   * closing the lightbox brings the slider to the photo seen last.
   */
  function setupLightbox(root, swiper, config) {
    if (typeof window.PhotoSwipe !== 'function') {
      return null;
    }
    var links = Array.prototype.filter.call(root.querySelectorAll('[data-sgg-pswp]'), function (link) {
      return !link.closest('[data-sgg-clone]');
    });
    // Loop mode moves the slides around, so the list follows the index of each slide, not the page order
    links.sort(function (a, b) {
      return slideIndex(a) - slideIndex(b);
    });
    if (links.length && window.SggLightbox && config && config.lightboxOptions) {
      return themedLightbox(root, links, config.lightboxOptions);
    }
    if (typeof window.PhotoSwipeLightbox !== 'function') {
      return null;
    }
    var items = links.map(function (link) {
      var image = link.querySelector('img');
      return {
        src: link.getAttribute('href'),
        width: parseInt(link.getAttribute('data-pswp-width'), 10) || 1600,
        height: parseInt(link.getAttribute('data-pswp-height'), 10) || 1067,
        alt: image ? image.alt : '',
        msrc: image ? image.currentSrc || image.src : '',
      };
    });
    if (!items.length) {
      return null;
    }
    var lightbox = new window.PhotoSwipeLightbox({
      dataSource: items,
      pswpModule: window.PhotoSwipe,
      bgOpacity: 0.92,
      showHideAnimationType: reducedMotion ? 'none' : 'fade',
      wheelToZoom: true,
    });
    lightbox.on('close', function () {
      if (lightbox.pswp && swiper && !swiper.destroyed) {
        swiper.slideToLoop(lightbox.pswp.currIndex, 0);
      }
    });
    lightbox.init();

    root._sggClick = function (event) {
      var link = event.target.closest('[data-sgg-pswp]');
      if (!link || !root.contains(link)) {
        return;
      }
      event.preventDefault();
      var slide = link.closest('[data-sgg-index]');
      lightbox.loadAndOpen(slide ? parseInt(slide.getAttribute('data-sgg-index'), 10) || 0 : 0);
    };
    root.addEventListener('click', root._sggClick);

    return lightbox;
  }

  function themedLightbox(root, links, options) {
    var items = links.map(function (link) {
      var image = link.querySelector('img');
      return {
        src: link.getAttribute('href'),
        width: parseInt(link.getAttribute('data-pswp-width'), 10) || 1600,
        height: parseInt(link.getAttribute('data-pswp-height'), 10) || 1067,
        alt: image ? image.alt : '',
        msrc: image ? image.currentSrc || image.src : '',
        element: link,
        title: link.getAttribute('data-sgg-title') || '',
        text: link.getAttribute('data-sgg-text') || '',
      };
    });
    root._sggClick = function (event) {
      var link = event.target.closest('[data-sgg-pswp]');
      if (!link || !root.contains(link)) {
        return;
      }
      event.preventDefault();
      var slide = link.closest('[data-sgg-index]');
      window.SggLightbox.open(items, slide ? parseInt(slide.getAttribute('data-sgg-index'), 10) || 0 : 0, options, {
        // The slider may have started again while the lightbox was open (full screen changes the device)
        onClose: function (index) {
          var current = root._sggSwiper;
          if (current && !current.destroyed) {
            current.slideToLoop(index, 0);
          }
        },
      });
    };
    root.addEventListener('click', root._sggClick);

    // An open lightbox stays open when the slider starts again
    return { destroy: function () {} };
  }

  function unmount(root) {
    if (root._sggInput) {
      root.removeEventListener('input', root._sggInput);
      root.removeEventListener('click', root._sggPlay);
      root._sggInput = root._sggPlay = null;
    }
    Array.prototype.forEach.call(root.querySelectorAll('[data-sgg-video]'), stopVideo);
    if (root._sggEnter) {
      root.removeEventListener('mouseenter', root._sggEnter);
      root.removeEventListener('mouseleave', root._sggLeave);
      root._sggEnter = root._sggLeave = null;
    }
    if (root._sggClick) {
      root.removeEventListener('click', root._sggClick);
      root._sggClick = null;
    }
    if (root._sggLightbox) {
      root._sggLightbox.destroy();
      root._sggLightbox = null;
    }
    if (root._sggSwiper) {
      root._sggSwiper.destroy(true, true);
      root._sggSwiper = null;
    }
    if (root._sggThumbs) {
      root._sggThumbs.destroy(true, true);
      root._sggThumbs = null;
    }
    if (root._sggResize) {
      root._sggResize.disconnect();
      root._sggResize = null;
    }
    if (root._sggVisible) {
      root._sggVisible.disconnect();
      root._sggVisible = null;
    }
    if (root._sggOnResize) {
      window.removeEventListener('resize', root._sggOnResize);
      root._sggOnResize = null;
    }
    clearTimeout(root._sggRemount);
  }

  function mount(root, config) {
    if (!root || typeof window.Swiper !== 'function') {
      return null;
    }
    unmount(root);
    config = config || readConfig(root);
    var container = root.querySelector('.sgg-sl-swiper');
    if (!config.responsive || !container) {
      return null;
    }
    var marquee = !!config.motion.marquee && config.type === 'carousel';
    if (marquee && !container.querySelector('[data-sgg-clone]')) {
      fillMarquee(container, config);
    }
    var slideCount = container.querySelectorAll('.swiper-slide').length;

    // The device: the screen on the page, the frame in the editor. On the page the slides in a row follow the device,
    // so when it changes the slider starts again for it.
    var framed = inFrame(root);
    root._sggDevice = deviceOf(root, config.breakpoints);
    root.setAttribute('data-device', root._sggDevice);
    function setDevice() {
      var device = deviceOf(root, config.breakpoints);
      if (device === root._sggDevice) {
        return;
      }
      root._sggDevice = device;
      root.setAttribute('data-device', device);
      if (!framed) {
        clearTimeout(root._sggRemount);
        root._sggRemount = setTimeout(function () {
          mount(root, config);
        }, 150);
      }
    }
    if (window.ResizeObserver) {
      root._sggResize = new ResizeObserver(setDevice);
      root._sggResize.observe(root);
    }
    if (!framed) {
      root._sggOnResize = setDevice;
      window.addEventListener('resize', setDevice);
    }

    var lightboxItems = config.lightbox;
    var options = swiperOptions(root, config, slideCount, root._sggDevice);

    // Thumbnails of the Slider: a second strip that follows the slider and moves it on click
    var thumbsEl = root.querySelector('.sgg-sl-thumbs');
    if (thumbsEl) {
      root._sggThumbs = new window.Swiper(thumbsEl, {
        slidesPerView: 'auto',
        spaceBetween: parseInt(config.nav.thumbGap, 10) || 0,
        freeMode: true,
        watchSlidesProgress: true,
        a11y: { enabled: false },
      });
      options.thumbs = { swiper: root._sggThumbs, autoScrollOffset: 1 };
    }

    var swiper = new window.Swiper(container, options);
    root._sggSwiper = swiper;
    root.classList.add('is-ready');

    if (lightboxItems) {
      root._sggLightbox = setupLightbox(root, swiper, config);
    }
    if (marquee && swiper.autoplay && !reducedMotion) {
      marqueePause(root, swiper);
    }

    // Before / After: the line follows the hidden range control (pointer, touch and keyboard)
    root._sggInput = function (event) {
      var range = event.target.closest && event.target.closest('[data-sgg-compare]');
      if (range) {
        range.parentNode.style.setProperty('--sgg-cmp', range.value + '%');
      }
    };
    root.addEventListener('input', root._sggInput);

    // Video slides: the play button starts the player and stops the autoplay of the slider
    root._sggPlay = function (event) {
      var button = event.target.closest('[data-sgg-play]');
      if (!button || !root.contains(button)) {
        return;
      }
      event.preventDefault();
      var box = button.parentNode.querySelector('[data-sgg-video]');
      if (box) {
        startVideo(box);
        if (swiper.autoplay && swiper.autoplay.running) {
          swiper.autoplay.stop();
        }
      }
    };
    root.addEventListener('click', root._sggPlay);
    if (root.querySelector('[data-sgg-video]')) {
      swiper.on('slideChangeTransitionEnd', function () {
        syncVideos(root, swiper);
      });
      syncVideos(root, swiper);
    }

    // Autoplay only while the slider is on screen
    if (swiper.autoplay && config.motion.autoplay && !reducedMotion && window.IntersectionObserver) {
      root._sggVisible = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!swiper.autoplay || swiper.destroyed) {
            return;
          }
          if (entry.isIntersecting) {
            swiper.autoplay.resume ? swiper.autoplay.resume() : swiper.autoplay.start();
          } else {
            swiper.autoplay.pause ? swiper.autoplay.pause() : swiper.autoplay.stop();
          }
        });
      });
      root._sggVisible.observe(root);
    }

    return swiper;
  }

  /**
   * Only the sliders the plugin printed are started, never markup written by hand in a post. Their root has the
   * attribute "sgg-rendered", which WordPress never keeps in the content of users without unfiltered_html, or a key
   * of this request that the server listed in the page (for themes that run wp_kses over the finished output).
   * A page cached before this version has no list and works as before.
   */
  function isPrinted(root) {
    var keys = window.sggSliderKeys;
    return !keys || root.hasAttribute('sgg-rendered') || keys[root.getAttribute('data-sgg-key')] === 1;
  }

  function mountAll(scope) {
    Array.prototype.forEach.call((scope || document).querySelectorAll('.sgg-sl[data-sgg-sl]'), function (root) {
      if (!root._sggSwiper && isPrinted(root)) {
        mount(root);
      }
    });
  }

  window.SggSliders = { mount: mount, unmount: unmount, mountAll: mountAll };

  function watchNewSliders() {
    if (!window.MutationObserver || !document.body) {
      return;
    }
    var pending = false;
    new MutationObserver(function (records) {
      if (pending) {
        return;
      }
      for (var i = 0; i < records.length; i++) {
        for (var j = 0; j < records[i].addedNodes.length; j++) {
          var node = records[i].addedNodes[j];
          if (node.nodeType === 1 && (node.matches('.sgg-sl[data-sgg-sl]') || node.querySelector('.sgg-sl[data-sgg-sl]'))) {
            pending = true;
            // Once per batch of changes, after the new markup is in place
            window.requestAnimationFrame(function () {
              pending = false;
              mountAll();
            });
            return;
          }
        }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      mountAll();
      watchNewSliders();
    });
  } else {
    mountAll();
    watchNewSliders();
  }
})();
