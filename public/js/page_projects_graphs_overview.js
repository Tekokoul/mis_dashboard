/* Breakdown charts on the MIS deliverables overview.
 *
 * Auto-loaded by template.php's page_<controller>_<action>.js convention, after
 * Raphael and Morris (enqueued in projects_graphs::overview()). The data arrays
 * are emitted inline by the view.
 *
 * Progress is a magnitude, so both charts are single-hue AU Corporate Green -
 * two hues here would imply two categories that do not exist. The axis is
 * pinned to 0-100 so a chart of zeros still reads as "empty measure", not as a
 * broken chart, and bars stay comparable as numbers grow.
 */
(function () {
    if (typeof Morris === 'undefined') { return; }

    function draw(elementId, rows) {
        var el = document.getElementById(elementId);
        if (!el) { return; }
        if (!rows || !rows.length) {
            el.textContent = 'Nothing to chart yet.';
            el.className += ' afcdc-progress-zero';
            return;
        }
        Morris.Bar({
            element: elementId,
            data: rows,
            xkey: 'y',
            ykeys: ['a'],
            labels: ['% complete'],
            horizontal: true,
            barColors: ['#1A5632'],       /* AU Corporate Green */
            ymin: 0,
            ymax: 100,
            xLabelMargin: 6,
            resize: true,
            hoverCallback: function (index, options, content, row) {
                /* Full deliverable/workstream name in the hover, not the
                 * truncated axis label. textContent-style escaping by hand:
                 * the row data is server-supplied but escape it anyway. */
                var esc = function (s) {
                    return String(s).replace(/[&<>"']/g, function (c) {
                        return '&#' + c.charCodeAt(0) + ';';
                    });
                };
                return '<div class="morris-hover-row-label">' + esc(row.full || row.y) + '</div>' +
                       '<div class="morris-hover-point" style="color:#1A5632">' +
                       esc(row.a) + '% complete</div>';
            }
        });
    }

    draw('chart-objectives', window.afcdc_chart_objectives);
    draw('chart-programmes', window.afcdc_chart_programmes);
})();
