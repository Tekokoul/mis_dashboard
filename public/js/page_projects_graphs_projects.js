/* AWP Activities table.
 *
 * Auto-loaded by template.php's page_<controller>_<action>.js convention, so
 * nothing has to enqueue it. Gives the table search, per-column sorting and
 * paging, plus the three dropdown filters above it. Filters, search, sort
 * and page survive Back and reload (sessionStorage), and the header count
 * and the reset button always say what the table is showing.
 */
(function () {
    var el = document.getElementById('datatable-activities');
    if (!el || typeof jQuery === 'undefined' || !jQuery.fn.DataTable) { return; }

    // Rows per page follows Profile > Settings (template.php sets page_length
    // from the same value the CRUD lists use), so the two never disagree.
    var rows = parseInt(window.page_length, 10) || 50;
    var table = jQuery(el).DataTable({
        stateSave: true,
        stateDuration: -1,    // sessionStorage: survives Back and reload, gone when the tab closes
        pageLength: rows,
        // The saved state remembers filters, search and sort - but the page
        // length always follows the profile setting, otherwise a state saved
        // before the setting changed kept showing the old row count.
        stateLoadParams: function (settings, data) { data.length = rows; },
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        // The leading column is a row counter, not data: never sort by it, and
        // renumber it after every sort/filter/page so it always reads 1..n for
        // what is actually on screen.
        order: [],
        columnDefs: [{ targets: 0, orderable: false, searchable: false }],
        language: {
            search: '',
            searchPlaceholder: 'Search activities…',
            lengthMenu: 'Show _MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_',
            infoEmpty: 'No activities to show',
            infoFiltered: '(filtered from _MAX_)',
            zeroRecords: 'No activity matches those filters',
            paginate: { previous: '‹', next: '›' }
        }
    });

    function renumber() {
        table.column(0, { search: 'applied', order: 'applied', page: 'current' })
             .nodes()
             .each(function (cell, i) {
                 cell.innerHTML = table.page.info().start + i + 1;
             });
    }
    table.on('order.dt search.dt draw.dt', renumber);
    renumber();

    // Exact match on the whole cell, escaped, so "1.1" cannot also match
    // "1.10" and a dot cannot act as a regex wildcard. Shared by the change
    // handler and the restore below.
    function rx(v) { return v ? '^' + v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$' : ''; }

    var selects = document.querySelectorAll('[data-afcdc-filter]');
    var reset   = document.getElementById('afcdc-filters-reset');
    var count   = document.getElementById('afcdc-count');

    // Restore each dropdown from the saved column search. The saved value is
    // the escaped regex, not the option text, so compare through rx(); a saved
    // value with no matching option (a renamed lens or deliverable) is dropped
    // so the table never silently hides rows behind a filter nobody can see.
    var dirty = false;
    Array.prototype.forEach.call(selects, function (sel) {
        var col = parseInt(sel.getAttribute('data-afcdc-filter'), 10);
        var saved = table.column(col).search();
        sel.value = '';
        if (!saved) { return; }
        var hit = Array.prototype.some.call(sel.options, function (opt) {
            if (opt.value && rx(opt.value) === saved) { sel.value = opt.value; return true; }
            return false;
        });
        if (!hit) { table.column(col).search(''); dirty = true; }
    });
    if (dirty) { table.draw(); }

    // Header count, active-filter styling and the reset button always reflect
    // the table, whatever changed it.
    function reflect() {
        var info = table.page.info();
        var n = table.search() ? 1 : 0;                 // the search box is a filter too
        Array.prototype.forEach.call(selects, function (sel) {
            var on = !!sel.value;
            n += on ? 1 : 0;
            sel.parentNode.classList.toggle('is-active', on);   // parent is <label class="afcdc-filter">
        });
        if (count) {
            count.textContent = info.recordsDisplay === info.recordsTotal
                ? info.recordsTotal + ' activities'
                : info.recordsDisplay + ' of ' + info.recordsTotal + ' activities';
        }
        if (reset) {
            reset.disabled = n === 0;
            reset.textContent = n === 0 ? 'Clear filters' : (n === 1 ? 'Clear 1 filter' : 'Clear ' + n + ' filters');
        }
    }
    table.on('draw.dt', reflect);
    reflect();   // the init draw fired inside DataTable(), before this handler existed

    Array.prototype.forEach.call(selects, function (sel) {
        sel.addEventListener('change', function () {
            var col = parseInt(sel.getAttribute('data-afcdc-filter'), 10);
            table.column(col).search(rx(sel.value), true, false).draw();
        });
    });

    if (reset) {
        reset.addEventListener('click', function () {
            Array.prototype.forEach.call(selects, function (sel) { sel.value = ''; });
            table.search('').columns().search('').draw();   // draw.dt runs reflect()
        });
    }

    // Printing: show every row that matches the current filters, then restore.
    var lengthBeforePrint = null;
    window.addEventListener('beforeprint', function () {
        lengthBeforePrint = table.page.len();
        table.page.len(-1).draw(false);
    });
    window.addEventListener('afterprint', function () {
        if (lengthBeforePrint !== null) { table.page.len(lengthBeforePrint).draw(false); lengthBeforePrint = null; }
    });
})();
