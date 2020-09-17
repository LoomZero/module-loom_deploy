(function ($) {

  var script = {

    attach: function (context) {
      $('.deploy-state-form').each(script.init);
    },

    init: function () {
      var form = $(this);

      form.find('.deploy-field-delete:not(.deploy-field-delete__processed)').each(script.removeButton);
      form.find('.deploy-field-wrapper').each(script.errorHandler);
    },

    removeButton: function () {
      var button = $(this).addClass('deploy-field-delete__processed');

      var wrapper = $('<div class="deploy-field-delete__fake"></div>');
      var fakeButton = $('<div class="deploy-field-delete__button button">Delete</div>');
      var timeout = null;

      wrapper.append(fakeButton);
      button.before(wrapper);
      wrapper.append(button);

      fakeButton.on('click', function () {
        wrapper.addClass('deploy-field-delete__fake--open');
      });
      wrapper
        .on('mouseleave', function () {
          timeout = setTimeout(function () {
            wrapper.removeClass('deploy-field-delete__fake--open');
          }, 1000);
        })
        .on('mouseenter', function () {
          clearTimeout(timeout);
        });
    },

    errorHandler: function () {
      var fieldset = $(this);

      if (fieldset.find('.error').length) {
        fieldset.addClass('error');
      }
    },

  };

  Drupal.behaviors.deployField = script;

})(jQuery);
