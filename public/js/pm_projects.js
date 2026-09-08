$(document).ready(function() {
    // Tasks typed before the activity exists (the add form's Tasks card).
    // Rows are plain inputs named new_tasks[i][name|description]; the server
    // creates them with the activity.
    var $newTasks = $('#afcdc-new-tasks');
    if ($newTasks.length) {
        function renumber() {
            var rows = $newTasks.find('tr.afcdc-new-task');
            rows.each(function (i) {
                $(this).find('.afcdc-new-task__num').text(i + 1);
                $(this).find('input').each(function () { this.name = this.name.replace(/new_tasks\[\d+\]/, 'new_tasks[' + i + ']'); });
            });
            $newTasks.find('.afcdc-new-tasks__empty').prop('hidden', rows.length > 0);
        }
        $newTasks.on('click', '[data-add-task]', function (e) {
            e.preventDefault();
            var i = $newTasks.find('tr.afcdc-new-task').length;
            var $row = $('<tr class="afcdc-new-task"><td class="afcdc-new-task__num"></td>'
                + '<td><input type="text" class="form-control form-control-sm" placeholder="Task name" maxlength="250"></td>'
                + '<td><input type="text" class="form-control form-control-sm" placeholder="What done looks like (optional)"></td>'
                + '<td><a href="#" data-remove-task aria-label="Remove"><i class="bx bx-trash text-3 me-2"></i></a></td></tr>');
            $row.find('input').eq(0).attr('name', 'new_tasks[' + i + '][name]');
            $row.find('input').eq(1).attr('name', 'new_tasks[' + i + '][description]');
            $newTasks.find('.afcdc-new-tasks__empty').before($row);
            renumber();
            $row.find('input').first().trigger('focus');
        });
        $newTasks.on('click', '[data-remove-task]', function (e) { e.preventDefault(); $(this).closest('tr').remove(); renumber(); });
        // Enter in a task row adds the next row instead of submitting the form.
        $newTasks.on('keydown', 'input', function (e) { if (e.key === 'Enter') { e.preventDefault(); $newTasks.find('[data-add-task]').trigger('click'); } });
    }
    // Nothing saved yet means no details panel to load; the cascade and the
    // task modal handlers below are still wired up.
    if (project_id > 0) $.ajax({
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

// Goal -> objective -> programme. Each box below a change is emptied to a
// "Choose..." placeholder and refilled; nothing is selected by default, so
// an activity is never saved under the first option by nobody's choice. The
// choice named in window.afcdcPreselect (set by the filing-by-content code
// in custom.js, which reads the name and description) is kept when it is
// among the new options. A late answer to an earlier request is dropped.
function afcdcPreselect($select, key) {
    var pre = window.afcdcPreselect || {};
    if (pre[key] !== undefined && $select.find('option[value="' + pre[key] + '"]').length) {
        $select.val(String(pre[key]));
    }
    delete pre[key];
}
var afcdcSeq = { objective_id: 0, programme_id: 0 };
function afcdcReset($select, label) {
    $select.empty().append($('<option></option>').attr('value', '').text(label));
}

$('select[name="pillar_id"]').change(function(){
    var goal = parseInt($(this).val(), 10) || 0;
    var $objective = $('select[name="objective_id"]'), $programme = $('select[name="programme_id"]');
    var mine = ++afcdcSeq.objective_id;
    afcdcReset($objective, 'Choose an objective\u2026');
    afcdcReset($programme, 'Choose a programme\u2026');
    if (!goal) { $objective.trigger("change"); return; }
    $.ajax({
        url: lang_prefix + "/projects/get_objectives/" + goal,
        dataType: "json",
        success: function(data){
            if (mine !== afcdcSeq.objective_id) { return; }
            $.each(data.data, function(key, element){
                $objective.append($("<option></option>")
                    .attr("value", element.id)
                    .text(element.abbr + ' ' + element.name));
            });
            afcdcPreselect($objective, 'objective_id');
            $objective.trigger("change");
        }
    });
});

$('select[name="objective_id"]').change(function(){
    var objective = parseInt($(this).val(), 10) || 0;
    var $programme = $('select[name="programme_id"]');
    var mine = ++afcdcSeq.programme_id;
    afcdcReset($programme, 'Choose a programme\u2026');
    if (!objective) { $programme.trigger("change"); return; }
    $.ajax({
        url: lang_prefix + "/projects/get_programmes/" + objective,
        dataType: "json",
        success: function(data){
            if (mine !== afcdcSeq.programme_id) { return; }
            $.each(data.data, function(key, element){
                $programme.append($("<option></option>")
                    .attr("value", element.id)
                    .text(element.abbr + ' ' + element.name));
            });
            afcdcPreselect($programme, 'programme_id');
            $programme.trigger("change");
        }
    });
});

// A new activity starts with nothing chosen: the objective and programme
// boxes hold only their placeholder until a goal is picked or the wording
// suggests one. After a refused save the posted choices are kept as rendered.
if (project_id === 0 && !$('select[name="pillar_id"]').val()) {
    afcdcReset($('select[name="objective_id"]'), 'Choose an objective\u2026');
    afcdcReset($('select[name="programme_id"]'), 'Choose a programme\u2026');
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
                // posted twice and created duplicate tasks.
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
                                    : 'The task could not be deleted (' + xhr.status + ').');
                            }
                        });
                    });
                }
            }
        });
    });

});