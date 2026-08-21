(function ($, pointers) {
  'use strict';

  function resolveNextUrl(nextURL) {
    if (nextURL !== '@images-list' && nextURL !== '@gallery-settings') {
      return nextURL;
    }

    var galleryId = new URLSearchParams(window.location.search).get('gallery_id');
    if (!galleryId) {
      return false;
    }

    var action = nextURL === '@images-list' ? 'view' : 'settings';
    return ajaxurl.replace('admin-ajax.php', 'admin.php') + '?page=supsystic-gallery&module=galleries&action=' + action + '&gallery_id=' + galleryId;
  }

  function isStorageAvailable() {
    return typeof window.sessionStorage !== 'undefined';
  }

  function isUsableTarget($target) {
    return $target.length && ($target.is(':visible') || $target.is('body, html') || $target.css('position') === 'fixed');
  }

  pointers.renderContent = function (pointerData) {
    var total = this.pointersData.length;
    var current = this.stepNumber + 1;

    return (
      '<button type="button" class="sgg-tutorial-x-close" aria-label="' + this.closeIcon + '" title="' + this.closeIcon + '">&times;</button>' +
      '<div class="sgg-tutorial-progress-label">' + current + ' / ' + total + '</div>' +
      pointerData.title +
      pointerData.content
    );
  };

  pointers.getTarget = function (pointerData) {
    var beforeTarget = this.beforeTarget[pointerData.id];
    if (typeof beforeTarget === 'function') {
      beforeTarget.call(this, pointerData);
    }

    var $target = $(pointerData.target);
    if (!isUsableTarget($target) && pointerData.fallbackTarget) {
      $target = $(pointerData.fallbackTarget);
    }

    this.currentScrollTarget = $target.first();
    this.currentTarget = this.getPointerTarget($target.first(), pointerData);

    return this.currentTarget;
  };

  pointers.advance = function (pointerData) {
    this.stepNumber += 1;
    sessionStorage.setItem('sgg-tutorial-step', this.stepNumber);

    var nextURL = resolveNextUrl(pointerData.nextURL);
    if (nextURL && nextURL !== '#' && window.location.href !== nextURL) {
      if (pointerData.nextURL === '@gallery-settings') {
        sessionStorage.setItem('sgg-tutorial-pending-page', 'settings');
      }

      window.location = nextURL;
      return;
    }

    var self = this;
    window.setTimeout(function () {
      self.setPointer();
    }, 80);
  };

  pointers.finish = function () {
    if (this.current && this.current.data('wp-pointer')) {
      try {
        this.current.pointer('destroy');
      } catch (error) {
        this.current.removeData('wp-pointer');
      }
    }
    $('.sgg-tutorial-pointer').stop(true, true).remove();

    $.post(ajaxurl, {
      _wpnonce: SupsysticGallery.nonce,
      action: 'sgg-tutorial-close',
    });
    sessionStorage.removeItem('sgg-tutorial-step');
  };

  pointers.skipMissingPointer = function (pointerData) {
    var self = this;
    this.missingRetries = this.missingRetries || {};
    this.missingRetries[pointerData.id] = (this.missingRetries[pointerData.id] || 0) + 1;

    if (this.missingRetries[pointerData.id] <= 10) {
      window.setTimeout(function () {
        self.setPointer();
      }, 200);
      return;
    }

    delete this.missingRetries[pointerData.id];
    this.missingSkips = (this.missingSkips || 0) + 1;

    if (this.missingSkips > this.pointersData.length) {
      this.finish();
      return;
    }

    this.advance(pointerData);
  };

  pointers.setPointer = function () {
    if (!this.stepNumber) {
      this.stepNumber = 0;
    }
    this.syncStepWithCurrentPage();

    if (!this.getCurrentPage()) {
      return;
    }

    if (this.current && this.current.data('wp-pointer')) {
      try {
        this.current.pointer('destroy');
      } catch (error) {
        this.current.removeData('wp-pointer');
      }
    }
    $('.sgg-tutorial-pointer').stop(true, true).remove();

    var pointerData = this.pointersData[this.stepNumber];
    if (!pointerData) {
      this.finish();
      return;
    }

    var $target = this.getTarget(pointerData);
    if (!isUsableTarget($target)) {
      this.skipMissingPointer(pointerData);
      return;
    }

    this.missingSkips = 0;
    if (this.missingRetries) {
      delete this.missingRetries[pointerData.id];
    }

    var self = this;
    var $pointer = $target.first().pointer({
      pointerClass: (pointerData.class || '') + ' sgg-tutorial-pointer',
      content: this.renderContent(pointerData),
      buttons: function (event, pointerApi) {
        var $closeButton = $('<button type="button" class="button button-secondary stop-tutorial"></button>').html(self.close);
        var $buttons = $('<span class="sgg-tutorial-buttons-inner"></span>').append($closeButton);

        $closeButton.on('click.sggTutorialClose', function (clickEvent) {
          clickEvent.preventDefault();
          clickEvent.stopPropagation();
          clickEvent.stopImmediatePropagation();
          self.hasNextStep = false;
          pointerApi.element.pointer('close');
        });

        if (self.stepNumber < self.pointersData.length - 1) {
          self.hasNextStep = true;
          var $nextButton = $('<button type="button" class="button button-primary next"></button>').html(self.next);
          $nextButton.on('click.sggTutorialNext', function (clickEvent) {
            clickEvent.preventDefault();
            clickEvent.stopPropagation();
            clickEvent.stopImmediatePropagation();
            self.hasNextStep = true;
            pointerApi.element.pointer('close');
          });
          $buttons.append($nextButton);
        }

        return $buttons;
      },
      position: {
        edge: pointerData.edge,
        align: pointerData.align,
      },
      close: function () {
        if (self.hasNextStep) {
          var beforeAdvance = self.beforeAdvance[pointerData.id];
          if (typeof beforeAdvance === 'function' && beforeAdvance.call(self, pointerData) === false) {
            return;
          }
          self.advance(pointerData);
        } else {
          self.finish();
        }
      },
    });

    this.current = $pointer;
    this.openPointer();

    var action = this.actions[pointerData.id];
    if (typeof action === 'function') {
      action.call(this);
    }
  };

  pointers.openPointer = function () {
    var $pointer = this.current;
    var self = this;

    if (!($pointer && $pointer.length)) {
      return;
    }

    this.scrollToTarget(this.currentScrollTarget || $pointer).done(function () {
        self.refreshPointerTarget();
        $pointer.pointer('open');
        var $widget = $pointer.pointer('widget');
        $widget.stop(true, true).show();
        self.positionPointerArrow($widget);
        self.setNext($widget);
      });
  };

  pointers.positionPointerArrow = function ($widget) {
    if (!($widget && $widget.length && this.currentTarget && this.currentTarget.length)) {
      return;
    }

    var widgetNode = $widget[0];
    var targetNode = this.currentTarget[0];
    var widgetRect = widgetNode.getBoundingClientRect();
    var targetRect = targetNode.getBoundingClientRect();

    if (!widgetRect.width || !targetRect.width) {
      return;
    }

    var arrowLeft = targetRect.left + targetRect.width / 2 - widgetRect.left;
    arrowLeft = Math.max(24, Math.min(arrowLeft, widgetRect.width - 24));
    $widget.css('--sgg-pointer-arrow-left', Math.round(arrowLeft) + 'px');
  };

  pointers.getPointerTarget = function ($target, pointerData) {
    if (!this.shouldOffsetSettingsPointer(pointerData)) {
      $('#sgg-tutorial-settings-anchor').remove();
      return $target;
    }

    var $anchor = $('#sgg-tutorial-settings-anchor');
    if (!$anchor.length) {
      $anchor = $('<span id="sgg-tutorial-settings-anchor" aria-hidden="true"></span>').appendTo('body');
    }

    $anchor.data('sggSourceTarget', $target);
    this.positionSettingsAnchor($anchor, $target);

    return $anchor;
  };

  pointers.shouldOffsetSettingsPointer = function (pointerData) {
    return pointerData && ['step-12', 'step-13', 'step-14', 'step-15', 'step-16', 'step-17', 'step-18'].indexOf(pointerData.id) !== -1;
  };

  pointers.refreshPointerTarget = function () {
    if (!(this.currentTarget && this.currentTarget.length && this.currentTarget.attr('id') === 'sgg-tutorial-settings-anchor')) {
      return;
    }

    this.positionSettingsAnchor(this.currentTarget, this.currentTarget.data('sggSourceTarget'));
  };

  pointers.positionSettingsAnchor = function ($anchor, $source) {
    if (!($anchor && $anchor.length && $source && $source.length)) {
      return;
    }

    var $label = this.getSettingsOptionLabel($source);
    var labelNode = $label[0];
    var labelRect = labelNode ? labelNode.getBoundingClientRect() : null;

    if (!labelRect) {
      labelNode = $source[0];
      labelRect = labelNode ? labelNode.getBoundingClientRect() : null;
      $label = $source;
    }

    if (!labelRect) {
      return;
    }

    var arrowInset = 34;
    var desiredArrowLeft = window.pageXOffset + labelRect.left + $label.outerWidth() + 200;
    var maxLeft = window.pageXOffset + $(window).width() - 420;
    var minLeft = window.pageXOffset + 40;
    var anchorLeft = Math.max(minLeft, Math.min(desiredArrowLeft - arrowInset, maxLeft));
    var maxTop = window.pageYOffset + Math.max(120, $(window).height() - 360);
    var minTop = window.pageYOffset + 120;
    var desiredTop = window.pageYOffset + labelRect.top + Math.min(Math.max($label.outerHeight() / 2, 18), 42);
    var anchorTop = Math.max(minTop, Math.min(desiredTop, maxTop));

    $anchor.css({
      display: 'block',
      position: 'absolute',
      left: Math.round(anchorLeft),
      top: Math.round(anchorTop),
      width: 1,
      height: 1,
      opacity: 1,
      background: 'transparent',
      pointerEvents: 'none',
      zIndex: 10000,
    });
  };

  pointers.getSettingsOptionLabel = function ($source) {
    var $label = $source.find('h3:visible').first();

    if (!$label.length && $source.is('tr')) {
      $label = $source.children('th:visible').first();
    }

    if (!$label.length && $source.is('table')) {
      $label = $source.find('tr:visible th:visible').first();
    }

    if (!$label.length) {
      $label = $source.closest('tr').children('th:visible').first();
    }

    if (!$label.length) {
      $label = $source.find('th:visible, td:visible').first();
    }

    return $label.length ? $label : $source;
  };

  pointers.scrollToTarget = function ($target) {
    var deferred = $.Deferred();
    var self = this;

    if (!($target && $target.length)) {
      deferred.resolve();
      return deferred.promise();
    }

    this.scrollSettingsWrapToTarget($target).always(function () {
      $('html, body')
        .stop(true)
        .animate(
          {
            scrollTop: Math.max(0, $target.offset().top - 170),
          },
          260
        )
        .promise()
        .always(function () {
          window.setTimeout(function () {
            self.forceTargetIntoViewport($target);
            deferred.resolve();
          }, 40);
        });
    });

    return deferred.promise();
  };

  pointers.scrollSettingsWrapToTarget = function ($target) {
    var deferred = $.Deferred();
    var $wrap = $target.closest('.settings-wrap');

    if (!$wrap.length || !$wrap[0] || $wrap[0].scrollHeight <= $wrap.innerHeight() + 20) {
      deferred.resolve();
      return deferred.promise();
    }

    $wrap
      .stop(true)
      .animate(
        {
          scrollTop: Math.max(0, $wrap.scrollTop() + $target.offset().top - $wrap.offset().top - 80),
        },
        260
      )
      .promise()
      .always(function () {
        deferred.resolve();
      });

    return deferred.promise();
  };

  pointers.forceTargetIntoViewport = function ($target) {
    if (!($target && $target.length && $target[0])) {
      return;
    }

    var rect = $target[0].getBoundingClientRect();
    var topLimit = 110;
    var bottomLimit = window.innerHeight - 120;

    if (rect.top < topLimit || rect.top > bottomLimit) {
      window.scrollTo(window.pageXOffset, Math.max(0, window.pageYOffset + rect.top - topLimit));
    }
  };

  pointers.setNext = function ($widget) {
    var self = this;
    this.hasNextStep = false;

    if (!$widget || !$widget.length) {
      return;
    }

    var $buttons = $widget.find('.wp-pointer-buttons');
    this.hasNextStep = this.stepNumber < this.pointersData.length - 1;
    $buttons.find('.close').removeClass('close').addClass('button button-secondary stop-tutorial').html(this.close);
    $buttons.find('.next').addClass('button button-primary');

    $widget.find('.sgg-tutorial-x-close').off('click.sggTutorialCloseIcon').on('click.sggTutorialCloseIcon', function () {
      self.hasNextStep = false;
      self.current.pointer('close');
    });
  };

  pointers.injectStyles = function () {
    if ($('#sgg-tutorial-pointer-style').length) {
      return;
    }

    $('head').append(
      '<style id="sgg-tutorial-pointer-style">' +
      '.sgg-tutorial-pointer.wp-pointer-top,.sgg-tutorial-pointer.wp-pointer-bottom,.sgg-tutorial-pointer.wp-pointer-left,.sgg-tutorial-pointer.wp-pointer-right,.sgg-tutorial-pointer.wp-pointer-undefined{padding:0!important;}' +
      '.sgg-tutorial-pointer{border-radius:8px;box-shadow:0 18px 46px rgba(21,32,51,.22);}' +
      '.sgg-tutorial-pointer:after{content:"";position:absolute;width:14px;height:14px;background:#fff;border:1px solid #c8d2dc;transform:rotate(45deg);z-index:1;}' +
      '.sgg-tutorial-pointer.wp-pointer-top:after,.sgg-tutorial-pointer.wp-pointer-undefined:after{top:-8px;left:var(--sgg-pointer-arrow-left,34px);margin-left:-7px;border-right:0;border-bottom:0;}' +
      '.sgg-tutorial-pointer.wp-pointer-bottom:after{bottom:-8px;left:var(--sgg-pointer-arrow-left,34px);margin-left:-7px;border-left:0;border-top:0;}' +
      '.sgg-tutorial-pointer.wp-pointer-left:after{left:-8px;top:34px;border-right:0;border-top:0;}' +
      '.sgg-tutorial-pointer.wp-pointer-right:after{right:-8px;top:34px;border-left:0;border-bottom:0;}' +
      '.sgg-tutorial-pointer .wp-pointer-arrow{display:none!important;}' +
      '#sgg-tutorial-settings-anchor{display:block!important;}' +
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
  };

  pointers.ensureGalleryDraft = function () {
    var $presets = $('.preset:not(.disabled)');
    var $activePreset = $('.preset.active:not(.disabled)');
    var $presetValue = $('#presetValue');
    var $title = $('#gallery-create-title');

    if (!$activePreset.length && $presets.length) {
      $presets.first().trigger('click');
      $activePreset = $presets.first();
    }

    if (!$presetValue.val() && $activePreset.length) {
      $presetValue.val($activePreset.data('preset') || 1);
    }

    if ($title.length && !$.trim($title.val())) {
      $title.val(this.autoGalleryTitle || 'Step by step gallery');
      $title.trigger('change');
    }
  };

  pointers.createGalleryAndContinue = function () {
    var $button = $('#gallery-create');
    if (!$button.length) {
      return true;
    }

    this.ensureGalleryDraft();
    this.creatingGalleryFromTutorial = true;
    this.hasNextStep = false;
    sessionStorage.setItem('sgg-tutorial-step', this.stepNumber + 1);
    if (this.current && this.current.pointer) {
      this.current.pointer('widget').hide();
    }
    $button.trigger('click');

    return false;
  };

  pointers.openWordPressMediaLibrary = function () {
    var $button = $('#gg-btn-upload:visible');
    if (!$button.length) {
      return true;
    }

    sessionStorage.setItem('sgg-tutorial-step', this.stepNumber + 1);
    if (this.current && this.current.data('wp-pointer')) {
      try {
        this.current.pointer('destroy');
      } catch (error) {
        this.current.removeData('wp-pointer');
      }
    }
    $('.sgg-tutorial-pointer').stop(true, true).remove();
    $button.trigger('click');

    return false;
  };

  pointers.redirectAfterMediaImport = function () {
    if (!isStorageAvailable() || sessionStorage.getItem('sgg-tutorial-after-import') !== '1') {
      return false;
    }

    var page = this.getCurrentPage();
    var stepNumber = Number(sessionStorage.getItem('sgg-tutorial-step'));
    var pointerData = this.pointersData[stepNumber];

    if (page === 'images') {
      sessionStorage.removeItem('sgg-tutorial-after-import');
      return false;
    }

    if (page === 'settings' && pointerData && this.getStepPage(pointerData.id) === 'images') {
      var imagesUrl = resolveNextUrl('@images-list');
      if (imagesUrl) {
        window.location = imagesUrl;
        return true;
      }
    }

    return false;
  };

  pointers.redirectPendingPage = function () {
    if (!isStorageAvailable()) {
      return false;
    }

    var pendingPage = sessionStorage.getItem('sgg-tutorial-pending-page');
    if (!pendingPage) {
      return false;
    }

    var page = this.getCurrentPage();
    if (page === pendingPage) {
      sessionStorage.removeItem('sgg-tutorial-pending-page');
      return false;
    }

    if (pendingPage === 'settings') {
      var settingsUrl = resolveNextUrl('@gallery-settings');
      if (settingsUrl) {
        window.location = settingsUrl;
        return true;
      }
    }

    sessionStorage.removeItem('sgg-tutorial-pending-page');
    return false;
  };

  pointers.findStepIndex = function (id) {
    for (var i = 0; i < this.pointersData.length; i++) {
      if (this.pointersData[i].id === id) {
        return i;
      }
    }

    return -1;
  };

  pointers.getStepPage = function (stepId) {
    if (stepId === 'step-2') {
      return 'presets';
    }

    if (['step-6', 'step-7', 'step-8', 'step-9'].indexOf(stepId) !== -1) {
      return 'images';
    }

    return 'settings';
  };

  pointers.getCurrentPage = function () {
    var params = new URLSearchParams(window.location.search);
    var module = params.get('module');
    var action = params.get('action');

    if (module !== 'galleries') {
      return false;
    }

    if (action === 'showPresets') {
      return 'presets';
    }

    if (action === 'view') {
      return 'images';
    }

    if (action === 'settings') {
      return 'settings';
    }

    return false;
  };

  pointers.syncStepWithCurrentPage = function () {
    var page = this.getCurrentPage();
    var pointerData = this.pointersData[this.stepNumber];

    if (!page || !pointerData) {
      return;
    }

    var stepPage = this.getStepPage(pointerData.id);
    if (page === stepPage) {
      return;
    }

    var presetsStart = this.findStepIndex('step-2');
    var settingsStart = this.findStepIndex('step-3');
    var imagesStart = this.findStepIndex('step-6');
    var settingsAfterImagesStart = this.findStepIndex('step-10');
    var nextStep = this.stepNumber;

    if (page === 'presets' && presetsStart !== -1) {
      nextStep = presetsStart;
    }

    if (page === 'images' && imagesStart !== -1) {
      nextStep = imagesStart;
    }

    if (page === 'settings') {
      if (stepPage === 'images' && settingsAfterImagesStart !== -1) {
        nextStep = settingsAfterImagesStart;
      } else if (settingsStart !== -1) {
        nextStep = settingsStart;
      }
    }

    if (nextStep !== this.stepNumber) {
      this.stepNumber = nextStep;
      sessionStorage.setItem('sgg-tutorial-step', this.stepNumber);
    }
  };

  pointers.prepareSettingsPage = function () {
    var $settingsTab = $('.change-tab[href="settings"]');
    if ($settingsTab.length && !$settingsTab.hasClass('nav-tab-active')) {
      $settingsTab.trigger('click');
    }

    $('[data-tab="settings"]').show();
    $('.gg-section-collapsed').removeClass('gg-section-collapsed');
    $('.gg-section-collapsed-extra').removeClass('gg-section-collapsed-extra');
    $('.settings-wrap table.form-table tbody').show();
  };

  pointers.prepareSettingsPreviewStep = function () {
    this.prepareSettingsPage();
    $('.settings-wrap').scrollTop(0);
    window.scrollTo(window.pageXOffset, 0);
  };

  if (pointers.resetStep) {
    sessionStorage.setItem('sgg-tutorial-step', 0);
  }

  var stepNumber = sessionStorage.getItem('sgg-tutorial-step');
  pointers.stepNumber = stepNumber !== null ? Number(stepNumber) : 0;
  if (pointers.redirectPendingPage()) {
    return;
  }

  if (pointers.redirectAfterMediaImport()) {
    return;
  }

  pointers.syncStepWithCurrentPage();
  pointers.autoGalleryTitle = pointers.autoGalleryTitle || 'Step by step gallery';
  pointers.actions = {};
  pointers.beforeAdvance = {
    'step-2': function () {
      if (this.creatingGalleryFromTutorial) {
        return true;
      }

      return this.createGalleryAndContinue();
    },
    'step-5': function () {
      return this.openWordPressMediaLibrary();
    },
  };
  pointers.beforeTarget = {
    'step-2': function () {
      this.ensureGalleryDraft();
    },
    'step-5': function () {
      if (!$('#importDialog:visible').length && $('button.gallery.import-to-gallery:visible').length) {
        $('button.gallery.import-to-gallery:visible').first().trigger('click');
      }
    },
    'step-10': function () {
      this.prepareSettingsPreviewStep();
    },
    'step-11': function () {
      this.prepareSettingsPage();
    },
    'step-12': function () {
      this.prepareSettingsPage();
    },
    'step-13': function () {
      this.prepareSettingsPage();
    },
    'step-14': function () {
      this.prepareSettingsPage();
    },
    'step-15': function () {
      this.prepareSettingsPage();
    },
    'step-16': function () {
      this.prepareSettingsPage();
    },
    'step-17': function () {
      this.prepareSettingsPage();
    },
    'step-18': function () {
      this.prepareSettingsPage();
    },
    'step-20': function () {
      this.prepareSettingsPage();
    },
  };

  pointers.actions['step-2'] = function () {
    var self = this;
    $('#gallery-create').on('mousedown.sggTutorial touchstart.sggTutorial', function () {
      self.ensureGalleryDraft();
    });
    $('#gallery-create').one('click.sggTutorial', function () {
      self.ensureGalleryDraft();
      self.creatingGalleryFromTutorial = true;
      sessionStorage.setItem('sgg-tutorial-step', self.stepNumber + 1);
      if ($('#gg-create-gallery-text input:first').val().length > 0) {
        self.current.pointer('widget').hide();
      }
    });
  };

  pointers.actions['step-4'] = function () {
    var self = this;
    $('button.gallery.import-to-gallery').one('click.sggTutorial', function () {
      window.setTimeout(function () {
        self.current.pointer('close');
      }, 40);
    });
  };

  pointers.actions['step-5'] = function () {
    var self = this;
    $('#gg-btn-upload').one('click.sggTutorial', function () {
      sessionStorage.setItem('sgg-tutorial-step', self.stepNumber + 1);
      window.setTimeout(function () {
        if (self.current && self.current.data('wp-pointer')) {
          try {
            self.current.pointer('destroy');
          } catch (error) {
            self.current.removeData('wp-pointer');
          }
        }
        $('.sgg-tutorial-pointer').stop(true, true).remove();
      }, 40);
    });
  };

  pointers.init = function () {
    this.injectStyles();
    this.setPointer();
  };

  pointers.init();
})(jQuery, GalleryPromoPointers);
