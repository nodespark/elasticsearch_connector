Drupal.elasticsearch_connector = {
  // Global status variable for iframe rendering. 
  admin_iframe: false
};

(function ($) {

  Drupal.behaviors.elasticsearch_connector_hosted_service = {
    attach: function (context, settings) {
      $('a.free-hosting', context).once('free-hosting', function () {
        $(this).click(function () {
          if (!Drupal.elasticsearch_connector.admin_iframe) {
            // TODO: Handle double click!
            $('#edit-options-use-authentication').attr('checked', true).trigger('change');
            var iframe = document.createElement('iframe');
            iframe.id = "hosted-service";
            iframe.src = Drupal.settings.elasticsearch_connector.hosting_url;
            iframe.height = "920px;"
            iframe.width = "100%;"
            $($(this).attr('href')).append(iframe);
            $("html, body").animate({ scrollTop: $($(this).attr('href')).offset().top}, 1000);
            Drupal.elasticsearch_connector.admin_iframe = true;
          }

          return false;
        });
      });
    }
  };

  //Register Iframe PostMessage functionality.
  $(document).ready(function () {
    $.receiveMessage(
      function(e){
        if (e.data) {
          var $result = $.parseJSON(e.data);
          $('#hosted-service').remove();
          Drupal.elasticsearch_connector.admin_iframe = false;
          $('#edit-url').val($result.url);
          $('#edit-options-authentication-type-digest').attr('checked', true);
          $('#edit-options-username').val($result.id);
          $('#edit-options-password').val($result.hash);
        }
      },
      Drupal.settings.elasticsearch_connector.hosting_domain
    );
  });

})(jQuery);
