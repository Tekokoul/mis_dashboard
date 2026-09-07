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

/* Filing by content. As the name and description of a new objective,
 * programme or activity are typed, the form asks the server where an item
 * with those words belongs (core/suggest_parent; suggest_parent() in
 * library.php scores the text against every place, including what is
 * already filed there) and sets the goal, objective and programme dropdowns
 * to the best match, saying so under the dropdown with the runners-up as
 * links. The code then follows the place chosen (the block above). A choice
 * made by hand in a dropdown wins: the form stops re-filing after that and
 * only suggests. On the edit form nothing moves by itself, because the item
 * already sits somewhere on purpose: the suggestion is shown with Apply. */
$(function () {
    var parentOf = { pm_objectives: 'pillar_id', pm_programmes: 'objective_id', pm_projects: 'programme_id' };
    var $model = $('input[name="tablename"]'), model = $model.val();
    if (!$model.length || !parentOf[model]) { return; }
    var $form   = $model.closest('form');
    var $fields = $form.find('input[name="name"], textarea[name="description"], textarea[name="kpi"]');
    var $parent = $form.find('select[name="' + parentOf[model] + '"]');
    if (!$fields.length || !$parent.length) { return; }
    var isAdd  = /add_update$/.test($form.attr('action') || '');
    var rowId  = isAdd ? 0 : (parseInt((window.location.pathname.match(/\/(\d+)\/?$/) || [])[1], 10) || 0);
    var prefix = (typeof lang_prefix === 'string') ? lang_prefix : '';
    // At the end of the field's column: select2 has already put its widget
    // right after the select, and the note belongs under both.
    var $hint  = $('<div class="afcdc-suggest" aria-live="polite"></div>').appendTo($parent.parent());
    // What the form was suggesting when Save is pressed. The server compares
    // it with what was actually chosen and remembers the difference, so the
    // matcher learns from corrections. Empty when nothing was suggested.
    var $sug = {};
    $.each(['pillar_id', 'objective_id', 'programme_id'], function (i, f) {
        $sug[f] = $('<input type="hidden">').attr('name', 'suggest_' + f).appendTo($form);
    });
    function remember(c) {
        $.each(['pillar_id', 'objective_id', 'programme_id'], function (i, f) {
            $sug[f].val(c && c[f] !== undefined ? String(c[f]) : '');
        });
    }
    var manual = false, auto = false, timer = null, lastText = '', last = null, seq = 0;

    function value(name) { var $s = $form.find('select[name="' + name + '"]'); return $s.length ? String($s.val()) : ''; }
    function key(c) {
        return model === 'pm_objectives' ? String(c.pillar_id)
             : model === 'pm_programmes' ? String(c.objective_id)
             : String(c.objective_id) + '/' + String(c.programme_id);
    }
    function selectedKey() {
        return model === 'pm_objectives' ? value('pillar_id')
             : model === 'pm_programmes' ? value('objective_id')
             : value('objective_id') + '/' + value('programme_id');
    }
    function setSelect(name, v) {
        var $s = $form.find('select[name="' + name + '"]');
        if (!$s.length || String($s.val()) === String(v)) { return false; }
        // A dropdown narrowed by a "Goal" box may be hiding this value: ask
        // the cascade to widen to its goal first, or the select would be
        // left with nothing chosen and the save would store a blank.
        if ($s.is('[data-afcdc-cascade-child]') && !$s.find('option[value="' + String(v) + '"]').length) {
            $s.trigger('afcdc:set', [String(v)]);
        }
        $s.val(String(v)).trigger('change');
        return true;
    }
    function apply(c) {
        if (model === 'pm_objectives') { setSelect('pillar_id', c.pillar_id); return; }
        if (model === 'pm_programmes') { setSelect('objective_id', c.objective_id); return; }
        // Goal -> objective -> programme through the cascade in pm_projects.js,
        // which keeps these two when it reloads the options beneath a change.
        window.afcdcPreselect = { objective_id: String(c.objective_id), programme_id: String(c.programme_id) };
        if (setSelect('pillar_id', c.pillar_id)) { return; }
        if (setSelect('objective_id', c.objective_id)) { return; }
        delete window.afcdcPreselect;
        setSelect('programme_id', c.programme_id);
    }
    function link(text, onClick) {
        // Runner-up labels can run long (two full names); show the start, keep the whole in the tooltip.
        var short = text.length > 72 ? text.substr(0, 70).replace(/\s+\S*$/, '') + '\u2026' : text;
        return $('<a href="#"></a>').text(short).attr('title', text).on('click', function (e) { e.preventDefault(); onClick(); });
    }
    function render() {
        $hint.empty();
        var list = (last && last.candidates) || [];
        if (!list.length) { return; }
        var selected = selectedKey(), current = null, others = [];
        $.each(list, function (i, c) { if (!current && key(c) === selected) { current = c; } else { others.push(c); } });
        if (current) {
            var lead = current.learned ? 'Filed here before for wording like this: '
                     : (auto && !manual) ? (last.confident ? 'Filed under ' : 'Best guess from the wording: ') : '';
            var tail = current.learned ? '' : ((auto && !manual) ? ' from the wording.' : ' matches the wording.');
            $hint.append($('<span></span>').text(lead))
                 .append($('<strong></strong>').text(current.label))
                 .append($('<span></span>').text(tail));
        } else {
            var best = others.shift();
            $hint.append($('<span></span>').text(best.learned ? 'Filed here before for wording like this: ' : 'Suggested from the wording: '))
                 .append($('<strong></strong>').text(best.label)).append(' ')
                 .append(link('Apply', function () { manual = true; auto = false; apply(best); render(); }));
        }
        if (others.length) {
            var $alt = $('<span class="afcdc-suggest__alt"></span>').text(current ? ' Not right? ' : ' Or: ');
            $.each(others, function (i, c) {
                if (i) { $alt.append(' \u00b7 '); }
                $alt.append(link(c.label, function () { manual = true; auto = false; apply(c); render(); }));
            });
            $hint.append($alt);
        }
    }
    function ask() {
        var parts = [];
        $fields.each(function () { parts.push($.trim($(this).val())); });
        var text = $.trim(parts.join('. '));
        if (text === lastText) { return; }
        lastText = text;
        if (text.length < 4) { last = null; render(); return; }
        var mine = ++seq;
        $.getJSON(prefix + '/core/suggest_parent/' + model, { text: text.substr(0, 4000), exclude: rowId }, function (r) {
            if (mine !== seq) { return; }
            last = (r && r.data) ? r.data : r;
            var best = last && last.candidates && last.candidates[0];
            remember(best);
            if (best && isAdd && !manual) { auto = true; apply(best); }
            render();
        });
    }
    $fields.on('input change', function () { clearTimeout(timer); timer = setTimeout(ask, 600); });
    // A pick made by a person: select2 raises select2:select only for one,
    // and a plain dropdown's change carries the browser event.
    $form.on('select2:select', 'select[name="pillar_id"], select[name="objective_id"], select[name="programme_id"], select[data-afcdc-cascade-parent]', function () { manual = true; });
    $form.on('change', 'select[name="pillar_id"], select[name="objective_id"], select[name="programme_id"], select[data-afcdc-cascade-parent]', function (e) { if (e.originalEvent) { manual = true; } });
    // Re-read the note whenever the place settles (the cascade ends here).
    $parent.on('change', function () { if (last) { render(); } });
});

/* Cascading dropdowns.
 *
 * FORMS: a dropdown may carry a "narrow by" box above it (form_builder
 * createCascadeSelector): the programme form has no goal column, but
 * choosing a goal first cuts seventeen objectives to the few beneath it. The
 * box never posts; every real option carries data-parent, and this hides the
 * ones that do not belong. On an edit form the box is set from the option
 * already chosen.
 *
 * LIST FILTERS: a filter may name the filter above it (data-afcdc-narrow-by).
 * Its options carry data-parent too, so with a goal chosen the programme box
 * lists only that goal's programmes - and when the goal changes, the
 * programme choice is cleared before the form submits, or the page would
 * show nothing at all. */
$(function () {
    $('select[data-afcdc-cascade-parent]').each(function () {
        var $parent = $(this);
        var $child  = $('select[data-afcdc-cascade-child="' + $parent.attr('data-afcdc-cascade-parent') + '"]');
        if (!$child.length) { return; }
        // The ORIGINAL option elements, kept for the life of the page and
        // re-attached as needed. Clones would break select2, whose cache
        // still points at the originals.
        var all = $child.children('option').toArray();
        var wasRequired = $child.prop('required');
        function narrow(want, silent) {
            var p = String($parent.val() || '');
            var was = String($child.val() || '');
            $child.empty();
            all.forEach(function (o) {
                var op = o.getAttribute('data-parent');
                // An option with no parent (a "None" placeholder) belongs everywhere.
                if (!p || op === null || op === '' || String(op) === p) { $child.append(o); }
            });
            if (!$child.children('option').length) {
                // A goal with nothing under it: say so, and do not let the
                // form save a blank objective by accident.
                $child.append($('<option value="">No objectives under this goal</option>'));
                $child.prop('required', true);
            } else {
                $child.prop('required', wasRequired);
            }
            var pick = (want !== undefined && $child.find('option[value="' + String(want) + '"]').length) ? String(want)
                     : ($child.find('option[value="' + was + '"]').length ? was : String($child.children('option').first().val()));
            $child.val(pick);
            if (silent) { return; }
            // A real change only when the value moved; otherwise just redraw
            // the widget, or the code box would be fetched twice on load.
            if (pick !== was) { $child.trigger('change'); } else { $child.trigger('change.select2'); }
        }
        // Preset the box from a SAVED selection only (the server marks it
        // with a selected attribute); a browser-default first option must
        // not narrow a fresh form to one goal.
        var saved = $child.find('option[selected]').attr('data-parent');
        if (saved) { $parent.val(String(saved)).trigger('change.select2'); }
        narrow(undefined, false);
        $parent.on('change', function () { narrow(undefined, false); });
        // Something else (the filing suggestion, an Apply link) wants a value
        // that the current narrowing hides: widen to its goal first.
        $child.on('afcdc:set', function (e, v) {
            var o = all.filter(function (el) { return String(el.value) === String(v); })[0];
            if (!o) { return; }
            var p = o.getAttribute('data-parent') || '';
            if (p && String($parent.val() || '') !== p) { $parent.val(p).trigger('change.select2'); }
            narrow(String(v), true);
        });
    });

    $('select[data-afcdc-narrow-by]').each(function () {
        var $child  = $(this);
        var $parent = $('select[name="' + $child.attr('data-afcdc-narrow-by') + '"]');
        if (!$parent.length) { return; }
        var p = String($parent.val() || '');
        if (p && p !== '%') {
            $child.find('option[data-parent]').each(function () {
                // The option in force stays even if it disagrees with the
                // parent (a stale link), so the box always shows what the
                // list is actually filtered by.
                if (!this.selected && String($(this).attr('data-parent')) !== p) { $(this).remove(); }
            });
        }
        // Bound directly, so it runs before the document-level autosubmit.
        $parent.on('change', function () { $child.val('%'); });
    });
});

/* The sticky list toolbar needs to know how tall the fixed page chrome is,
 * and whether it is currently pinned (for its shadow). Both are measured
 * rather than assumed: the header's height differs by breakpoint and theme. */
$(function () {
    var bar = document.querySelector('.datatable-header.afcdc-sticky');
    if (!bar) { return; }
    var ph = document.querySelector('.page-header');
    function place() {
        if (ph && getComputedStyle(ph).position === 'fixed') {
            bar.style.setProperty('--afcdc-sticky-top', Math.round(ph.getBoundingClientRect().bottom) + 'px');
        } else {
            bar.style.removeProperty('--afcdc-sticky-top');
        }
    }
    function shadow() {
        // One rect read per scroll event is cheap; deferring it a frame made
        // the shadow lag the pin by one scroll step.
        var top = parseFloat(getComputedStyle(bar).top) || 0;
        // document.scrollingElement, not window.scrollY: this theme scrolls the html element.
        var scrolled = (document.scrollingElement || document.documentElement).scrollTop > 0;
        var stuck = getComputedStyle(bar).position === 'sticky' && Math.round(bar.getBoundingClientRect().top) <= Math.round(top) + 1 && scrolled;
        bar.classList.toggle('is-stuck', stuck);
    }
    place(); shadow();
    window.addEventListener('resize', function () { place(); shadow(); });
    window.addEventListener('scroll', shadow, { passive: true });
});

