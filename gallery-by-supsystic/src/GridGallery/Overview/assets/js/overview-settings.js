(function($, app) {

    var Controller = function () {
        this.$newsContainer = $('.supsystic-overview-news');
        this.$mailButton = $('#send-mail');
        this.$subscribeButton = $('#subscribe-btn');
        this.$subscribeButtonRemind = $('.supsysticOverviewACBtnRemind');
        this.$subscribeButtonDisable = $('.supsysticOverviewACBtnDisable');
        this.$faqToggles = $('.faq-title');
    };

    Controller.prototype.initScroll = function() {

        this.$newsContainer.slimScroll({
            height: '500px',
            railVisible: true,
            alwaysVisible: true,
            allowPageScroll: true
        });
    };

    Controller.prototype.checkMail = function() {
        var self = this,
            $userMail = $('[name="email"]'),
            $userText = $('[name="message"]'),
            $dialog = $('#contact-form-dialog');

        function sendMail() {

            var defaultIconClass = self.$mailButton.find('i').attr('class');
            self.$mailButton.find('i').attr('class', 'fa fa-spinner fa-spin');
            self.$mailButton.attr('disabled', true);

            data = {};
            $.each($('#form-settings').serializeArray(), function(index, obj){
                data[obj.name] = obj.value;
            });

            app.Ajax.Post({
                module: 'overview',
                action: 'sendMail',
                data: data
            }).send(function(response) {
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
                        Close: function() {
                            $('#contact-form-dialog').find('.on-error').hide();
                            $(this).dialog("close");
                        }
                    }
                });
            });
        }

        this.$mailButton.on('click', function(e) {
            e.preventDefault();
            if(!$userMail.val() || !$userText.val()) {
                $userMail.closest('tr').find('.required').css('color', 'red');
                $userText.closest('tr').find('.required').css('color', 'red');
                $('.required-notification').show();
                return;
            }
            $('.required-notification').hide();
            sendMail();
        });

      };

        Controller.prototype.subscribeMail = function() {
            var self = this,
                $userMail = $('.supsysticOverviewACForm [name="email"]'),
                $userName = $('.supsysticOverviewACForm [name="username"]'),
                $dialog = $('#supsysticOverviewACFormDialog');

            function sendSubscribeMail() {

                var defaultIconClass = self.$subscribeButton.find('i').attr('class');
                self.$subscribeButton.find('i').attr('class', 'fa fa-spinner fa-spin');
                self.$subscribeButton.attr('disabled', true);

                data = {};
                $.each($('#overview-ac-form').serializeArray(), function(index, obj){
                    data[obj.name] = obj.value;
                });

                app.Ajax.Post({
                    module: 'overview',
                    action: 'sendSubscribeMail',
                    data: data
                }).send(function(response) {
                    self.$subscribeButton.find('i').attr('class', defaultIconClass);
                    self.$subscribeButton.attr('disabled', false);

                    if (!response.success) {
                        $('#supsysticOverviewACFormDialog').find('.on-error').show();
                    }
                    $('#supsysticOverviewACFormDialog').find('.message').text(response.message);
                    $('#supsysticOverviewACFormDialog').dialog({
                        autoOpen: true,
                        resizable: false,
                        width: 500,
                        height: 280,
                        modal: true,
                        buttons: {
                            Close: function() {
                                $('#supsysticOverviewACFormDialog').find('.on-error').hide();
                                $('.supsysticOverviewACFormOverlay').fadeOut();
                                $(this).dialog("close");
                            }
                        }
                    });
                });
        }

        this.$subscribeButton.on('click', function(e) {
            e.preventDefault();
            if(!$userMail.val() || !$userName.val() || !jQuery('#supsysticOverviewACTermsCheckbox').is(':checked')) {
                $('.supsysticOverviewACFormNotification').show();
                return;
            }
            $('.supsysticOverviewACFormNotification').hide();
            jQuery('#subscribe-btn, .supsysticOverviewACBtnRemind, .supsysticOverviewACBtnDisable').attr('disabled','disabled').prop('disabled','disabled');
            sendSubscribeMail();
        });

      };

      Controller.prototype.subscribeRemind = function() {
          var self = this;
          function sendSubscribeRemind() {
              var defaultIconClass = self.$subscribeButtonRemind.find('i').attr('class');
              self.$subscribeButtonRemind.find('i').attr('class', 'fa fa-spinner fa-spin');
              self.$subscribeButtonRemind.attr('disabled', true);
              console.log(SupsysticGallery.nonce);
              var data = {};

              app.Ajax.Post({
                  module: 'overview',
                  action: 'sendSubscribeRemind',
                  data: data
              }).send(function(response) {
                  self.$subscribeButtonRemind.find('i').attr('class', defaultIconClass);
                  self.$subscribeButtonRemind.attr('disabled', false);
                  $('.supsysticOverviewACFormOverlay').fadeOut();
              });
      }
      this.$subscribeButtonRemind.on('click', function(e) {
          e.preventDefault();
          sendSubscribeRemind();
      });
    };

    Controller.prototype.subscribeDisable = function() {
        var self = this;
        function sendSubscribeDisable() {
            var defaultIconClass = self.$subscribeButtonDisable.find('i').attr('class');
            self.$subscribeButtonDisable.find('i').attr('class', 'fa fa-spinner fa-spin');
            self.$subscribeButtonDisable.attr('disabled', true);
            var data = {};

            app.Ajax.Post({
                module: 'overview',
                action: 'sendSubscribeDisable',
                data: data
            }).send(function(response) {
                self.$subscribeButtonDisable.find('i').attr('class', defaultIconClass);
                self.$subscribeButtonDisable.attr('disabled', false);
                $('.supsysticOverviewACFormOverlay').fadeOut();
            });
    }
    this.$subscribeButtonDisable.on('click', function(e) {
        e.preventDefault();
        sendSubscribeDisable();
    });
  };

    Controller.prototype.initFaqToggles = function() {
        var self = this;

        this.$faqToggles.on('click', function() {
            jQuery(this).find('div.description').toggle();
        });
    };

    Controller.prototype.init = function() {
        this.initScroll();
        this.checkMail();
        this.subscribeMail();
        this.subscribeRemind();
        this.subscribeDisable();
        this.initFaqToggles();
    };

    $(document).ready(function() {
        var controller = new Controller();

        controller.init();
    });

})(jQuery, window.SupsysticGallery = window.SupsysticGallery || {});

jQuery(document).ready(function(){
  jQuery('.overview-section-btn').on('click', function(){
    jQuery(".overview-section").hide();
    jQuery(".overview-section[data-section='"+jQuery(this).data("section")+"']").show();
    jQuery('.overview-section-btn-active').removeClass('overview-section-btn-active');
    jQuery(this).addClass('overview-section-btn-active');
  });
  jQuery('.supsysticOverviewACBtnDisable, .supsysticOverviewACClose, .supsysticOverviewACBtnRemind').on('click', function(){
    jQuery('.supsysticOverviewACFormOverlay').fadeOut();
  });
  jQuery('.supsysticOverviewACTerms').on('click', function(){
    jQuery('.supsysticOverviewACFormOverlayTerms').fadeIn();
  });
  jQuery('.supsysticOverviewACFormOverlayTermsClose').on('click', function(){
    jQuery('.supsysticOverviewACFormOverlayTerms').fadeOut();
  });

  jQuery('.overview-section-btn').eq(0).trigger('click');
});
