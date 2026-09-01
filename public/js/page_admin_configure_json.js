
(function($) {

    'use strict';

    /*
    Basic
    */
    $('#jsontree').jstree({
        'core': {
            'themes': {
                'responsive': false
            }
        },
        'types': {
            'default': {
                'icon': 'fas fa-folder'
            },
            'file': {
                'icon': 'fas fa-file'
            }
        },
        'plugins': ['types']
    });
}).apply(this, [jQuery]);

$('#jsontree').on("select_node.jstree", function (e, data) {
    window.location.href = data.node.a_attr.href;
});
