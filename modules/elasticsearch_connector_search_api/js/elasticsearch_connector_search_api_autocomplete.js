(function ($) {
/**
 * Hides the autocomplete suggestions.
 */
Drupal.jsAC.prototype.hidePopup = function (keycode) {
  // Select item if the right key or mousebutton was pressed.
  if (this.selected && ((keycode && keycode != 46 && keycode != 8 && keycode != 27) || !keycode)) {
    this.input.value = $('a', this.selected).attr("href");
    return $('a', this.selected).attr("href");
  }

  // Hide popup.
  var popup = this.popup;
  if (popup) {
    this.popup = null;
    $(popup).fadeOut('fast', function () { $(popup).remove(); });
  }
  this.selected = false;
  $(this.ariaLive).empty();
};
        
/**
 * Attaches the autocomplete behavior to all required fields.
 */
Drupal.behaviors.elasticsearch_autocomplete = {
  attach: function (context, settings) {
    var acdb = [];

    $('input.elasticsearch-autocomplete', context).once('autocomplete', function () {
      var uri = $('#' + this.id + "-autocomplete").val();
      if (!acdb[uri]) {
        acdb[uri] = new Drupal.ACDB(uri);
      }
      var $input = $(this)
        .attr('autocomplete', 'OFF')
        .attr('aria-autocomplete', 'list');
      $($input[0].form).unbind();
      $($input[0].form).submit(Drupal.ELAutocompleteSubmit);
      $input.parent()
        .attr('role', 'application')
        .append($('<span class="element-invisible" aria-live="assertive"></span>')
          .attr('id', $input.attr('id') + '-autocomplete-aria-live')
        );
      $input.unbind();
      new Drupal.jsAC($input, acdb[uri]);
    });
  },
  weight: 20
};

/**
 * Prevents the form from submitting if the suggestions popup is open
 * and closes the suggestions popup when doing so.
 */
Drupal.ELAutocompleteSubmit = function () {
  var href = '';
  $('#autocomplete').each(function () {
    href = this.owner.hidePopup();
  });

  if (href == '' || typeof href == 'undefined') {
    return false;;
  }
  else {
    window.location = href;
  }

  return false;
};
})(jQuery);

