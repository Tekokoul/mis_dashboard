$(document).ready(function() {
    // Tasks typed before the activity exists (the add form's Tasks card).
    // Rows are plain inputs named new_tasks[i][name|description]; the server
    // creates them with the activity.
    var $newTasks = $('#afcdc-new-tasks');
    if ($newTasks.length) {
        function renumber() {
            // On the edit form the saved tasks are numbered first, so a row
            // typed underneath continues the list instead of restarting it.
            var saved = $newTasks.find('tr.afcdc-task').length;
            var rows = $newTasks.find('tr.afcdc-new-task');
            rows.each(function (i) {
                $(this).find('.afcdc-new-task__num').text(saved + i + 1);
                $(this).find('input').each(function () { this.name = this.name.replace(/new_tasks\[\d+\]/, 'new_tasks[' + i + ']'); });
            });
            $newTasks.find('.afcdc-new-tasks__empty').prop('hidden', (rows.length + saved) > 0);
        }
        // Rows that came back with a refused save have no number until this
        // runs, so the list read "1, 2, 3, blank, blank".
        renumber();
        $newTasks.on('click', '[data-add-task]', function (e) {
            e.preventDefault();
            var i = $newTasks.find('tr.afcdc-new-task').length;
            // A task gets its Status box once it exists; until then the cell says so.
            var withStatus = $newTasks.find('th[data-afcdc-status-col]').length > 0;
            var $row = $('<tr class="afcdc-new-task"><td class="afcdc-new-task__num"></td>'
                + '<td><input type="text" class="form-control form-control-sm" placeholder="Task name" maxlength="250"></td>'
                + '<td><input type="text" class="form-control form-control-sm" placeholder="What done looks like (optional)"></td>'
                + (withStatus ? '<td class="afcdc-task__status-later">after saving</td>' : '')
                + '<td><a href="#" data-remove-task aria-label="Remove"><i class="bx bx-trash text-3 me-2"></i></a></td></tr>');
            $row.find('input').eq(0).attr('name', 'new_tasks[' + i + '][name]');
            $row.find('input').eq(1).attr('name', 'new_tasks[' + i + '][description]');
            $newTasks.find('.afcdc-new-tasks__empty').before($row);
            renumber();
            $row.find('input').first().trigger('focus');
        });
        $newTasks.on('click', '[data-remove-task]', function (e) { e.preventDefault(); $(this).closest('tr').remove(); renumber(); });
        // A saved task is marked, not removed from the page: the server has to
        // be told to delete it, and until Save is pressed nothing has happened,
        // so the mark can be taken back.
        $newTasks.on('click', '[data-remove-existing-task]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('tr.afcdc-task');
            $row.addClass('afcdc-task--removed');
            $row.find('input[name$="[remove]"]').val('1');
            // A row on its way out must not hold the save up for a blank name.
            $row.find('input[type="text"]').prop('readonly', true).removeAttr('required')
                .closest('.form-group').removeClass('afcdc-field--missing');
            // Nor does its status get posted: a status is for a task that stays.
            $row.find('select.afcdc-task__status, select.afcdc-task__pct').prop('disabled', true);
            $(this).prop('hidden', true);
            $row.find('[data-undo-remove-task]').prop('hidden', false);
        });
        $newTasks.on('click', '[data-undo-remove-task]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('tr.afcdc-task');
            $row.removeClass('afcdc-task--removed');
            $row.find('input[name$="[remove]"]').val('0');
            $row.find('input[type="text"]').prop('readonly', false).eq(0).attr('required', 'required');
            $row.find('select.afcdc-task__status, select.afcdc-task__pct').prop('disabled', false);
            $(this).prop('hidden', true);
            $row.find('[data-remove-existing-task]').prop('hidden', false);
        });
        // The Status box: its colour band follows the choice, and a choice
        // that differs from what was loaded counts as an unsaved change
        // (custom.js looks for data-afcdc-touched="1" on selects).
        $newTasks.on('change', 'select.afcdc-task__status', function () {
            var was = this.getAttribute('data-afcdc-was');
            this.setAttribute('data-afcdc-status', this.value);
            this.setAttribute('data-afcdc-touched', (was !== null && this.value === was) ? '0' : '1');
            // How far along belongs to In progress only; a task put in
            // progress without a percentage yet starts at 25%.
            var $pct = $(this).closest('td').find('select.afcdc-task__pct');
            if ($pct.length) {
                var on = this.value === '2';
                $pct.prop('hidden', !on);
                if (on && !$pct.val()) { $pct.val('25').trigger('change'); }
                else if (!on) { $pct.attr('data-afcdc-touched', '0'); }   // hidden: the server ignores it for any other status
                else { var pw = $pct.attr('data-afcdc-was'); $pct.attr('data-afcdc-touched', (pw !== undefined && $pct.val() === pw) ? '0' : '1'); }
            }
        });
        $newTasks.on('change', 'select.afcdc-task__pct', function () {
            var was = this.getAttribute('data-afcdc-was');
            this.setAttribute('data-afcdc-touched', (was !== null && this.value === was) ? '0' : '1');
        });
        // Enter in a row being TYPED adds the next one instead of submitting.
        // Only in those rows: in a saved task's box Enter means "I have fixed
        // this, save it", and adding a blank row there is never what was meant.
        $newTasks.on('keydown', 'tr.afcdc-new-task input', function (e) { if (e.key === 'Enter') { e.preventDefault(); $newTasks.find('[data-add-task]').trigger('click'); } });
    }
    // Delete, on the form (administrators): the list's confirm, but the list
    // is where to go afterwards - reloading a form whose activity is gone
    // would only say "not found". The code rides along so the list can say
    // what went.
    $('.afcdc-delete-activity').magnificPopup({
        type: 'inline', preloader: false, modal: true,
        callbacks: { open: function () {
            var t = $($.magnificPopup.instance.currItem.el[0]), content = $(this.content);
            content.off('click.afcdc').on('click.afcdc', '.modal-dismiss', function (e) { e.preventDefault(); $.magnificPopup.close(); });
            content.on('click.afcdc', '.modal-confirm', function (e) {
                e.preventDefault();
                var $btn = $(this).prop('disabled', true);
                $.ajax({
                    method: 'POST', url: lang_prefix + '/core/db_delete/pm_projects/' + t.data('id'),
                    data: { csrf: window.CSRF_TOKEN || '' }, dataType: 'json', cache: false,
                    success: function (r) {
                        // The list is where the notice lives, so that is where to
                        // go - with its search and filters when the form was
                        // opened from it; from anywhere else (a graph, Progress)
                        // the plain list. What went comes from the server's answer.
                        var after = String(t.data('afcdc-after') || '');
                        if (!/\/projects\/list(\?|$)/.test(after)) { after = lang_prefix + '/projects/list'; }
                        var gone = (r && r.data) || {};
                        window.location.href = after + (after.indexOf('?') >= 0 ? '&' : '?') + 'deleted=' + encodeURIComponent(t.data('code') || '')
                            + '&tasks=' + encodeURIComponent(gone.tasks || 0) + '&deliveries=' + encodeURIComponent(gone.deliveries || 0);
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false); $.magnificPopup.close();
                        alert(xhr.status === 403
                            ? 'The page had been open too long for the deletion to be accepted. Reload and try again.'
                            : 'The activity could not be deleted (' + xhr.status + '). Nothing was removed.');
                    }
                });
            });
        } }
    });
    // Nothing saved yet means no details panel to load, and an activity
    // reported through tasks has no panel at all any more - they are edited in
    // the form. The cascade below is wired up either way.
    if (project_id > 0 && $('#project_details').length) $.ajax({
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

/* After "Save and add another" the strip at the top says the goal, objective
 * and programme are filled in from the activity just saved. Once a person
 * moves one of those boxes that is no longer true, so the sentence goes;
 * "Saved ... (open it)" stays. A pick raises select2:select, Apply raises
 * afcdc:picked, and only a change with a browser event behind it is a
 * person's - the cascade rebuilding options in script is not. */
$(function () {
    if (!$('#afcdc-saved [data-afcdc-prefill]').length) { return; }
    var boxes = 'form.ecommerce-form select[name="pillar_id"], form.ecommerce-form select[name="objective_id"], form.ecommerce-form select[name="programme_id"]';
    $(document).on('select2:select afcdc:picked change', boxes, function (e) {
        if (e.type === 'change' && !e.originalEvent) { return; }
        $('#afcdc-saved [data-afcdc-prefill]').remove();
    });
});
