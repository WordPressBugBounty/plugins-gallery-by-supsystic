/**
 * The step-by-step tutorial: wp-pointer bubbles on the real screens, in the order the plugin is really used.
 * The creation wizard comes first (one step per wizard pane, following the pane on screen), then the editor of what
 * was created: a carousel or slider ("editor" page) or a classic gallery ("settings" and "images" pages).
 * Steps come from GridGallery_Promo_Module::pointers(); the current step id is kept in sessionStorage, so the
 * tutorial goes on after the wizard creates the gallery and after page loads.
 */
(function ($, data) {
  'use strict';

  var STORE = 'sgg-tutorial-step';
  var steps = data.pointersData || [];

  function store(key, value) {
    try {
      if (value === null) {
        window.sessionStorage.removeItem(key);
      } else {
        window.sessionStorage.setItem(key, value);
      }
    } catch (e) {}
  }

  function stored(key) {
    try {
      return window.sessionStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function indexOfId(id) {
    for (var i = 0; i < steps.length; i++) {
      if (steps[i].id === id) {
        return i;
      }
    }
    return -1;
  }

  // ---------- Where we are
  function currentPage() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('page') !== 'supsystic-gallery' || params.get('module') !== 'galleries') {
      return '';
    }
    var action = params.get('action');
    if (action === 'showPresets') {
      return document.querySelector('[data-sgg-wz]') ? 'wizard' : '';
    }
    if (action === 'view') {
      return 'images';
    }
    if (action === 'settings') {
      return document.querySelector('.sgg-sle') ? 'editor' : 'settings';
    }
    return '';
  }

  function visiblePane() {
    var pane = $('[data-sgg-wz] [data-wz-step]').filter(function () {
      return !this.hidden;
    });
    return pane.length ? pane.attr('data-wz-step') : '';
  }

  function galleryUrl(action) {
    var id = new URLSearchParams(window.location.search).get('gallery_id');
    return id ? window.location.pathname + '?page=supsystic-gallery&module=galleries&action=' + action + '&gallery_id=' + id : '';
  }

  // The step to show on this page: on the wizard the one of the visible pane, elsewhere the stored step when it
  // belongs here, or else the first step of this page after it.
  function stepForPage(page) {
    if (page === 'wizard') {
      var pane = visiblePane();
      for (var w = 0; w < steps.length; w++) {
        if (steps[w].page === 'wizard' && steps[w].pane === pane) {
          return w;
        }
      }
      return -1;
    }
    var from = Math.max(0, indexOfId(stored(STORE)));
    if (steps[from] && steps[from].page === page) {
      return from;
    }
    for (var i = from; i < steps.length; i++) {
      if (steps[i].page === page) {
        return i;
      }
    }
    for (var j = 0; j < from; j++) {
      if (steps[j].page === page) {
        return j;
      }
    }
    return -1;
  }

  function wizardGoals() {
    try {
      return JSON.parse($('[data-sgg-wz]').attr('data-config') || '{}').goals || {};
    } catch (e) {
      return {};
    }
  }

  // The goal picked in the wizard (Image carousel until one is picked); kept for the pages after the wizard
  function chosenGoal() {
    var $current = $('[data-sgg-wz] .sgg-wz-goal.is-current');
    if ($current.length) {
      var id = $current.attr('data-wz-goal'),
        goal = wizardGoals()[id] || {};
      store('sgg-tutorial-goal', id);
      store('sgg-tutorial-no-images', goal.noImages ? '1' : null);
      return id;
    }
    return currentPage() === 'wizard' ? 'carousel' : stored('sgg-tutorial-goal') || '';
  }

  function branchOf(step) {
    if (step.page === 'settings' || step.page === 'images') {
      return 'classic';
    }
    return step.page === 'editor' ? 'editor' : 'all';
  }

  // The steps of this run, for the "3 / 15" label: the wizard and the branch of what is made, without the steps
  // that will be skipped (no images step for posts and products; the row card only for carousels, the layers card
  // only for sliders with images, no captions card for products)
  function path(page) {
    var goalId = chosenGoal(),
      goal = wizardGoals()[goalId] || {},
      kind = page === 'wizard' ? goal.kind || 'carousel' : '',
      noImages = page === 'wizard' ? !!goal.noImages : stored('sgg-tutorial-no-images') === '1',
      branch = page === 'settings' || page === 'images' || kind === 'gallery' ? 'classic' : 'editor';
    return steps.filter(function (step) {
      var stepBranch = branchOf(step);
      if (stepBranch !== 'all' && stepBranch !== branch) {
        return false;
      }
      if (step.pane === 'images' && noImages) {
        return false;
      }
      if (!step.optional) {
        return true;
      }
      if (step.page === page) {
        return page === 'editor' ? $(step.target).filter(':visible').length > 0 : $(step.target).length > 0;
      }
      if (page === 'wizard') {
        if (step.id === 'ed-row') {
          return kind === 'carousel';
        }
        if (step.id === 'ed-layers') {
          return kind === 'slider' && !noImages;
        }
        if (step.id === 'ed-captions') {
          return goalId !== 'products';
        }
      }
      return true;
    });
  }

  // ---------- The tutorial
  var tour = {
    index: -1,
    page: '',
    $holder: null,
    retries: 0,
    watching: null,

    start: function () {
      this.page = currentPage();
      if (!this.page || !steps.length) {
        return;
      }
      if (data.resetStep) {
        store(STORE, steps[0].id);
        store('sgg-tutorial-goal', null);
        store('sgg-tutorial-no-images', null);
      }
      store('sgg-tutorial-after-import', null);
      this.injectStyles();
      if (this.page === 'wizard') {
        this.followWizard();
      }
      // A moment for the page scripts (the settings groups restore the last open group on load)
      var self = this;
      window.setTimeout(function () {
        self.show(stepForPage(self.page));
      }, 300);
    },

    show: function (index) {
      this.destroy();
      var step = steps[index];
      if (!step || step.page !== this.page) {
        return;
      }
      this.index = index;
      store(STORE, step.id);
      this.prepare(step);

      var $target = $(step.target).filter(':visible').first();
      if (!$target.length && step.fallbackTarget) {
        $target = $(step.fallbackTarget).filter(':visible').first();
      }
      if (!$target.length) {
        if (step.optional) {
          this.forward(step);
          return;
        }
        // The screen may still be drawing: try again for a moment, then go on without this step
        var self = this;
        if (++this.retries <= 12) {
          window.setTimeout(function () {
            self.show(index);
          }, 200);
        } else {
          this.retries = 0;
          this.forward(step);
        }
        return;
      }
      this.retries = 0;
      this.open(step, $target);
    },

    open: function (step, $source) {
      var self = this,
        list = path(this.page),
        number = list.indexOf(step) + 1,
        $target = step.anchor ? this.anchor($source) : $source;

      this.scrollTo($source).done(function () {
        if (step.anchor) {
          self.placeAnchor($target, $source);
        }
        $target.pointer({
          pointerClass: 'sgg-tutorial-pointer sgg-tutorial-' + step.id,
          content:
            '<button type="button" class="sgg-tutorial-x-close" aria-label="' + data.closeIcon + '" title="' + data.closeIcon + '">&times;</button>' +
            '<div class="sgg-tutorial-progress-label">' + number + ' / ' + list.length + '</div>' +
            step.title +
            step.content,
          buttons: function () {
            var $buttons = $('<span class="sgg-tutorial-buttons-inner"></span>'),
              $close = $('<button type="button" class="button button-secondary stop-tutorial"></button>').html(step.end ? data.finish : data.close);
            $close.on('click', function (event) {
              event.preventDefault();
              self.finish();
            });
            $buttons.append($close);
            if (!step.end) {
              var $next = $('<button type="button" class="button button-primary next"></button>').html(step.nextLabel || data.next);
              $next.on('click', function (event) {
                event.preventDefault();
                self.next(step);
              });
              $buttons.append($next);
            }
            return $buttons;
          },
          position: { edge: step.edge || 'top', align: step.align || 'left' },
          close: function () {},
        });
        $target.pointer('open');
        self.$holder = $target;
        var $widget = $target.pointer('widget');
        $widget.find('.sgg-tutorial-x-close').on('click', function () {
          self.finish();
        });
        self.arrow($widget, $source);
      });
    },

    // What Next does: on the wizard it moves the wizard itself (the pane change then shows the next step)
    next: function (step) {
      var $wz = $('[data-sgg-wz]');
      if (step.id === 'wz-goal') {
        if (!visiblePane() || visiblePane() === 'goal') {
          $wz.find('[data-wz-goal="carousel"]').trigger('click');
        }
        return;
      }
      if (step.id === 'wz-images') {
        if (!$wz.find('[data-wz-picked] [data-wz-index]').length && !this.askedForImages) {
          this.askedForImages = true;
          this.chooseImages();
          return;
        }
        $wz.find('[data-wz-next]').trigger('click');
        return;
      }
      if (step.id === 'wz-style' || step.id === 'wz-extras') {
        $wz.find('[data-wz-next]').trigger('click');
        return;
      }
      if (step.id === 'wz-name') {
        var $title = $wz.find('[data-wz-title]');
        if (!$.trim($title.val())) {
          $title.val(data.autoGalleryTitle || 'Step by step gallery');
        }
        // After the wizard the first step of the editor or of the gallery settings follows
        store(STORE, step.id);
        this.destroy();
        $wz.find('[data-wz-create]').trigger('click');
        return;
      }
      this.forward(step);
    },

    forward: function (step) {
      var index = steps.indexOf(step) + 1;
      if (step.end || !steps[index]) {
        this.finish();
        return;
      }
      if (step.next === 'images' || steps[index].page !== this.page) {
        var url = galleryUrl(step.next === 'images' ? 'view' : 'settings');
        var target = steps[index].page;
        // Leave this page only for the page the next step lives on
        if (url && (target === 'images' || target === 'settings')) {
          store(STORE, steps[index].id);
          window.location.href = url;
          return;
        }
        this.finish();
        return;
      }
      this.show(index);
    },

    // The Media Library of the wizard; the step comes back when the window closes
    chooseImages: function () {
      var self = this;
      this.destroy();
      $('[data-sgg-wz] [data-wz-media]').trigger('click');
      var timer = window.setInterval(function () {
        if (!$('.media-modal:visible').length) {
          window.clearInterval(timer);
          self.show(stepForPage('wizard'));
        }
      }, 400);
    },

    // The wizard changes panes by itself (a click on a goal, Next, Back): the bubble follows
    followWizard: function () {
      var self = this,
        last = visiblePane(),
        root = document.querySelector('[data-sgg-wz]');
      if (!root || !window.MutationObserver) {
        return;
      }
      this.watching = new MutationObserver(function () {
        var pane = visiblePane();
        if (pane && pane !== last) {
          last = pane;
          window.setTimeout(function () {
            self.show(stepForPage('wizard'));
          }, 450);
        }
      });
      this.watching.observe(root, { attributes: true, attributeFilter: ['hidden'], subtree: true });
    },

    prepare: function (step) {
      if (step.prepare === 'settings' || step.prepare === 'top') {
        var $tab = $('.change-tab[href="settings"]');
        if ($tab.length && !$tab.hasClass('nav-tab-active')) {
          $tab.trigger('click');
        }
        $('[data-tab="settings"]').show();
        $('.gg-section-collapsed').removeClass('gg-section-collapsed');
        $('.gg-section-collapsed-extra').removeClass('gg-section-collapsed-extra');
        $('.settings-wrap table.form-table tbody').show();
      }
      // The settings of a classic gallery are split into groups (tabs): open the one that holds the target of the
      // step (in the free version some PRO rows sit under More), or else the group the step names
      var group = $(step.target).first().closest('.sgg-section').attr('data-sgg-group') ||
        $(step.fallbackTarget || '').first().closest('.sgg-section').attr('data-sgg-group') ||
        step.group;
      if (group) {
        var $group = $('.sgg-settings-group[data-sgg-group="' + group + '"]');
        if ($group.length && !$group.hasClass('is-active')) {
          $group.trigger('click');
        }
      }
      if (step.prepare === 'top') {
        $('.settings-wrap').scrollTop(0);
      }
    },

    destroy: function () {
      if (this.$holder && this.$holder.data('wp-pointer')) {
        try {
          this.$holder.pointer('destroy');
        } catch (e) {
          this.$holder.removeData('wp-pointer');
        }
      }
      this.$holder = null;
      $('.sgg-tutorial-pointer').stop(true, true).remove();
    },

    finish: function () {
      this.destroy();
      if (this.watching) {
        this.watching.disconnect();
        this.watching = null;
      }
      $('#sgg-tutorial-anchor').remove();
      store(STORE, null);
      store('sgg-tutorial-goal', null);
      store('sgg-tutorial-no-images', null);
      $.post(ajaxurl, { _wpnonce: window.SupsysticGallery ? SupsysticGallery.nonce : '', action: 'sgg-tutorial-close' });
    },

    // ---------- Placement
    // Rows of the classic settings are wide: the bubble points at a spot just right of the row's label
    anchor: function () {
      var $anchor = $('#sgg-tutorial-anchor');
      if (!$anchor.length) {
        $anchor = $('<span id="sgg-tutorial-anchor" aria-hidden="true"></span>').appendTo('body');
      }
      return $anchor;
    },

    placeAnchor: function ($anchor, $source) {
      var $label = $source.is('tr') ? $source.children('th:visible').first() : $source.closest('tr').children('th:visible').first();
      if (!$label.length) {
        $label = $source;
      }
      var rect = $label[0].getBoundingClientRect(),
        left = window.pageXOffset + rect.left + $label.outerWidth() + 170,
        top = window.pageYOffset + rect.top + Math.min(Math.max(rect.height / 2, 18), 42);
      left = Math.max(window.pageXOffset + 40, Math.min(left, window.pageXOffset + $(window).width() - 420));
      $anchor.css({ position: 'absolute', display: 'block', left: Math.round(left), top: Math.round(top), width: 1, height: 1, pointerEvents: 'none', zIndex: 10000 });
    },

    scrollTo: function ($target) {
      var deferred = $.Deferred(),
        $wrap = $target.closest('.settings-wrap');
      if ($wrap.length && $wrap[0].scrollHeight > $wrap.innerHeight() + 20) {
        $wrap.scrollTop(Math.max(0, $wrap.scrollTop() + $target.offset().top - $wrap.offset().top - 80));
      }
      var top = Math.max(0, $target.offset().top - 170);
      $('html, body')
        .stop(true)
        .animate({ scrollTop: top }, 260)
        .promise()
        .always(function () {
          window.setTimeout(deferred.resolve, 40);
        });
      return deferred.promise();
    },

    arrow: function ($widget, $source) {
      if (!$widget || !$widget.length || !$source || !$source.length) {
        return;
      }
      var widget = $widget[0].getBoundingClientRect(),
        target = $source[0].getBoundingClientRect();
      if (!widget.width || !target.width) {
        return;
      }
      var left = Math.max(24, Math.min(target.left + target.width / 2 - widget.left, widget.width - 24));
      $widget.css('--sgg-pointer-arrow-left', Math.round(left) + 'px');
    },

    injectStyles: function () {
      if ($('#sgg-tutorial-pointer-style').length) {
        return;
      }
      $('head').append(
        '<style id="sgg-tutorial-pointer-style">' +
          '.sgg-tutorial-pointer.wp-pointer-top,.sgg-tutorial-pointer.wp-pointer-bottom,.sgg-tutorial-pointer.wp-pointer-left,.sgg-tutorial-pointer.wp-pointer-right,.sgg-tutorial-pointer.wp-pointer-undefined{padding:0!important;}' +
          '.sgg-tutorial-pointer{border-radius:8px;box-shadow:0 18px 46px rgba(21,32,51,.22);z-index:100000!important;}' +
          '.sgg-tutorial-pointer:after{content:"";position:absolute;width:14px;height:14px;background:#fff;border:1px solid #c8d2dc;transform:rotate(45deg);z-index:1;}' +
          '.sgg-tutorial-pointer.wp-pointer-top:after,.sgg-tutorial-pointer.wp-pointer-undefined:after{top:-8px;left:var(--sgg-pointer-arrow-left,34px);margin-left:-7px;border-right:0;border-bottom:0;}' +
          '.sgg-tutorial-pointer.wp-pointer-bottom:after{bottom:-8px;left:var(--sgg-pointer-arrow-left,34px);margin-left:-7px;border-left:0;border-top:0;}' +
          '.sgg-tutorial-pointer.wp-pointer-left:after{left:-8px;top:34px;border-right:0;border-top:0;}' +
          '.sgg-tutorial-pointer.wp-pointer-right:after{right:-8px;top:34px;border-left:0;border-bottom:0;}' +
          '.sgg-tutorial-pointer .wp-pointer-arrow{display:none!important;}' +
          '.sgg-tutorial-pointer .wp-pointer-content{position:relative;z-index:2;padding:18px 20px 16px;background:#fff;color:#2b3546;}' +
          '.sgg-tutorial-pointer .wp-pointer-content h3{margin:0 30px 10px 0;padding:0;border:0;background:transparent;color:#152033;font-size:18px;font-weight:800;line-height:1.25;}' +
          '.sgg-tutorial-pointer .wp-pointer-content h3:before{display:none;content:none;}' +
          '.sgg-tutorial-pointer .wp-pointer-content p{margin:0 0 10px;color:#405168;font-size:13px;line-height:1.55;}' +
          '.sgg-tutorial-pointer .wp-pointer-content p:last-child{margin-bottom:0;}' +
          '.sgg-tutorial-pointer .wp-pointer-buttons{position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 20px 18px;background:#fff;}' +
          '.sgg-tutorial-pointer .sgg-tutorial-buttons-inner{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;}' +
          '.sgg-tutorial-pointer .wp-pointer-buttons .button{display:inline-flex;align-items:center;justify-content:center;min-height:34px;margin:0;border-radius:6px;font-weight:700;line-height:1.2;text-align:center;}' +
          '.sgg-tutorial-pointer .wp-pointer-buttons .button.next{margin-left:auto;}' +
          '.sgg-tutorial-pointer .wp-pointer-buttons .button-primary{background:#152033;border-color:#152033;color:#fff;}' +
          '.sgg-tutorial-progress-label{display:inline-flex;margin:0 0 10px;padding:3px 8px;border-radius:999px;background:#e9fbfb;color:#12777d;font-size:11px;font-weight:800;}' +
          '.sgg-tutorial-x-close{position:absolute;top:10px;right:10px;width:28px;height:28px;border:0;border-radius:50%;background:#f2f5f8;color:#536276;cursor:pointer;font-size:18px;line-height:1;}' +
          '.sgg-tutorial-x-close:hover{background:#e4ebf1;color:#152033;}' +
          '</style>'
      );
    },
  };

  $(function () {
    tour.start();
  });
})(jQuery, window.GalleryPromoPointers || {});
