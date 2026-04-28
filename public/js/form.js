$(function () {
    setDisplayElements('body');
});

function setDisplayElements(elem) {
    $(elem)
        .find('.elem-link')
        .each(function () {
            displayElements(this);
            $(this).change(function () {
                displayElements(this);
            });
        });
}

function displayElements(parent) {
    var closest = $(parent).closest('.div-form');
    if (closest.length == 0) {
        closest = $(parent).closest('form');
    }
    closest
        .find('[elem-link="' + $(parent).attr('elem') + '"]')
        .each(function (index) {
            var isVisible = $(parent).is(':visible') && $(this).attr('elem-value') == $(parent).val();
            var $target = $(this);
            var $group = $target.closest('.form-group');

            if (isVisible) {
                $group.show();
            } else {
                $group.hide();
            }

            // Toggle required attribute to avoid "not focusable" error when hidden
            var toggleRequired = function (el, visible) {
                var $el = $(el);
                if ($el.is('input, select, textarea')) {
                    if (visible) {
                        if ($el.attr('data-was-required')) {
                            $el.prop('required', true);
                            $el.removeAttr('data-was-required');
                        }
                    } else {
                        if ($el.prop('required')) {
                            $el.attr('data-was-required', 'true');
                            $el.prop('required', false);
                        }
                    }
                }
            };

            toggleRequired(this, isVisible);
            $target.find('input[required], input[data-was-required], select[required], select[data-was-required], textarea[required], textarea[data-was-required]').each(function () {
                toggleRequired(this, isVisible);
            });

            if ($target.hasClass('elem-link')) {
                displayElements($target);
            }
        });
}
