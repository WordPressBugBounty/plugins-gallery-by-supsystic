/**
 * The old jQuery UI dialogs of the plugin give every button the same "primary" class. When a dialog opens, the
 * buttons that only close it (Cancel, Close, No) are marked plain, so the action button is the only dark one. It runs
 * a tick later: the plugin's own handler of the same event rewrites the classes of the buttons first.
 */
(function ($) {
  'use strict';

  var plain = /^(cancel|close|no|back|отмена|закрыть|нет|назад)$/i;

  $(document).on('dialogopen', function (event) {
    var $dialog = $(event.target).closest('.ui-dialog');
    setTimeout(function () {
      $dialog.find('.ui-dialog-buttonset button').each(function () {
        var $button = $(this);
        $button.toggleClass('sgg-dlg-plain', plain.test($.trim($button.text())));
      });
    }, 0);
  });
})(jQuery);
