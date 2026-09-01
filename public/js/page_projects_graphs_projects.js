/* AWP Activities table.
 *
 * Auto-loaded by template.php's page_<controller>_<action>.js convention, so
 * nothing has to enqueue it. Gives the table search, per-column sorting and
 * paging, plus the three dropdown filters above it.
 */
(function () {
    var el = document.getElementById('datatable-activities');
    if (!el || typeof jQuery === 'undefined' || !jQuery.fn.DataTable) { return; }

    var table = jQuery(el).DataTable({
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
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

    // Dropdown filters. Each carries the column index it filters on.
    var selects = document.querySelectorAll('[data-afcdc-filter]');
    Array.prototype.forEach.call(selects, function (sel) {
        sel.addEventListener('change', function () {
            var col = parseInt(sel.getAttribute('data-afcdc-filter'), 10);
            // Exact match on the whole cell, escaped, so "1.1" cannot also
            // match "1.10" and a dot cannot act as a regex wildcard.
            var v = sel.value
                ? '^' + sel.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'
                : '';
            table.column(col).search(v, true, false).draw();
        });
    });

    var reset = document.getElementById('afcdc-filters-reset');
    if (reset) {
        reset.addEventListener('click', function () {
            Array.prototype.forEach.call(selects, function (sel) { sel.value = ''; });
            table.search('').columns().search('').draw();
        });
    }
})();
