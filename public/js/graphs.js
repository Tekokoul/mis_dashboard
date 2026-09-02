class CEGauge{
    constructor(element, options) {
        this.gauge = new Gauge(element).setOptions(options);
        this.gauge.maxValue = 100;
        this.gauge.setMinValue(0);
        // Reduced motion: the needle is drawn at rest instead of sweeping.
        // animationSpeed is a property, not an option - setOptions() ignores
        // it, and Gauge.set() copies it into the pointer on every call.
        // Same when the user switched the sweep off in Profile > Settings.
        if ((window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) || window.animate_gauges === 0) {
            this.gauge.animationSpeed = 1;
        }
    }

    update(value) {
        this.gauge.set(value);
    }
}


const gauges = document.querySelectorAll('.gaugeBasic');
gauges.forEach(gauge => {
    const Gauge = new CEGauge(gauge, {
        lines: 12, // The number of lines to draw
        angle: 0, // The length of each line
        lineWidth: 0.1, // The line thickness
        pointer: {
            length: 0.55, // The radius of the inner circle
            strokeWidth: 0.05, // The rotation offset
            color: '#444' // Fill color
        },
        limitMax: 'true', // If true, the pointer will not go past the end of the gauge
        colorStart: graph_color, // Colors
        colorStop: graph_color, // just experiment with them
        strokeColor: '#E8EDE9', // the progress-track tint (custom.css .progress), so gauge and bars match
        generateGradient: false
    });
    const gauseValue = gauge.getAttribute('data-value');
    Gauge.update(gauseValue);
});