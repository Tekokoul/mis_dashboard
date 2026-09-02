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

                // Scoped to this modal and re-bound with .off(): the page-wide
                // '.modal-confirm' selector used to stack a handler on the
                // delete dialog's Confirm on every open, and the Update button
                // (no type, so a submit) had no preventDefault - one click
                // posted twice and created duplicate KPIs.
                $('#taskModal .modal-confirm').off('click').on('click', function (event) {
                    event.preventDefault();
                    $('#taskform').submit();
                    $.magnificPopup.close();
                });

                $('#taskModal .modal-dismiss').off('click').on('click', function (event) {
                    event.preventDefault();
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
                        // POST with the CSRF token - a GET delete could be
                        // fired by an <img src> on any page.
                        $.ajax({
                            method: "POST",
                            url: lang_prefix + "/projects/task_delete/" + t.data('id'),
                            data: { csrf: window.CSRF_TOKEN || '' },
                            dataType: "json",
                            cache: false,
                            success: function (data) {
                                location.reload();
                            },
                            error: function (xhr) {
                                alert(xhr.status === 403
                                    ? 'The page had been open too long for the deletion to be accepted. Reload and try again.'
                                    : 'The KPI could not be deleted (' + xhr.status + ').');
                            }
                        });
                    });
                }
            }
        });
    });

});