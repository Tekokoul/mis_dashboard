/* Charts for projects_graphs/members.
 *
 * Colours are Africa CDC / African Union family. This file previously carried
 * the CrystalEngine vendor palette (#5E77E9 / #8897DD), which survived the
 * white-labelling because nothing here reads _PROJECT_COLOR - the page never
 * defines the `graph_color` variable that the other charts use.
 *
 * Series colours below were checked with a CVD validator rather than picked
 * by eye. Two results drive the choices:
 *   - AU green against AU red measures delta-E 1.6 under deuteranopia, and
 *     AU green against AU gold 7.5 under protanopia. Neither pair is safe as
 *     a colour-only encoding.
 *   - Green / blue / gold is the largest set that clears all-pairs CVD
 *     separation. Past three series no arrangement of this hue space passes.
 */

/* Completed against Total is part-of-whole, not two categories, so it is one
   hue at two steps: the filled measure and its container. */
var AFCDC_PART  = '#1A5632';   /* AU Corporate Green - the completed portion */
var AFCDC_WHOLE = '#9CC3A6';   /* same hue, light step - the total container  */

/* Categorical series, assigned in this fixed order and never cycled.
   A fourth division user series needs faceting or an "Other" fold, not a new hue. */
var AFCDC_SERIES = ['#2E7D3C', '#3A6FD8', '#C9911F'];

Morris.Bar({
    element: 'ChartistOverallTasks',
    data: ms_chart,
    xkey: 'country',
    horizontal: true,
    ykeys: ['completed', 'total'],
    barColors: [AFCDC_PART, AFCDC_WHOLE],
    labels: ['Completed', 'Total']
});


Morris.Bar({
    element: 'ChartistRanking',
    data: ms_sorted,
    xkey: 'country',
    horizontal: true,
    stacked: true,
    ykeys: ['completed', 'total'],
    barColors: [AFCDC_PART, AFCDC_WHOLE],
    labels: ['Progress', 'Total']
});

Morris.Line({
    element: 'ChartistMonthly',
    data: ms_monthly,
    xkey: 'month',
    ykeys: member_keys,
    labels: member_labels,
    xLabels: 'month',
    parseTime: false,
    /* Morris cycles this array when there are more series than colours. With
       more than three division users on the chart the repeats are indistinguishable,
       so read this chart from its legend and hover labels, not from colour
       alone - or facet it into small multiples per division user. */
    lineColors: AFCDC_SERIES,
    hideHover: 'auto'
});
