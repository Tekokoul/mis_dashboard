(function($) {

    'use strict';

    /*
     * Status - Select2 With Templating
     *
     */
    $('select[name="order_status"]').select2({
        minimumResultsForSearch: -1,
        templateResult: formatOrderStatus,
        templateSelection: formatOrderStatus,
        theme: 'bootstrap'
    });

    function formatOrderStatus(status) {
        if (!status.id) {
            return status.text;
        }

        var $status = $(
            '<span class="ecommerce-status '+ status.element.dataset.status +'">'+ status.text +'</span>'
        );

        return $status;
    };

}(jQuery));