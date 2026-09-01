$(document).ready(function () {
    // Function to update scope_id values based on the selected scope
    function updateScopeIdValues(selectedScope) {
        // Make an AJAX request to fetch scope_id values based on the selected scope
        // Replace the URL with your server-side endpoint
        var url = lang_prefix + "/price_rates/get_scope_values/" + selectedScope;

        $.get(url, function (data) {
            // Clear existing options
            $("select[name='scope_id[]']").empty();

            // // Populate scope_id dropdown with new options
            $.each(data.data.data, function (index, value) {
                var option = $('<option>', {
                    value: value.id,
                    text: value.name
                });

                // Check if the id is in the dynamic_field_data array
                if ($.inArray(value.id.toString(), dynamic_field_data) !== -1) {
                    option.prop('selected', true);
                }

                $("select[name='scope_id[]']").append(option);
            });
        });

    }

    // Attach a change event handler to the "scope" dropdown
    $("select[name='scope']").change(function () {
        var selectedScope = $(this).val();
        updateScopeIdValues(selectedScope);
    });

    // Set preselected values (for editing)
    var preselectedScope = $("select[name='scope']").val(); // Replace with your preselected value
    $("select[name='scope']").val(preselectedScope);

    // Trigger change event to update scope_id dropdown
    updateScopeIdValues(preselectedScope);
});