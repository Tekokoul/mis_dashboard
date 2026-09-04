/* Site-wide behaviour that used to live in inline attributes (onchange="…",
 * onclick="…", href="javascript:…"). The Content-Security-Policy allows
 * scripts only from this origin or with the page's nonce, so it lives here,
 * on every page (template.php loads this file last). */
$(function () {
    // List filters submit their form as soon as a value is picked.
    $(document).on('change', '[data-afcdc-autosubmit]', function () {
        if (this.form) { this.form.submit(); }
    });
    // "Print or save as PDF" on the overview.
    $(document).on('click', '.afcdc-print', function (e) {
        e.preventDefault();
        window.print();
    });
    // Repeater add/remove links were href="javascript:void(0)"; now "#".
    $(document).on('click', '.deleteElement, #addElement', function (e) {
        e.preventDefault();
    });
});

/* Abbreviation autofill on the add/edit forms of objectives, programmes and
 * activities: while the box is empty (or still holds a value this script
 * put there), it shows the next code for the place the item sits, and
 * follows the parent dropdown. Anything typed by hand is left alone; the
 * server fills an empty code the same way on save. */
$(function () {
    var $abbr = $('input[name="abbr"]'), $model = $('input[name="tablename"]');
    if (!$abbr.length || !$model.length) { return; }
    var model = $model.val();
    if (['pm_objectives', 'pm_programmes', 'pm_projects'].indexOf(model) < 0) { return; }
    var parentSel = model === 'pm_programmes' ? 'select[name="objective_id"]'
                  : model === 'pm_projects'   ? 'select[name="programme_id"]' : null;
    var prefix = (typeof lang_prefix === 'string') ? lang_prefix : '';
    function fill() {
        if ($abbr.val() !== '' && $abbr.attr('data-auto') !== '1') { return; }
        var parent = parentSel ? parseInt($(parentSel).val(), 10) || 0 : 0;
        if (parentSel && !parent) { return; }
        $.getJSON(prefix + '/core/next_code/' + model + '/' + parent, function (r) {
            var d = (r && r.data) ? r.data : r;
            if (!d || !d.code) { return; }
            $abbr.val(d.code).attr('data-auto', '1');
        });
    }
    $abbr.on('input', function () { $(this).attr('data-auto', $(this).val() === '' ? '1' : '0'); });
    if (parentSel) { $(document).on('change', parentSel, fill); }
    fill();
});
