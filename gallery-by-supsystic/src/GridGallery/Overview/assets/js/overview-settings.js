(function ($) {
  $(document).ready(function () {
    $('.sgg-overview-resource-link[href^="#"]').on('click', function (event) {
      var target = $(this.getAttribute('href'));

      if (!target.length) {
        return;
      }

      event.preventDefault();
      $('html, body').animate(
        {
          scrollTop: Math.max(0, target.offset().top - 40),
        },
        260
      );
    });
  });
})(jQuery);
