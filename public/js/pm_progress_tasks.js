$(document).ready(function() {
    $.ajax({
        url: lang_prefix + "/projects/get_tasks_details/" + project_id,
        type: "GET",
        dataType: "html",
        success: function (response) {
            // `response` is the HTML content returned from the server
            $("#project_details").html(response);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log("AJAX Error: " + textStatus + " - " + errorThrown);
        }
    });


    // $(document).on('click', '.open-task-modal', function(event) {
    //     event.preventDefault();
    //     var id = $(this).data('id');
    //     $.ajax({
    //         url: lang_prefix + "/projects/get_task_details/" + taskId,
    //         type: 'GET',
    //         success: function(data) {
    //             $.magnificPopup.open({
    //                 items: {
    //                     src: '<div class="modal-text">' + data + '</div>',
    //                     type: 'inline'
    //                 }
    //             });
    //         },
    //         error: function(xhr, errmsg, err) {
    //             console.log(xhr.status + ': ' + xhr.responseText);
    //         }
    //     });
    // });


    function loadPopupContent(dataId, projectId, memberId) {
        $.ajax({
            url: lang_prefix + "/projects/get_task_details/" + dataId + "/" + projectId + "/" +memberId,
            method: 'GET',
            success: function(response) {
                // Set the HTML of the popup container to the loaded content
                $('#taskModal').html(response);

                // Add click handlers for the update and cancel buttons
                $('.modal-confirm').on('click', function() {
                    // Make an AJAX request to update the data
                    $('#taskform').submit();
                    $.magnificPopup.close();
                });

                $('.modal-dismiss').on('click', function(event) {
                    // Close the popup when the cancel button is clicked
                    event.preventDefault(); // Prevent the default form submission behavior
                    $.magnificPopup.close();
                });
            },
            error: function(xhr, status, error) {
                console.error(xhr, status, error);
            }
        });
    }

    $(document).on('click', '.open-task-modal', function(e) {
        e.preventDefault();
        var dataId = $(this).data('id');
        var projectId = $(this).data('project-id');
        var memberId = $(this).data('member-id');

        loadPopupContent(dataId, projectId, memberId);
        $.magnificPopup.open({
            items: {
                src: '#taskModal',
                type: 'inline',
                modal: true
            }
        });
    });



});