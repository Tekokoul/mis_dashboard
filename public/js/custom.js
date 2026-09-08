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
    // A plain numeric code that came from the database ("1.8.1") counts as
    // automatic as well: when the item is moved to another parent on the
    // edit form the code follows, and moving it back restores the code it
    // had. Anything typed by hand in this session is left alone.
    var typed = false, origParent = parentSel ? $(parentSel).val() : null, origCode = $abbr.val(), seq = 0;
    var $form = $abbr.closest('form');
    function pending(on) { $abbr.attr('data-afcdc-code-pending', on ? '1' : null); if (!on) { $form.trigger('afcdc:code'); } }
    function fill(moved) {
        var mine = ++seq;   // anything older in flight may no longer touch the box
        var v = $abbr.val();
        var automatic = v === '' || $abbr.attr('data-auto') === '1' || (moved && !typed && /^\d+(\.\d+)+$/.test(v));
        if (!automatic) { pending(false); return; }
        var parent = parentSel ? parseInt($(parentSel).val(), 10) || 0 : 0;
        if (parentSel && !parent) { pending(false); return; }
        if (moved && !typed && origCode !== '' && String(parent) === String(origParent)) { $abbr.val(origCode).attr('data-auto', '1'); pending(false); return; }
        pending(true);
        $.getJSON(prefix + '/core/next_code/' + model + '/' + parent, function (r) {
            if (mine !== seq || typed) { return; }
            if (parentSel && String(parseInt($(parentSel).val(), 10) || 0) !== String(parent)) { return; }
            var d = (r && r.data) ? r.data : r;
            if (!d || !d.code) { return; }
            $abbr.val(d.code).attr('data-auto', '1');
        }).always(function () { if (mine === seq) { pending(false); } });
    }
    $abbr.on('input', function () { seq++; typed = $(this).val() !== ''; $(this).attr('data-auto', typed ? '0' : '1'); pending(false); });
    if (parentSel) { $(document).on('change', parentSel, function () { fill(true); }); }
    fill(false);
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
    var manual = false, auto = false, timer = null, lastText = '', last = null, seq = 0, inflight = false, pendingSubmit = null, pendingSubmitter = null, bypass = false, settling = false, deferred = null;
    // Opened from a parent's "Add a ..." button: that parent is a choice a
    // person made, so the wording only suggests here - it never moves the
    // boxes. The suggestion is still asked for and still posted, which is
    // how the placement teaches the guesser when the two disagree.
    if ($form.find('input[name="filed_from_parent"]').length) { manual = true; }
    // The three boxes wear gold while what they show came from the wording
    // and nobody has touched them; a pick by hand takes it off.
    function markSuggested(on) {
        $.each(['pillar_id', 'objective_id', 'programme_id'], function (i, f) {
            var $g = $form.find('select[name="' + f + '"]').closest('.form-group');
            $g.toggleClass('afcdc-field--suggested', on);
            if (on && !$g.find('.afcdc-field__suggested').length) { $('<div class="afcdc-field__suggested">Suggested from the wording; change it if it is wrong.</div>').appendTo($g.children().last()); }
            if (!on) { $g.find('.afcdc-field__suggested').remove(); }
        });
    }

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
        if (manual) { $s.trigger('afcdc:picked'); }   // an Apply or a runner-up link: a choice, and Esc should ask about it
        return true;
    }
    // Returns true when a cascade was started (options are being reloaded
    // beneath a change) and the place is not settled yet.
    function apply(c) {
        if (model === 'pm_objectives') { setSelect('pillar_id', c.pillar_id); return false; }
        if (model === 'pm_programmes') { setSelect('objective_id', c.objective_id); return false; }
        // Goal -> objective -> programme through the cascade in pm_projects.js,
        // which keeps these two when it reloads the options beneath a change.
        window.afcdcPreselect = { objective_id: String(c.objective_id), programme_id: String(c.programme_id) };
        if (setSelect('pillar_id', c.pillar_id)) { return true; }
        if (setSelect('objective_id', c.objective_id)) { return true; }
        var $p = $form.find('select[name="programme_id"]');
        // The preselect is only spent once the option is there; a programme
        // list still loading keeps it and picks it up when it arrives.
        if ($p.find('option[value="' + String(c.programme_id) + '"]').length) { delete window.afcdcPreselect; setSelect('programme_id', c.programme_id); return false; }
        return true;
    }
    function link(text, onClick) {
        // Runner-up labels can run long (two full names); show the start, keep the whole in the tooltip.
        var short = text.length > 72 ? text.substr(0, 70).replace(/\s+\S*$/, '') + '\u2026' : text;
        return $('<a href="#"></a>').text(short).attr('title', text).on('click', function (e) { e.preventDefault(); onClick(); });
    }
    function render() {
        $hint.empty();
        var list = (last && last.candidates) || [];
        if (!list.length) { markSuggested(false); return; }
        var selected = selectedKey(), current = null, others = [];
        $.each(list, function (i, c) { if (!current && key(c) === selected) { current = c; } else { others.push(c); } });
        markSuggested(model === 'pm_projects' && isAdd && auto && !manual && current !== null);
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
    function currentText() {
        var parts = [];
        $fields.each(function () { parts.push($.trim($(this).val())); });
        return $.trim(parts.join('. '));
    }
    // A save held back until the suggestion matches the final wording goes
    // through here. requestSubmit() re-runs the browser's own checks; the
    // submit handler below lets it pass once.
    function codePending() { return $form.find('input[name="abbr"][data-afcdc-code-pending="1"]').length > 0; }
    function flush() {
        if (!pendingSubmit || inflight || settling || codePending()) { return; }
        var f = pendingSubmit, sub = pendingSubmitter;
        pendingSubmit = null; pendingSubmitter = null;
        bypass = true;
        // Through the button that was pressed: "Save and add a programme"
        // carries a name and a value, and a plain requestSubmit() drops them.
        if (typeof f.requestSubmit === 'function') {
            if (sub && f.contains(sub)) { f.requestSubmit(sub); } else { f.requestSubmit(); }
        } else { f.submit(); }
        bypass = false;
    }
    function ask() {
        var text = currentText();
        if (text === lastText) { flush(); return; }
        lastText = text;
        if (text.length < 4) { last = null; render(); flush(); return; }
        var mine = ++seq;
        inflight = true;
        $.getJSON(prefix + '/core/suggest_parent/' + model, { text: text.substr(0, 4000), exclude: rowId }, function (r) {
            if (mine !== seq) { return; }
            last = (r && r.data) ? r.data : r;
            var best = last && last.candidates && last.candidates[0];
            remember(best);
            if (best && isAdd && !manual) {
                auto = true;
                // Not while a dropdown is open under the person's hand: the
                // cascade would empty the list they are choosing from.
                var $auto = $form.find('select[data-afcdc-auto-open]');
                if ($auto.length) { $auto.select2('close'); }
                if ($('.select2-container--open').length) { deferred = best; } else { deferred = null; settling = apply(best); }
            }
            render();
        }).always(function () { if (mine === seq) { inflight = false; flush(); } });
    }
    $form.on('select2:close', 'select', function () {
        if (deferred && isAdd && !manual) { var c = deferred; deferred = null; window.setTimeout(function () { settling = apply(c); render(); }, 0); }
    });
    $fields.on('input change', function () { clearTimeout(timer); timer = setTimeout(ask, 600); });
    // Leaving a text box asks at once, and Save waits for the answer (at
    // most two seconds): what is recorded as "suggested" is what was shown
    // for the wording that was saved, which is what the learning relies on.
    $fields.on('blur', function () { clearTimeout(timer); ask(); });
    $form.on('submit', function (e) {
        if (bypass) { return; }
        if (currentText() === lastText && !inflight && !settling && !codePending()) { return; }
        e.preventDefault();
        pendingSubmit = this;
        pendingSubmitter = (e.originalEvent && e.originalEvent.submitter) || null;
        clearTimeout(timer);
        ask();
        var gen = seq;
        window.setTimeout(function () {
            if (gen === seq) { inflight = false; }
            settling = false;
            $form.find('input[name="abbr"]').removeAttr('data-afcdc-code-pending');   // a code request that never answers must not hold Save for ever
            flush();
        }, 5000);
    });
    // The cascade ends at the programme box, and the code box says when its
    // number has arrived: a held save goes on from either.
    $parent.on('change', function () { settling = false; flush(); });
    $form.on('afcdc:code', function () { flush(); });
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

/* Vetting the re-filed activities: Accept / Undo on a row, or Accept all.
 * Each is a POST with the page's CSRF token; the page reloads so the bands
 * and the pending count are fresh. */
$(function () {
    $(document).on('click', '[data-review-action]', function (e) {
        e.preventDefault();
        var action = $(this).attr('data-review-action'), id = $(this).attr('data-id');
        if (action === 'accept_all' && !window.confirm('Accept every move still pending?')) { return; }
        var prefix = (typeof lang_prefix === 'string') ? lang_prefix : '';
        var url = prefix + '/projects/allocation_' + action + (id ? '/' + id : '');
        $.ajax({ url: url, method: 'POST', data: { csrf: window.CSRF_TOKEN || '' }, dataType: 'json' })
            .done(function () { window.location.reload(); })
            .fail(function (xhr) { window.alert(xhr.status === 403 ? 'The page had been open too long. Reload and try again.' : 'That did not go through (' + xhr.status + ').'); });
    });
});


/* Required fields on the activity form. The browser's own check cannot show
 * itself on a select2 box: the real <select> is hidden, so "an invalid form
 * control is not focusable" is all that happens and the click does nothing.
 * This marks every missing field, names what is wrong, and opens the first
 * one. The server refuses the save as well (projectsController). */
$(function () {
    var pending = null;
    document.addEventListener('invalid', function (e) {
        var field = e.target;
        if (!field || !field.form || !$(field.form).hasClass('ecommerce-form')) { return; }
        e.preventDefault();
        // Most fields are wrapped by the form builder; the task rows on the
        // activity form are bare inputs in a table cell. Without this fallback
        // the mark went nowhere, the message was appended to an empty set, and
        // the scroll below threw on undefined - which, because preventDefault
        // above has already suppressed the browser's own bubble, left the Save
        // button doing nothing at all with no explanation.
        var $group = $(field).closest('.form-group');
        var grouped = $group.length > 0;
        if (!grouped) { $group = $(field).closest('td, .afcdc-field-wrap'); }
        var why = (field.tagName === 'SELECT' && !field.options.length) ? 'Nothing to choose from yet' : 'Required';
        $group.addClass('afcdc-field--missing');
        var $msg = $group.find('.afcdc-field__msg');
        if (!$msg.length && $group.length) { $msg = $('<div class="afcdc-field__msg" role="alert"></div>').appendTo(grouped ? $group.children().last() : $group); }
        $msg.text(why);
        if (!pending) {
            pending = field;
            window.setTimeout(function () {
                var first = pending; pending = null;
                var $scroll = $(first).closest('.form-group');
                ($scroll[0] || first).scrollIntoView({ block: 'center', behavior: 'smooth' });
                // A dropdown opened here is the form's doing, not the person's: a
                // suggestion that lands meanwhile may still fill the boxes.
                if ($(first).data('select2')) { $(first).attr('data-afcdc-auto-open', '1').one('select2:close', function () { $(this).removeAttr('data-afcdc-auto-open'); }).select2('open'); } else { first.focus(); }
            }, 0);
        }
    }, true);
    // The mark goes as soon as the field is filled.
    $(document).on('input change', 'form.ecommerce-form [required]', function () {
        if (this.value !== '' && this.value !== null) { $(this).closest('.form-group, td, .afcdc-field-wrap').first().removeClass('afcdc-field--missing').find('.afcdc-field__msg').remove(); }
    });
});

/* Escape leaves full screen, and that is the whole job of that press: the
 * page must not step back as well. A page put into full screen through the
 * Fullscreen API says so, but one the person put there with F11 or the green
 * button does not - and its window can still show the browser's toolbar, so
 * measuring the screen misses it. What always happens is the window
 * resizing, so the move is scheduled a moment ahead and dropped if the
 * window changes size first. The wait is short enough not to be felt. */
function afcdcFullScreen() {
    // A page put into full screen through the Fullscreen API says so.
    if (document.fullscreenElement || document.webkitFullscreenElement) { return true; }
    // A window the person put there with F11, or the green button on a Mac,
    // does not - but the browser reports its display mode, which is the one
    // signal that holds however full screen was entered. Measuring the window
    // against the screen does not: in full screen on a Mac the toolbar is
    // still shown, so the window is never quite the height of the screen.
    if (window.matchMedia) {
        var q = window.matchMedia('(display-mode: fullscreen)');
        if (q && q.media !== 'not all' && q.matches) { return true; }
    }
    // Last resort where the display mode is unknown: the window covering the
    // whole screen, chrome included (outerHeight is 0 in some embedded views).
    var sc = window.screen;
    return !!(sc && sc.height && window.outerHeight
        && window.outerHeight >= sc.height - 2 && window.outerWidth >= sc.width - 2);
}

function afcdcEscapeGo(go) {
    var cancelled = false;
    function cancel() { cancelled = true; }
    window.addEventListener('resize', cancel);
    document.addEventListener('fullscreenchange', cancel);
    document.addEventListener('webkitfullscreenchange', cancel);
    window.setTimeout(function () {
        window.removeEventListener('resize', cancel);
        document.removeEventListener('fullscreenchange', cancel);
        document.removeEventListener('webkitfullscreenchange', cancel);
        if (!cancelled) { go(); }
    }, 260);
}

/* Esc leaves the activity form the way the Back button does - to the list
 * it was opened from. Not while a dropdown or a dialog is open (they take
 * Esc themselves), and not without asking when something typed is unsaved. */
$(function () {
    // A goal / objective / programme moved by a person, as opposed to by the
    // cascade or a suggestion: only a real event carries originalEvent, and
    // select2 raises select2:select for a pick alone.
    var placement = 'form.ecommerce-form select[name="pillar_id"], form.ecommerce-form select[name="objective_id"], form.ecommerce-form select[name="programme_id"]';
    // What each box held when the page arrived, so putting one back where it
    // started stops counting as a change.
    $(placement).each(function () { $(this).attr('data-afcdc-was', this.value); });
    function afcdcMark(el) {
        var was = $(el).attr('data-afcdc-was');
        $(el).attr('data-afcdc-touched', (was !== undefined && String(el.value) === String(was)) ? '0' : '1');
    }
    $(document).on('select2:select', placement, function () { afcdcMark(this); });
    $(document).on('change', placement, function (e) { if (e.originalEvent) { afcdcMark(this); } });
    // "Apply" and the runner-up links move the boxes in script, so they carry
    // no browser event; they are a person's choice all the same.
    $(document).on('afcdc:picked', placement, function () { afcdcMark(this); });
    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape' || e.isDefaultPrevented()) { return; }
        // The marked Back link, or any editing form's own Back button: an
        // edit must never be dropped without asking, whichever form it is.
        var $back = $('a[data-afcdc-back]').first();
        if (!$back.length) { $back = $('form.ecommerce-form a.cancel-button').first(); }
        if (!$back.length || !$back.attr('href')) { return; }
        if ($('.select2-container--open').length) { return; }
        if (window.jQuery && $.magnificPopup && $.magnificPopup.instance && $.magnificPopup.instance.isOpen) { return; }
        var dirty = false;
        $('form.ecommerce-form').find('input[type="text"], input:not([type]), textarea').each(function () {
            if (this.name === 'abbr' && this.getAttribute('data-auto') === '1') { return; }   // filled by the form, not typed
            if (this.value !== this.defaultValue) { dirty = true; }
        });
        // A task struck through for removal is a decision, not a keystroke: it
        // has no typed text to compare, so it has to be looked for on its own.
        // Not against defaultValue - for a hidden input the value property is
        // the attribute itself, so the two are never different and this test
        // silently passed everything.
        $('form.ecommerce-form').find('input[name$="[remove]"]').each(function () {
            var was = this.getAttribute('data-afcdc-was');
            if (this.value !== (was === null ? '0' : was)) { dirty = true; }
        });
        // Not option[selected]: the cascade rebuilds these options in script,
        // so none carries the attribute and a re-filing was read as "nothing
        // changed". A box the person moved themselves is marked instead.
        if ($('form.ecommerce-form').find('select[data-afcdc-touched="1"]').length) { dirty = true; }
        if (dirty && !window.confirm('Leave without saving your changes?')) { return; }
        // Escape is the browser's "stop loading" as well, so the move waits
        // until the key has been dealt with: navigating inside the handler
        // could have its own load aborted a moment later.
        if (afcdcFullScreen()) { return; }   // this press is leaving full screen; one thing per key
        e.preventDefault();
        var to = $back.attr('href');
        afcdcEscapeGo(function () { window.location.href = to; });
    });
});

/* Esc anywhere else in the dashboard goes back one step: the page you came
 * from when that was a page of this site, otherwise the parent named in the
 * breadcrumb (Overview > Objective > Programme). Forms are handled above,
 * and anything that uses Esc for itself - a dropdown, a dialog, the search
 * suggestions, the go-to-page box - stops the key before it reaches here. */
$(function () {
    function sameSite(url) {
        if (!url) { return false; }
        var a = document.createElement('a');
        a.href = url;
        return a.protocol === window.location.protocol && a.host === window.location.host;
    }
    function upLink() {
        // The breadcrumb's last link is the level above; the last one in the
        // header is the page itself on a list, which is not a step back.
        var $links = $('header.page-header').find('h2 a, .breadcrumbs a');
        return $links.length ? $links.last().attr('href') : '';
    }
    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape' || e.isDefaultPrevented()) { return; }
        if ($('a[data-afcdc-back]').length || $('form.ecommerce-form a.cancel-button').length) { return; }   // a form: handled above, with its unsaved check
        if ($('.select2-container--open, .afcdc-jump__box').length) { return; }
        if (window.jQuery && $.magnificPopup && $.magnificPopup.instance && $.magnificPopup.instance.isOpen) { return; }
        var tag = (document.activeElement && document.activeElement.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') { return; }
        // In full screen this press is what leaves it: that is the whole
        // action, and stepping back as well would lose the page too.
        if (afcdcFullScreen()) { return; }
        if (sameSite(document.referrer)) { e.preventDefault(); afcdcEscapeGo(function () { window.history.back(); }); return; }
        var up = upLink();
        if (up) { e.preventDefault(); afcdcEscapeGo(function () { window.location.href = up; }); }
    });
});

/* On the activity edit form, a goal / objective / programme moved away from
 * what the row holds - the AI's proposal while one is pending - turns yellow
 * before anything is saved, so a correction is visible as a correction.
 * Choosing the original value again clears it. */
$(function () {
    if (typeof project_id === 'undefined' || !(project_id > 0)) { return; }
    var $form = $('form.ecommerce-form');
    if (!$form.length) { return; }
    var names = ['pillar_id', 'objective_id', 'programme_id'], base = {};
    names.forEach(function (n) { var el = $form.find('select[name="' + n + '"]')[0]; if (el) { base[n] = el.value; } });
    var why = $('.afcdc-review-panel').length ? 'Changed from what was proposed. Saving keeps your choice.' : 'Changed. Saving moves the activity.';
    function mark() {
        names.forEach(function (n) {
            var $el = $form.find('select[name="' + n + '"]');
            if (!$el.length || base[n] === undefined) { return; }
            var changed = String($el.val()) !== String(base[n]);
            var $g = $el.closest('.form-group');
            $g.toggleClass('afcdc-field--changed', changed);
            if (changed && !$g.find('.afcdc-field__changed').length) { $('<div class="afcdc-field__changed"></div>').text(why).appendTo($g.children().last()); }
            if (!changed) { $g.find('.afcdc-field__changed').remove(); }
        });
    }
    $form.on('change', 'select[name="pillar_id"], select[name="objective_id"], select[name="programme_id"]', function () { window.setTimeout(mark, 0); });
});

/* Under the programme box on the activity form: what the programme is for
 * and what already sits there (projects/programme_context). A placement is
 * judged far better by its neighbours than by a title. */
$(function () {
    var $p = $('form.ecommerce-form input[name="tablename"][value="pm_projects"]').closest('form').find('select[name="programme_id"]');
    if (!$p.length) { return; }
    var prefix = (typeof lang_prefix === 'string') ? lang_prefix : '';
    var rowId = (typeof project_id === 'number') ? project_id : 0;
    var $box = $('<div class="afcdc-neighbours" aria-live="polite"></div>').appendTo($p.parent());
    var seq = 0;
    function show() {
        var id = parseInt($p.val(), 10) || 0, mine = ++seq;
        if (!id) { $box.empty(); return; }
        $.getJSON(prefix + '/projects/programme_context/' + id, { exclude: rowId }, function (r) {
            if (mine !== seq) { return; }
            var d = (r && r.data) ? r.data : r;
            if (!d || !d.label) { $box.empty(); return; }
            $box.empty();
            if (d.description) { $box.append($('<p class="afcdc-neighbours__what"></p>').text(d.description)); }
            var $l = $('<div class="afcdc-neighbours__list"></div>');
            if (d.activities && d.activities.length) {
                $l.append($('<span class="afcdc-neighbours__lead"></span>').text('Already here (' + d.count + '): '));
                $.each(d.activities, function (i, a) {
                    if (i) { $l.append(', '); }
                    $l.append($('<span class="afcdc-code"></span>').text(a.abbr)).append(document.createTextNode(' ' + a.name));
                });
                if (d.count > d.activities.length) { $l.append(' …'); }
            } else {
                $l.text('Nothing filed here yet.');
            }
            $box.append($l);
        });
    }
    $p.on('change', show);
    show();
});

/* The "…" between page numbers opens a small box to type a page number.
 * On the server-paged lists the link carries the page URL with __PAGE__
 * where the number goes; on a DataTables table (Per Project) the box turns
 * the table's own page instead. */
$(function () {
    function close() { $('.afcdc-jump__box').remove(); $('.afcdc-jump').removeClass('is-open'); }
    function openBox($host, last, page, go) {
        if ($host.hasClass('is-open')) { close(); return; }
        close();
        var $box = $('<form class="afcdc-jump__box" role="dialog" aria-label="Go to a page"></form>');
        var $in = $('<input type="number" class="form-control form-control-sm" min="1" max="' + last + '" required>').val(page);
        $box.append($('<label></label>').text('Go to page ').append($in))
            .append($('<span class="afcdc-jump__of"></span>').text(' of ' + last + ' '))
            .append('<button type="submit" class="btn btn-sm btn-primary">Go</button>');
        $box.on('submit', function (ev) {
            ev.preventDefault();
            go(Math.min(last, Math.max(1, parseInt($in.val(), 10) || 1)));
        });
        $host.addClass('afcdc-jump is-open').append($box);
        $in.trigger('focus').trigger('select');
    }
    $(document).on('click', '[data-afcdc-jump]', function (e) {
        e.preventDefault();
        var $a = $(this);
        openBox($a.closest('li'), parseInt($a.attr('data-afcdc-last'), 10) || 1, parseInt($a.attr('data-afcdc-page'), 10) || 1, function (n) {
            window.location.href = $a.attr('data-afcdc-jump').replace('__PAGE__', String(n));
        });
    });
    // DataTables draws its own "…" (span.ellipsis, or a disabled page-link
    // with data-dt-idx="ellipsis" in the Bootstrap skin); the box drives the table.
    $(document).on('click', '.dataTables_paginate .ellipsis, .dataTables_paginate [data-dt-idx="ellipsis"]', function (e) {
        e.preventDefault(); e.stopImmediatePropagation();
        var $el = $(this), $host = $el.closest('li').length ? $el.closest('li') : $el;
        var $wrap = $el.closest('.dataTables_wrapper'), $tbl = $wrap.find('table.dataTable').first();
        if (!$tbl.length || !$.fn.DataTable) { return; }
        var table = $tbl.DataTable(), info = table.page.info();
        openBox($host, info.pages || 1, (info.page || 0) + 1, function (n) { close(); table.page(n - 1).draw('page'); });
    });
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && $('.afcdc-jump__box').length) { close(); e.stopImmediatePropagation(); } });
    $(document).on('click', function (e) { if (!$(e.target).closest('.afcdc-jump').length) { close(); } });
});

/* The list search shows what it would find as you type: rows of the list
 * (a pick opens one) and the parents it can be filtered by (a pick sets
 * that filter), then "Search for …" which submits as before. Arrow keys
 * move, Enter picks, Esc closes. projects/search_suggest answers. */
$(function () {
    var $in = $('input[data-afcdc-suggest]').first();
    if (!$in.length) { return; }
    var model = $in.attr('data-afcdc-suggest'), open = $in.attr('data-afcdc-open') || '';
    var prefix = (typeof lang_prefix === 'string') ? lang_prefix : '';
    var $form = $in.closest('form'), $wrap = $in.closest('.afcdc-search');
    var $box = $('<div class="afcdc-typeahead" role="listbox" hidden></div>').appendTo($wrap);
    var timer = null, seq = 0, items = [], active = -1;
    function close() { $box.attr('hidden', true).empty(); items = []; active = -1; $in.attr('aria-expanded', 'false'); }
    function go(item) {
        if (item.filter) {
            var $sel = $form.find('select[name="' + item.filter + '"]');
            if ($sel.length && $sel.find('option[value="' + item.value + '"]').length) { $in.val(''); $sel.val(String(item.value)); $form.submit(); return; }
            $in.val(item.label); $form.submit(); return;
        }
        if (item.id !== undefined && open) { window.location.href = prefix + '/' + open + '/' + item.id; return; }
        $form.submit();
    }
    function render(groups, q) {
        $box.empty(); items = []; active = -1;
        $.each(groups, function (i, g) {
            $box.append($('<div class="afcdc-typeahead__group"></div>').text(g.label));
            $.each(g.items, function (j, it) {
                var $row = $('<div class="afcdc-typeahead__item" role="option"></div>').append($('<span></span>').text(it.label));
                if (it.hint) { $row.append($('<small></small>').text(it.hint)); }
                if (it.filter) { $row.append($('<small class="afcdc-typeahead__act"></small>').text('filter')); }
                if (it.why) {
                    // Found through its description: show the passage with the words marked.
                    var $why = $('<div class="afcdc-typeahead__why"></div>').append($('<b></b>').text('In description')).append(document.createTextNode(' '));
                    var words = q.split(/\s+/).filter(Boolean).sort(function (a, b) { return b.length - a.length; });   // longest first, so a word is not cut by its own prefix
                    var rest = it.why, re = new RegExp('(' + words.map(function (w) { return w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|') + ')', 'ig');
                    rest.split(re).forEach(function (part, k) { if (!part) { return; } $why.append(k % 2 ? $('<mark></mark>').text(part) : document.createTextNode(part)); });
                    $row.append($why);
                }
                $row.data('item', it); $box.append($row); items.push($row);
            });
        });
        var $all = $('<div class="afcdc-typeahead__item afcdc-typeahead__all" role="option"></div>').text('Search for "' + q + '"').data('item', { search: true });
        $box.append($all); items.push($all);
        $box.removeAttr('hidden'); $in.attr('aria-expanded', 'true');
    }
    function ask() {
        var q = $.trim($in.val());
        if (q.length < 2) { close(); return; }
        var mine = ++seq;
        $.getJSON(prefix + '/projects/search_suggest/' + model, { q: q }, function (r) {
            if (mine !== seq) { return; }
            var d = (r && r.data) ? r.data : r;
            render((d && d.groups) || [], q);
        });
    }
    function highlight(n) {
        active = n;
        $.each(items, function (i, $r) { $r.toggleClass('is-active', i === n); });
        if (n >= 0) { items[n][0].scrollIntoView({ block: 'nearest' }); }
    }
    $in.on('input', function () { clearTimeout(timer); timer = setTimeout(ask, 250); });
    $in.on('keydown', function (e) {
        if ($box.attr('hidden') !== undefined && $box.is('[hidden]')) { return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(items.length - 1, active + 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(-1, active - 1)); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); go(items[active].data('item')); }
        else if (e.key === 'Escape') { close(); e.stopImmediatePropagation(); }
    });
    $box.on('mousedown', '.afcdc-typeahead__item', function (e) { e.preventDefault(); go($(this).data('item')); });
    $box.on('mousemove', '.afcdc-typeahead__item', function () {
        var el = this, n = -1;
        $.each(items, function (i, $r) { if ($r[0] === el) { n = i; } });
        if (n !== active) { highlight(n); }
    });
    $in.on('blur', function () { window.setTimeout(close, 150); });
    $(document).on('click', function (e) { if (!$(e.target).closest('.afcdc-search').length) { close(); } });
});
