(function($) {
    'use strict';
    if( $('#revenueChart').get(0) ) {
        Morris.Bar({
            element: 'revenueChart',
            data: revenueChartData,
            xkey: 'x',
            ykeys: ['y'],
            barColors: [graph_color],
            barSizeRatio: 0.75
        });
    }
}).apply(this, [jQuery]);