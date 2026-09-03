$(document).ready(function() {
    // The task panel: a spinner while it loads (markup is in progress_edit.php),
    // the table on success, and a real message with a way out on failure -
    // it used to stay blank forever if the request failed or the session
    // had expired.
    function loadTasks() {
        $("#project_details").attr("aria-busy", "true");
        $.ajax({
            url: lang_prefix + "/projects/get_tasks_details/" + project_id,
            type: "GET",
            dataType: "html",
            success: function (response) {
                $("#project_details").html(response).attr("aria-busy", "false");
            },
            error: function (xhr, status) {
                var why = (xhr.status === 401 || xhr.status === 403)
                    ? 'Your session has expired. <a href="">Reload the page</a> and sign in again.'
                    : 'The task list could not be loaded (' + (xhr.status || status) + '). <a href="#" class="afcdc-retry">Try again</a>';
                $("#project_details").html('<div class="card card-modern"><div class="card-body"><p class="mb-0" role="alert">' + why + '</p></div></div>').attr("aria-busy", "false");
            }
        });
    }
    loadTasks();
    $(document).on('click', '.afcdc-retry', function (e) { e.preventDefault(); loadTasks(); });


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


    // The popup opens only once its content has arrived. It used to open
    // first, locked (modal: true, so no close button, no ESC, no click-out),
    // and fetch afterwards - if the request failed or the session had
    // expired the only way out was a page reload, on the one control the
    // page exists for. Now: open on success, and on failure show a short
    // card with a working Cancel.
    function openPopup() {
        $.magnificPopup.open({
            items: { src: '#taskModal', type: 'inline' },
            closeOnBgClick: false
        });
    }

    function loadPopupContent(dataId, projectId, memberId) {
        $.ajax({
            url: lang_prefix + "/projects/get_task_details/" + dataId + "/" + projectId + "/" +memberId,
            method: 'GET',
            success: function(response) {
                $('#taskModal').html(response);
                openPopup();

                // Save once. The button greys to "Saving…" and a second click
                // (or Enter in a field plus a click) is ignored; the popup is
                // NOT closed here - closing removed the button the instant it
                // said Saving…, and the POST navigates the page anyway.
                $('#taskModal .modal-confirm').on('click', function(event) {
                    event.preventDefault();
                    $('#taskform').trigger('submit');
                });
                $('#taskform').on('submit', function() {
                    if ($(this).data('busy')) { return false; }
                    $(this).data('busy', true);
                    $('#taskModal .modal-confirm').prop('disabled', true).attr('aria-busy', 'true').text('Saving…');
                });

                $('#taskModal .modal-dismiss').on('click', function(event) {
                    event.preventDefault();
                    $.magnificPopup.close();
                });
            },
            error: function(xhr, status, error) {
                var why = (xhr.status === 401 || xhr.status === 403)
                    ? 'Your session has expired. Reload the page and sign in again.'
                    : 'The form could not be loaded (' + (xhr.status || status) + '). Try again in a moment.';
                $('#taskModal').html(
                    '<section class="card"><header class="card-header"><h2 class="card-title">Record delivery</h2></header>' +
                    '<div class="card-body"><p class="mb-0">' + why + '</p></div>' +
                    '<footer class="card-footer text-end"><button type="button" class="btn btn-default modal-dismiss">Cancel</button></footer></section>'
                );
                openPopup();
                $('#taskModal .modal-dismiss').on('click', function(event) {
                    event.preventDefault();
                    $.magnificPopup.close();
                });
            }
        });
    }

    $(document).on('click', '.open-task-modal', function(e) {
        e.preventDefault();
        var dataId = $(this).data('id');
        var projectId = $(this).data('project-id');
        var memberId = $(this).data('member-id');
        loadPopupContent(dataId, projectId, memberId);
    });



});