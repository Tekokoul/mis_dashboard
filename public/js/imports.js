/* Import a work plan: the review page.
 *
 * Every decision is a POST with the page's CSRF token, then the page
 * reloads so the bands, the counts and the tabs are fresh - the same shape
 * as vetting on the lists. The objective box refills the programme box from
 * projects/get_programmes, as the activity form does. */
$(function () {
    var prefix = (typeof lang_prefix === 'string') ? lang_prefix : '';

    function fail(xhr) {
        var msg = 'That did not go through (' + xhr.status + ').';
        try { var j = JSON.parse(xhr.responseText); if (j && j.message) { msg = j.message; } } catch (e) {}
        if (xhr.status === 403) { msg = 'The page had been open too long. Reload and try again.'; }
        window.alert(msg);
    }

    function post(url, data) {
        data = data || {};
        data.csrf = window.CSRF_TOKEN || '';
        return $.ajax({ url: url, method: 'POST', data: data, dataType: 'json' });
    }

    function rowOf(el) { return $(el).closest('[data-import-row]'); }

    /* The objective and programme boxes live only in the page until that row's
     * own Accept is pressed. A reload after accepting one row would drop the
     * corrections made on every other row without saying so, and Escape would
     * do the same, so both ask first. */
    function editedRows(exceptId) {
        var ids = [];
        $('[data-import-row]').each(function () {
            var $r = $(this), id = $r.attr('data-import-row');
            if (id === String(exceptId)) { return; }
            $r.find('.afcdc-import__obj, .afcdc-import__prg').each(function () {
                var was = $(this).attr('data-afcdc-was') || '';
                if (String($(this).val() || '') !== String(was) && ids.indexOf(id) === -1) { ids.push(id); }
            });
            $r.find('.afcdc-import__desc-input').each(function () {
                if (this.value !== this.defaultValue && ids.indexOf(id) === -1) { ids.push(id); }
            });
        });
        return ids;
    }
    function confirmLosingEdits(exceptId) {
        var n = editedRows(exceptId).length;
        if (!n) { return true; }
        return window.confirm(n === 1
            ? 'One other row has a placement you changed but have not accepted. It will be put back as proposed. Continue?'
            : n + ' other rows have placements you changed but have not accepted. They will be put back as proposed. Continue?');
    }
    // Leaving the page the ordinary ways asks as well (Escape is handled by
    // the general back handler in custom.js, which fires a normal navigation).
    window.addEventListener('beforeunload', function (e) {
        if (window.afcdcImportSaving) { return; }
        if (!editedRows(0).length) { return; }
        e.preventDefault();
        e.returnValue = '';
    });

    $(document).on('click', '[data-import-action]', function (e) {
        e.preventDefault();
        var $a = $(this), action = $a.attr('data-import-action'), id = $a.attr('data-id');
        var data = {};
        if (action === 'accept') {
            var $row = rowOf(this);
            var $obj = $row.find('.afcdc-import__obj'), $prg = $row.find('.afcdc-import__prg');
            var $desc = $row.find('.afcdc-import__desc-input');
            if ($desc.length) {
                data.description = $desc.val() || '';
                if (!$.trim(data.description)) {
                    window.alert('This row needs a description before it can be created. The workbook gave none, so write one here.');
                    $desc.trigger('focus');
                    return;
                }
            }
            if ($obj.length) {
                data.objective_id = $obj.val() || '';
                data.programme_id = $prg.val() || '';
                if (!data.objective_id || !data.programme_id) {
                    var none = data.objective_id && $prg.find('option').length === 1 && !$prg.find('option[value!=""]').length;
                    window.alert(none
                        ? 'That objective has no programme yet. Add a programme to it first, or choose another objective.'
                        : 'Choose an objective and one of its programmes first.');
                    (data.objective_id ? $prg : $obj).trigger('focus');
                    return;
                }
            }
        }
        if (action === 'accept_all' && !window.confirm('Accept every agreed new activity and every plain update still pending?')) { return; }
        if (action === 'discard' && !window.confirm('Throw this import away? Nothing has been written from it.')) { return; }
        if (!confirmLosingEdits(action === 'discard' || action === 'accept_all' ? 0 : id)) { return; }
        window.afcdcImportSaving = true;
        $a.addClass('disabled').attr('aria-disabled', 'true');
        post(prefix + '/imports/' + action + '/' + id, data)
            .done(function (res) {
                // Rows that would not go through are the reason to read this.
                if (res && res.data && res.data.failed > 0 && res.message) { window.alert(res.message); }
                if (action === 'discard') {
                    window.location.href = prefix + '/imports/list?discarded=1';
                    return;
                }
                window.location.reload();
            })
            .fail(function (xhr) { window.afcdcImportSaving = false; $a.removeClass('disabled').removeAttr('aria-disabled'); fail(xhr); });
    });

    // The objective box refills the programme box; the proposal's programme
    // is kept when it belongs to the objective chosen.
    function refill($obj, wantProgramme) {
        var $row = rowOf($obj), $prg = $row.find('.afcdc-import__prg');
        var objective = $obj.val();
        var seq = (parseInt($prg.attr('data-seq') || '0', 10) + 1);
        $prg.attr('data-seq', seq).prop('disabled', true).html('<option value="">Loading…</option>');
        if (!objective) { $prg.prop('disabled', false).html('<option value="">Choose…</option>'); return; }
        $.ajax({ url: prefix + '/projects/get_programmes/' + encodeURIComponent(objective), method: 'GET', dataType: 'json' })
            .done(function (res) {
                if (parseInt($prg.attr('data-seq'), 10) !== seq) { return; }   // a later change won
                var list = (res && res.data) ? res.data : [];
                if (!list.length) {
                    // An objective with no programme cannot hold an activity;
                    // saying so beats an empty box and an unexplained refusal.
                    $prg.html('<option value="">No programme under this objective yet</option>').prop('disabled', false);
                    return;
                }
                var html = '<option value="">Choose…</option>';
                for (var i = 0; i < list.length; i++) {
                    html += '<option value="' + parseInt(list[i].id, 10) + '">' + $('<div>').text((list[i].abbr || '') + ' ' + (list[i].name || '')).html() + '</option>';
                }
                $prg.html(html).prop('disabled', false);
                if (wantProgramme && $prg.find('option[value="' + parseInt(wantProgramme, 10) + '"]').length) { $prg.val(String(parseInt(wantProgramme, 10))); }
                else if (list.length === 1) { $prg.val(String(parseInt(list[0].id, 10))); }
                $prg.trigger('change');
            })
            // The same guard as the success path: a request that fails after a
            // later one has already filled the box must not empty it again.
            .fail(function () {
                if (parseInt($prg.attr('data-seq'), 10) !== seq) { return; }
                $prg.html('<option value="">Could not load the programmes - pick the objective again</option>').prop('disabled', false);
            });
    }
    $(document).on('change', '.afcdc-import__obj', function () { refill($(this), 0); });

    // The number an activity gets comes from the programme it goes under, so
    // it is re-read whenever that box changes. The code shown on load counts
    // the other pending rows headed for the same programme; this one asks for
    // the next free code alone, and the real number is assigned on accept.
    function refreshCode($prg) {
        var $code = rowOf($prg).find('.afcdc-import__code-value');
        if (!$code.length) { return; }
        var programme = parseInt($prg.val(), 10);
        if (!programme) { $code.text('—'); return; }
        var seq = (parseInt($code.attr('data-seq') || '0', 10) + 1);
        $code.attr('data-seq', seq).text('…');
        $.ajax({ url: prefix + '/core/next_code/pm_projects/' + programme, method: 'GET', dataType: 'json' })
            .done(function (res) {
                if (parseInt($code.attr('data-seq'), 10) !== seq) { return; }
                $code.text((res && res.data && res.data.code) ? res.data.code : '—');
            })
            .fail(function () {
                if (parseInt($code.attr('data-seq'), 10) !== seq) { return; }
                $code.text('—');
            });
    }
    $(document).on('change', '.afcdc-import__prg', function () { refreshCode($(this)); });

    // "Use the wording's pick": the boxes jump to the alternative.
    $(document).on('click', '[data-import-pick]', function (e) {
        e.preventDefault();
        var $row = rowOf(this), $obj = $row.find('.afcdc-import__obj');
        if (!$obj.length) { return; }
        $obj.val(String(parseInt($(this).attr('data-objective'), 10)));
        refill($obj, parseInt($(this).attr('data-programme'), 10));
    });
});
