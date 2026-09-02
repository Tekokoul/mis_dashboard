/* Add here all your JS customizations */
tinymce.init({
    selector: ".mceEditor",
    height: 400,
    entity_encoding:"raw",
    relative_urls: false,
    // The Responsive Filemanager plugin (server-side file browser/uploader)
    // was removed on 2 Sep 2026: it was reachable without a login. Images and
    // media are inserted by URL.
    plugins:
        "advlist autolink link image lists charmap print preview hr anchor pagebreak searchreplace wordcount visualblocks visualchars insertdatetime media nonbreaking table directionality emoticons paste  code fullscreen"
    ,
    toolbar1: "bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | blockquote | link unlink anchor | image media | forecolor backcolor | code fullscreen ",
    image_advtab: true ,
    image_class_list: [
        {title: 'Responsive', value: 'img-responsive'}
    ],
    media_live_embeds: true
});

$(function(){
    $('[data-plugin-datetimepicker]').datetimepicker({
        dateFormat: 'yy-mm-dd',
        timeFormat: 'HH:mm:ss',
        beforeShow: function() {
            setTimeout(function(){
                $('.ui-datepicker').css('z-index', 99999999999999);
            }, 0);
        }
    });

});

$(function(){
    $('[data-plugin-datepicker]').datepicker({
        dateFormat: 'yy-mm-dd',
        beforeShow: function() {
            setTimeout(function(){
                $('.ui-datepicker').css('z-index', 99999999999999);
            }, 0);
        }
    });
});

$(document.body).off("click", ".deleteElement").on("click", ".deleteElement", function(e){
    if($('.deleteElement').length>1){
        var id = $(this).data("id");
        $("#element_"+id).remove();
    }
});

// Define the event listener function
$(document.body).off("click", "#addElement").on("click", "#addElement", function(e){
    e.preventDefault();
    var tableBody = $('#elementstable').find("tbody"),
        trLast = tableBody.find("tr.element_row:last"),
        trNew = trLast.clone();

    var newid = parseInt(trNew.prop("id").match(/[^_]+_(\d+)/)[1], 10)+1;
    trNew.prop("id", "element_"+newid);

    var deleteElement = trNew.find(".deleteElement");
    deleteElement.data("id", newid);

    trNew.find(":input").each(function () {
        this.name = this.name.replace(/\[(\d+)\]/, function(str,p1){
            return '[' + (parseInt(p1,10)+1) + ']';
        });
        this.id = this.id.replace(/\[(\d+)\]/, function(str,p1){
            return '[' + (parseInt(p1,10)+1) + ']';
        });
    }).val('');
    trNew.find("select").val(trNew.find("select option:first").val());

    trLast.after(trNew);
});

function open_popup(url) {
    var w = 880;
    var h = 570;
    var l = Math.floor((screen.width-w)/2);
    var t = Math.floor((screen.height-h)/2);
    var win = window.open(url, 'ResponsiveFilemanager', "scrollbars=1,width=" + w + ",height=" + h + ",top=" + t + ",left=" + l);
}

$('.modal-basic').magnificPopup({
    type: 'inline',
    preloader: false,
    modal: true,
    callbacks : {
        open : function(){
            var mp = $.magnificPopup.instance,
                t = $(mp.currItem.el[0]);
            var content = $(this.content);
            content.on('click', '.modal-dismiss', function (e) {
                e.preventDefault();
                $.magnificPopup.close();
            });
            content.on('click', '.modal-confirm', function (e) {
                e.preventDefault();
                $.magnificPopup.close();
                // POST, with the CSRF token: as a GET this could be triggered
                // by an <img src> on any page an administrator visited.
                $.ajax({
                    method: "POST",
                    url: lang_prefix+"/core/db_delete/"+content.data('tablename')+"/"+t.data('id'),
                    data: { csrf: window.CSRF_TOKEN || '' },
                    dataType: "json",
                    cache: false,
                    success: function(data){
                        location.reload();
                    },
                    error: function(xhr){
                        alert(xhr.status === 403
                            ? 'The page had been open too long for the deletion to be accepted. Reload and try again.'
                            : 'The entry could not be deleted (' + xhr.status + ').');
                    }
                });
            });
        }
    }
});

$(document).ready(function() {
    $('.modal-password').magnificPopup({
        type: 'inline',
        preloader: false,
        modal: true,
        callbacks: {
            open: function () {
                var content = $(this.content);
                content.on('click', '.modal-dismiss', function (e) {
                    e.preventDefault();
                    $.magnificPopup.close();
                });
                content.on('click', '.modal-update', function (e) {
                    $("#passwordform").validate();
                    $.magnificPopup.close();
                });
            }
        }
    });
});

$(document).on('submit', 'form', function(event) {
    var form = $(this);
    var passwordFields = form.find('input[type="password"]');
    var isFirstFieldFilled = passwordFields.eq(0).val() !== '';
    var isSecondFieldFilled = passwordFields.eq(1).val() !== '';

    // Check if only one field is completed
    if ((isFirstFieldFilled && !isSecondFieldFilled) || (!isFirstFieldFilled && isSecondFieldFilled)) {
        alert('Please complete both password fields.');
        event.preventDefault();
        return false;
    }

    // Check if both filled fields match
    if (isFirstFieldFilled && isSecondFieldFilled) {
        var firstPassword = passwordFields.eq(0).val();
        var secondPassword = passwordFields.eq(1).val();
        if (firstPassword !== secondPassword) {
            alert('Passwords do not match.');
            event.preventDefault();
            return false;
        }
    }
    // All conditions passed, allow form submission
    return true;
});

$(document).ready(function() {
    $('select[data-plugin-selectTwo]').on('change', function() {
        var selectedOption = $(this).val() && $(this).val()[0];
        var options = $(this).find('option').not(':first, :last, [value="0"]');
        if (selectedOption == '%') {
            $(this).val(options.map(function() {
                return this.value;
            }).get()).trigger('change');
        } else if (selectedOption == '0') {
            $(this).val(null).trigger('change');
        }
    });
});









// (function($) {
//
//     'use strict';
//
//     /*
//     * eCommerce DataTable List
//     */
//     var ceListDataTableInit = function() {
//
//         var $ceListTable = $('#datatable-list');
//
//         $ceListTable.dataTable({
//             dom: '<"row justify-content-between"<"col-auto"><"col-auto">><"table-responsive"t>ip',
//             columnDefs: [
//                 {
//                     targets: 0,
//                     orderable: false
//                 }
//             ],
//             pageLength: 1,
//             order: [],
//             language: {
//                 paginate: {
//                     previous: '<i class="fas fa-chevron-left"></i>',
//                     next: '<i class="fas fa-chevron-right"></i>'
//                 }
//             },
//             drawCallback: function() {
//
//                 // Move dataTables info to footer of table
//                 $ceListTable
//                     .closest('.dataTables_wrapper')
//                     .find('.dataTables_info')
//                     .appendTo( $ceListTable.closest('.datatables-header-footer-wrapper').find('.results-info-wrapper') );
//
//                 // Move dataTables pagination to footer of table
//                 $ceListTable
//                     .closest('.dataTables_wrapper')
//                     .find('.dataTables_paginate')
//                     .appendTo( $ceListTable.closest('.datatables-header-footer-wrapper').find('.pagination-wrapper') );
//
//                 $ceListTable.closest('.datatables-header-footer-wrapper').find('.pagination').addClass('pagination-modern pagination-modern-spacing justify-content-center');
//
//             }
//         });
//
//         // Link "Show" select for change the "pageLength" of dataTable
//         $(document).on('change', '.results-per-page', function(){
//             var $this = $(this),
//                 $dataTable = $this.closest('.datatables-header-footer-wrapper').find('.dataTable').DataTable();
//
//             $dataTable.page.len( $this.val() ).draw();
//         });
//
//         // Link "Search" field to show results based in the term entered (the "Filter By" is considered to filter the results)
//         $(document).on('keyup', '.search-term', function(){
//             var $this = $(this),
//                 // $filterBy = $this.closest('.datatables-header-footer-wrapper').find('.filter-by'),
//                 $dataTable = $this.closest('.datatables-header-footer-wrapper').find('.dataTable').DataTable();
//
//             // if( $filterBy.val() == 'all' ) {
//                 $dataTable.search( $this.val() ).draw();
//             // } else {
//             //     $dataTable.column( parseInt( $filterBy.val() ) ).search( $this.val() ).draw();
//             // }
//         });
//
//         // Trigger "keyup" event when "filter-by" changes
//         // $(document).on('change', '.filter-by', function(){
//         //     var $this = $(this),
//         //         $searchField = $this.closest('.datatables-header-footer-wrapper').find('.search-term');
//         //
//         //     $searchField.trigger('keyup');
//         // });
//
//         // Select All
//         $ceListTable.find( '.select-all' ).on('change', function(){
//             if( this.checked ) {
//                 $ceListTable.find( 'input[type="checkbox"]:not(.select-all)' ).prop('checked', true);
//             } else {
//                 $ceListTable.find( 'input[type="checkbox"]:not(.select-all)' ).prop('checked', false);
//             }
//         })
//
//     };
//
//     ceListDataTableInit();
//
// }(jQuery));