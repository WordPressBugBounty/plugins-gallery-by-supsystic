(function ($) {
  'use strict';

  function escapeAttribute(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function WelcomeTour(data) {
    this.slides = data.slides || [];
    this.i18n = data.i18n || {};
    this.closeAction = data.closeAction;
    this.current = 0;
    this.$modal = null;
  }

  WelcomeTour.prototype.buildList = function (items, label, modifier) {
    if (!items || !items.length) {
      return '';
    }

    var html = items.map(function (item) {
      return '<li><span class="sgg-wt-check"></span><span>' + item + '</span></li>';
    }).join('');

    return (
      '<div class="sgg-wt-feature-card sgg-wt-feature-card-' + modifier + '">' +
      '<div class="sgg-wt-feature-label">' + label + '</div>' +
      '<ul>' + html + '</ul>' +
      '</div>'
    );
  };

  WelcomeTour.prototype.buildMedia = function (slide) {
    return (
      '<div class="sgg-wt-media-frame">' +
      '<img class="sgg-wt-image" src="' + escapeAttribute(slide.image || '') + '" alt="' + escapeAttribute(slide.imageAlt || '') + '" />' +
      '</div>'
    );
  };

  WelcomeTour.prototype.buildSlide = function (slide, index) {
    var proCta = slide.ctaUrl
      ? '<a class="gg-pro-get-button sgg-wt-pro-button" target="_blank" rel="noopener noreferrer" href="' + escapeAttribute(slide.ctaUrl) + '"><span class="gg-pro-get-button-star">&#9733;</span>' + (slide.ctaLabel || this.i18n.getPro) + '</a>'
      : '';
    var tourCta = slide.tourUrl
      ? '<a class="sgg-wt-tour-button" href="' + escapeAttribute(slide.tourUrl) + '">' + (slide.tourLabel || this.i18n.startTour) + '</a>'
      : '';
    var cta = proCta || tourCta ? '<div class="sgg-wt-cta-wrap">' + tourCta + proCta + '</div>' : '';
    var eyebrow = slide.eyebrow ? '<div class="sgg-wt-eyebrow">' + slide.eyebrow + '</div>' : '';
    var featureCards = this.buildList(slide.free, 'Free', 'free') + this.buildList(slide.pro, 'PRO', 'pro');

    return (
      '<section class="sgg-wt-slide" data-index="' + index + '" aria-hidden="' + (index === 0 ? 'false' : 'true') + '">' +
      '<div class="sgg-wt-media">' +
      this.buildMedia(slide) +
      '<div class="sgg-wt-media-caption">' +
      '<span>' + (index + 1) + '</span>' +
      '<b>' + (slide.eyebrow || '') + '</b>' +
      '</div>' +
      '</div>' +
      '<div class="sgg-wt-body">' +
      eyebrow +
      '<h3 class="sgg-wt-title">' + slide.title + '</h3>' +
      '<div class="sgg-wt-text">' + slide.text + '</div>' +
      '<div class="sgg-wt-features">' + featureCards + '</div>' +
      cta +
      '</div>' +
      '</section>'
    );
  };

  WelcomeTour.prototype.buildDots = function () {
    var html = '';
    for (var i = 0; i < this.slides.length; i++) {
      html += '<button type="button" class="sgg-wt-dot' + (i === 0 ? ' sgg-wt-dot-active' : '') + '" data-index="' + i + '" aria-label="Slide ' + (i + 1) + '"></button>';
    }
    return html;
  };

  WelcomeTour.prototype.open = function () {
    var self = this;

    if (!this.slides.length) {
      return;
    }

    if (this.$modal) {
      this.$modal.show();
      this.goTo(0);
      return;
    }

    var slidesHtml = this.slides.map(function (slide, index) {
      return self.buildSlide(slide, index);
    }).join('');

    var markup =
      '<div class="sgg-wt-overlay" role="dialog" aria-modal="true">' +
      '<div class="sgg-wt-modal">' +
      '<button type="button" class="sgg-wt-close" aria-label="' + escapeAttribute(this.i18n.close) + '" title="' + escapeAttribute(this.i18n.close) + '">&times;</button>' +
      '<div class="sgg-wt-viewport"><div class="sgg-wt-track">' + slidesHtml + '</div></div>' +
      '<div class="sgg-wt-progress"><span></span></div>' +
      '<div class="sgg-wt-footer">' +
      '<button type="button" class="sgg-wt-prev" disabled><span>&larr;</span>' + this.i18n.prev + '</button>' +
      '<div class="sgg-wt-footer-center">' +
      '<div class="sgg-wt-counter"></div>' +
      '<div class="sgg-wt-dots">' + this.buildDots() + '</div>' +
      '</div>' +
      '<button type="button" class="sgg-wt-next">' + this.i18n.next + '<span>&rarr;</span></button>' +
      '</div>' +
      '</div>' +
      '</div>';

    this.$modal = $(markup).appendTo('body');
    this.bindEvents();
    this.goTo(0);
  };

  WelcomeTour.prototype.bindEvents = function () {
    var self = this;

    this.$modal.on('click', '.sgg-wt-close', function () {
      self.close();
    });
    this.$modal.on('click', '.sgg-wt-overlay', function (event) {
      if (event.target === this) {
        self.close();
      }
    });
    this.$modal.on('click', '.sgg-wt-next', function () {
      var slide = self.slides[self.current] || {};
      if (self.current === self.slides.length - 1) {
        if (slide.tourUrl) {
          self.markClosed();
          window.location = slide.tourUrl;
          return;
        }
        self.close();
        return;
      }
      self.goTo(self.current + 1);
    });
    this.$modal.on('click', '.sgg-wt-prev', function () {
      self.goTo(self.current - 1);
    });
    this.$modal.on('click', '.sgg-wt-dot', function () {
      self.goTo(parseInt($(this).data('index'), 10));
    });
    $(document).on('keydown.sggWelcomeTour', function (event) {
      if (!self.$modal || !self.$modal.is(':visible')) {
        return;
      }
      if (event.key === 'Escape') {
        self.close();
      } else if (event.key === 'ArrowRight') {
        self.$modal.find('.sgg-wt-next').trigger('click');
      } else if (event.key === 'ArrowLeft') {
        self.$modal.find('.sgg-wt-prev').trigger('click');
      }
    });
  };

  WelcomeTour.prototype.goTo = function (index) {
    if (index < 0 || index >= this.slides.length) {
      return;
    }

    this.current = index;
    var isLast = index === this.slides.length - 1;
    var progress = ((index + 1) / this.slides.length) * 100;

    this.$modal.find('.sgg-wt-track').css('transform', 'translateX(-' + index * 100 + '%)');
    this.$modal.find('.sgg-wt-dot').removeClass('sgg-wt-dot-active').eq(index).addClass('sgg-wt-dot-active');
    this.$modal.find('.sgg-wt-slide').attr('aria-hidden', 'true').find('a, button, input, select, textarea').attr('tabindex', '-1');
    this.$modal.find('.sgg-wt-slide').eq(index).attr('aria-hidden', 'false').find('a, button, input, select, textarea').removeAttr('tabindex');
    this.$modal.find('.sgg-wt-prev').prop('disabled', index === 0);
    this.$modal.find('.sgg-wt-next').html((isLast ? this.i18n.finish : this.i18n.next) + '<span>&rarr;</span>');
    this.$modal.find('.sgg-wt-counter').text((index + 1) + ' / ' + this.slides.length);
    this.$modal.find('.sgg-wt-progress span').css('width', progress + '%');
  };

  WelcomeTour.prototype.markClosed = function () {
    if (this.closeAction && typeof ajaxurl !== 'undefined') {
      $.post(ajaxurl, {
        action: this.closeAction,
        _wpnonce: typeof SupsysticGallery !== 'undefined' ? SupsysticGallery.nonce : '',
      });
    }
  };

  WelcomeTour.prototype.close = function () {
    if (this.$modal) {
      this.$modal.hide();
    }
    $(document).off('keydown.sggWelcomeTour');
    this.markClosed();
  };

  $(function () {
    if (typeof GalleryWelcomeTourData === 'undefined') {
      return;
    }

    var tour = new WelcomeTour(GalleryWelcomeTourData);
    window.sggWelcomeTour = tour;

    $(document).on('click', '#sgg-start-welcome-tour', function (event) {
      event.preventDefault();
      tour.open();
    });

    if (GalleryWelcomeTourData.autoShow) {
      tour.open();
    }
  });
})(jQuery);
