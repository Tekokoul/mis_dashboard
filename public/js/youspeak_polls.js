function loadPopupContent(id, type, pollsId) {
    $.ajax({
        url: lang_prefix + "/polls/"+type+"/" + pollsId +"/"+id,
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
    var type = $(this).data('type');
    var pollsId = $(this).data('polls-id');

    loadPopupContent(id, type, pollsId);
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
                        url: lang_prefix + "/polls/event_delete/" + t.data('type') + "/" + t.data('id'),
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


function load_poll_details(poll_id) {
    $.ajax({
        url: lang_prefix + "/polls/get_details/" + poll_id,
        type: "GET",
        dataType: "html",
        success: function(response) {
            // `response` is the HTML content returned from the server
            $("#poll_details").html(response);
            $("#poll_details").find('[data-plugin-selectTwo]').select2({
                dropdownParent: $('#project_details')
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log("AJAX Error: " + textStatus + " - " + errorThrown);
        }
    });    
}