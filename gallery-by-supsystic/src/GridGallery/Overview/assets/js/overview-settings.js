(function ($, app) {
  var Controller = function () {
    this.$newsContainer = $('.supsystic-overview-news');
    this.$mailButton = $('#send-mail');
    this.$faqToggles = $('.faq-title');
  };

  Controller.prototype.initScroll = function () {
    this.$newsContainer.slimScroll({
      height: '500px',
      railVisible: true,
      alwaysVisible: true,
      allowPageScroll: true,
    });
  };

  Controller.prototype.checkMail = function () {
    var self = this,
      $userMail = $('[name="email"]'),
      $userText = $('[name="message"]'),
      $dialog = $('#contact-form-dialog');

    function sendMail() {
      var defaultIconClass = self.$mailButton.find('i').attr('class');
      self.$mailButton.find('i').attr('class', 'fa fa-spinner fa-spin');
      self.$mailButton.attr('disabled', true);

      data = {};
      $.each($('#form-settings').serializeArray(), function (index, obj) {
        data[obj.name] = obj.value;
      });

      app.Ajax.Post({
        module: 'overview',
        action: 'sendMail',
        data: data,
      }).send(function (response) {
        self.$mailButton.find('i').attr('class', defaultIconClass);
        self.$mailButton.attr('disabled', false);

        if (!response.success) {
          $('#contact-form-dialog').find('.on-error').show();
        }
        $('#contact-form-dialog').find('.message').text(response.message);
        $('#contact-form-dialog').dialog({
          autoOpen: true,
          resizable: false,
          width: 500,
          height: 280,
          modal: true,
          buttons: {
            Close: function () {
              $('#contact-form-dialog').find('.on-error').hide();
              $(this).dialog('close');
            },
          },
        });
      });
    }

    this.$mailButton.on('click', function (e) {
      e.preventDefault();
      if (!$userMail.val() || !$userText.val()) {
        $userMail.closest('tr').find('.required').css('color', 'red');
        $userText.closest('tr').find('.required').css('color', 'red');
        $('.required-notification').show();
        return;
      }
      $('.required-notification').hide();
      sendMail();
    });
  };

  Controller.prototype.initFaqToggles = function () {
    var self = this;

    this.$faqToggles.on('click', function () {
      jQuery(this).find('div.description').toggle();
    });
  };

  Controller.prototype.init = function () {
    this.initScroll();
    this.checkMail();
    this.initFaqToggles();
  };

  $(document).ready(function () {
    var controller = new Controller();

    controller.init();
  });
})(jQuery, (window.SupsysticGallery = window.SupsysticGallery || {}));

jQuery(document).ready(function () {
  jQuery('.overview-section-btn').on('click', function () {
    jQuery('.overview-section').hide();
    jQuery(".overview-section[data-section='" + jQuery(this).data('section') + "']").show();
    jQuery('.overview-section-btn-active').removeClass('overview-section-btn-active');
    jQuery(this).addClass('overview-section-btn-active');
  });

  jQuery('.overview-section-btn').eq(0).trigger('click');
});
