(function ($) {
  /**
   * Attach the child dialog behavior to new content.
   */
  Drupal.behaviors.ECIndexDialogChild = {
    attach: function(context, settings) {
      // Get the entity id and title from the settings provided by the views display.
      var cluster_id = settings.elasticsearch_connector.dialog.cluster_id;
      var index_name = settings.elasticsearch_connector.dialog.index_name;
      if (cluster_id != null && cluster_id != '') {
        // Close the dialog by communicating with the parent.
        parent.Drupal.ECIndexDialog.close(cluster_id, index_name, settings.elasticsearch_connector.dialog);
      }
    }
  }
})(jQuery);
