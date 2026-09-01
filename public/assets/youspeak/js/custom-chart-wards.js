var $ = jQuery.noConflict();
$(document).ready(function() {
    var randomScalingFactor = function() {
        return Math.round(Math.random() * 100);
    };

    var config = {
        type: 'pie',
        data: {
            datasets: [{
                data: [
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                    randomScalingFactor(),
                ],
                backgroundColor: [
                    window.chartColors.red,
                    window.chartColors.orange,
                    window.chartColors.yellow,
                    window.chartColors.green,
                    window.chartColors.blue,
                ],
                label: 'Dataset 1'
            }],
            labels: [
                "Red",
                "Orange",
                "Yellow",
                "Green",
                "Blue"
            ]
        },
        options: {
            responsive: true
        }
    };

    var ctx = document.getElementById("chart-0").getContext("2d");
    window.myPie = new Chart(ctx, config);

    var ctx1 = document.getElementById("chart-1").getContext("2d");
    window.myPie = new Chart(ctx1, config);

    var ctx2 = document.getElementById("chart-2").getContext("2d");
    window.myPie = new Chart(ctx2, config);

    var ctx3 = document.getElementById("chart-3").getContext("2d");
    window.myPie = new Chart(ctx3, config);
});