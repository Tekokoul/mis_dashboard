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
