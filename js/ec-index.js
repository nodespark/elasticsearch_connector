(function ($) {

  Drupal.behaviors.ECIndexDialog = {
    attach: function (context, settings) {
      $('a.ec-index-dialog').once('ec-index-dialog', function () {
        $(this).click(function () {

          Drupal.ECIndexDialog.open($(this).attr('href'), $(this).html());
          Drupal.ECIndexDialog.populateIndex = function (cluster_id, index_name, settings) {
            // Add new option and select it.
            $('#' + settings.index_element_id)
                .append('<option value="' + index_name + '">' + index_name + '</option>')
                .val(index_name);

            // Trigger change to update the form cache.
            $('#' + settings.cluster_element_id).trigger('change');
          };

          return false;
        }, context);
      });
    }
  };

  /**
   * Our dialog object. Can be used to open a dialog to anywhere.
   */
  Drupal.ECIndexDialog = {
    dialog_open: false,
    open_dialog: null
  };

  /**
   * If this property is set to be a function, it
   * will be called when an entity is received from an overlay.
   */
  Drupal.ECIndexDialog.populateIndex = null;

  /**
   * Open a dialog window.
   *
   * @param href
   * @param title
   */
  Drupal.ECIndexDialog.open = function (href, title) {
    if (!this.dialog_open) {
      // Get the current window size and do 75% of the width and 90% of the height.
      // @todo Add settings for this so that users can configure this by themselves.
      var window_width = $(window).width() / 100 * 75;
      var window_height = $(window).height() / 100 * 90;
      this.open_dialog = $('<iframe class="elasticsearch-dialog-iframe" src="' + href + '"></iframe>').dialog({
        width: window_width,
        height: window_height,
        modal: true,
        resizable: false,
        position: ["center", 50],
        title: title,
        close: function () {
          Drupal.ECIndexDialog.dialog_open = false;
        }
      }).width(window_width - 10).height(window_height);
      $(window).bind("resize scroll", function () {
        // Move the dialog the main window moves.
        if (typeof Drupal.ECIndexDialog == "object" && Drupal.ECIndexDialog.open_dialog != null) {
          Drupal.ECIndexDialog.open_dialog.dialog("option", "position", ["center", 10]);
          Drupal.ECIndexDialog.setDimensions();
        }
      });
      this.dialog_open = true;
    }
  };

  /**
   * Set dimensions of the dialog depending on the current window size
   * and scroll position.
   */
  Drupal.ECIndexDialog.setDimensions = function () {
    if (typeof Drupal.ECIndexDialog == "object") {
      var window_width = $(window).width() / 100 * 75;
      var window_height = $(window).height() / 100 * 90;
      this.open_dialog.dialog("option", "width", window_width).dialog("option", "height", window_height).width(window_width - 10).height(window_height);
    }
  };

  /**
   * Close the dialog and provide an entity id and a title
   * that we can use in various ways.
   */
  Drupal.ECIndexDialog.close = function (cluster_id, index_name, settings) {
    this.open_dialog.dialog('close');
    this.open_dialog.dialog('destroy');
    this.open_dialog = null;
    this.dialog_open = false;
    // Call our populateIndex function if we have one.
    // this is used as an event.
    if (typeof this.populateIndex == "function") {
      this.populateIndex(cluster_id, index_name, settings);
    }
  }
}(jQuery));
