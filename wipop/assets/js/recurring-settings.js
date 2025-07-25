jQuery(function ($) {
    const checkbox = $('#_wipop_recurring_enabled');
    const select = $('#_wipop_recurring_period');
    const selectWrapper = $('#_wipop_recurring_period').closest('p');

    function toggleSelectVisibility() {
        if (checkbox.is(':checked')) {
            selectWrapper.show();
        } else {
            selectWrapper.hide();
            select.val('');
        }
    }

    toggleSelectVisibility();
    checkbox.on('change', toggleSelectVisibility);
});

