/**
 * @file
 * Fixes the form part for loading image models.
 */

(function ($, Drupal) {
  Drupal.behaviors.imageWidgetCrop = {
    attach: function (context) {
      // Hide the form part for loading image models.
      if (!$('select[name="interpolator_firework_model"]').val().includes('allows images')) {
        $('#firework-image-field').hide();
      }
      // Since ID changes with Drupal Ajax, we need to use the name.
      $('select[name="interpolator_firework_model"]').off('change').on('change', function () {
        if (!$('select[name="interpolator_firework_model"] :selected').text().includes('allows images')) {
          $('#firework-image-field').hide();
          // If this change we set the value to empty.
          $('#firework-image-field select').val('');
        } else {
          $('#firework-image-field').show();
        }
      });
    }
  }
})(jQuery, Drupal);
