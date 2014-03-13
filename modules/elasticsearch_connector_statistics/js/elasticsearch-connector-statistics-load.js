/**
 * @file
 * Asyncronyously load and image that will trgger the statistics collector. 
 */
(function ($) {
  var img = $("<img />").attr('src', Drupal.settings.elasticsearch_connector.statistics.image_src)
    .load(function() {
      if (!this.complete || typeof this.naturalWidth == "undefined" || this.naturalWidth == 0) {
        if (typeof console == "object") {
          console.log("Problem loading the statistics image.");
        }
      }
      else {
        if (typeof console == "object") {
          console.log("Statistics image has been loaded successfully.");
        }
      }
  });
})(jQuery);