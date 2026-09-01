$(document).ready(function() {
    $.ajax({
        url: lang_prefix + "/projects/get_details/" + project_type + "/" + project_id,
        type: "GET",
        dataType: "html",
        success: function(response) {
            // `response` is the HTML content returned from the server
            $("#project_details").html(response);
            $("#project_details").find('[data-plugin-selectTwo]').select2({
                dropdownParent: $('#project_details')
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log("AJAX Error: " + textStatus + " - " + errorThrown);
        }
    });

    // $("#type").change(function(){
    //     // Get the selected value
    //     var selectedValue = $(this).val();
    //     // Make AJAX call to server to retrieve JSON data
    //     var url = '';
    //     if(project_id===0){
    //         url = lang_prefix + "/projects/get_details/" + selectedValue;
    //     } else {
    //         url = lang_prefix + "/projects/get_details/" + selectedValue + "/" + project_id;
    //     }
    //     $.ajax({
    //         url: lang_prefix + "/projects/get_details/" + selectedValue + "/" + project_id,
    //         type: "GET",
    //         dataType: "html",
    //         success: function(response) {
    //             // `response` is the HTML content returned from the server
    //             $("#project_details").html(response);
    //         },
    //         error: function(jqXHR, textStatus, errorThrown) {
    //             console.log("AJAX Error: " + textStatus + " - " + errorThrown);
    //         }
    //     });
    // });

// Use the name attribute directly for 'pillar_id'
$('select[name="pillar_id"]').change(function(){
    // Get the selected value
    var selectedValue = $(this).val();
    // Make AJAX call to server to retrieve JSON data
    $.ajax({
        url: lang_prefix + "/projects/get_objectives/" + selectedValue,
        dataType: "json",
        success: function(data){
            // Clear options from the second dropdown
            $('select[name="objective_id"]').empty();
            $('select[name="programme_id"]').empty();

            // Populate options in the second dropdown based on selected value
            $.each(data.data, function(key, element){
                $('select[name="objective_id"]').append($("<option></option>")
                    .attr("value", element.id)
                    .text(element.abbr + ' ' + element.name));

                $('select[name="objective_id"]').trigger("change");
            });
        }
    });
});

// Use the name attribute directly for 'objective_id'
$('select[name="objective_id"]').change(function(){
    // Get the selected value
    var selectedValue = $(this).val();
    // Make AJAX call to server to retrieve JSON data
    console.log(lang_prefix + "/projects/get_programmes/" + selectedValue);
    $.ajax({
        url: lang_prefix + "/projects/get_programmes/" + selectedValue,
        dataType: "json",
        success: function(data){
            // Clear options from the second dropdown
            $('select[name="programme_id"]').empty();
            // Populate options in the second dropdown based on selected value
            $.each(data.data, function(key, element){
                $('select[name="programme_id"]').append($("<option></option>")
                    .attr("value", element.id)
                    .text(element.abbr + ' ' + element.name));
            });
        }
    });
});

// Trigger the change event for 'pillar_id' dynamically
if(project_id === 0){
    $('select[name="pillar_id"]').trigger("change");
}



    // $.fn.modal.Constructor.prototype.enforceFocus = function() {};

    function loadPopupContent(id, projectId) {
        $.ajax({
            url: lang_prefix + "/projects/task/" + projectId +"/"+id,
            method: 'GET',
            success: function (response) {
                // Set the HTML of the popup container to the loaded content
                $('#taskModal').html(response);

                $("#taskModal").find('[data-plugin-selectTwo]').select2({
                    dropdownParent: $('#taskModal')
                });

                // Add click handlers for the update and cancel buttons
                $('.modal-confirm').on('click', function () {
                    // Make an AJAX request to update the data
                    $('#taskform').submit();
                    $.magnificPopup.close();
                });

                $('.modal-dismiss').on('click', function (event) {
                    // Close the popup when the cancel button is clicked
                    event.preventDefault(); // Prevent the default form submission behavior
                    $.magnificPopup.close();
                });
            }, error: function (xhr, status, error) {
                console.error(xhr, status, error);
            }
        });
    }

    $(document).on('click', '.open-task-modal', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var projectId = $(this).data('project-id');

        loadPopupContent(id, projectId);
        $.magnificPopup.open({
            items: {
                src: '#taskModal', type: 'inline', modal: true
            }
        });
    });

    $(document).on('click', '.delete-task-modal', function (e) {
        e.preventDefault();
        var t = $(this);
        $.magnificPopup.open({
            items: {
                src: '#deleteTaskModal',
                type: 'inline'
            },
            preloader: false,
            modal: true,
            callbacks: {
                open: function () {
                    var mp = $.magnificPopup.instance;
                    var content = $(this.content);
                    content.on('click', '.modal-dismiss', function (e) {
                        e.preventDefault();
                        $.magnificPopup.close();
                    });
                    content.on('click', '.modal-confirm', function (e) {
                        e.preventDefault();
                        $.magnificPopup.close();
                        $.ajax({
                            method: "GET",
                            url: lang_prefix + "/projects/task_delete/" + t.data('id'),
                            dataType: "json",
                            cache: false,
                            success: function (data) {
                                // new PNotify({
                                //     title: '<b>SUCCESS</b>',
                                //     text: data.message,
                                //     type: 'success',
                                //     addclass: notificationclass,
                                //     stack: {"dir1": "up", "dir2": "left"},
                                //     width: "50%"
                                // });
                                location.reload();
                            }
                        });
                    });
                }
            }
        });
    });

});