$(document).ready(function () {
    // $.fn.modal.Constructor.prototype.enforceFocus = function() {};

    function loadPopupContent(id, caseId, modalType) {
        $.ajax({
            url: lang_prefix + "/cases/" + modalType + "/" + caseId +"/"+id,
            method: 'GET',
            success: function (response) {
                // Set the HTML of the popup container to the loaded content
                $('#caseModal').html(response);

                $("#caseModal").find('[data-plugin-selectTwo]').select2({
                    dropdownParent: $('#caseModal')
                });

                // Add click handlers for the update and cancel buttons
                $('.modal-confirm').on('click', function () {
                    // Make an AJAX request to update the data
                    $('#caseform').validate();
                    $('#caseform').submit();
                    $.magnificPopup.close();
                });

                $('.modal-dismiss').on('click', function (event) {
                    // Close the popup when the cancel button is clicked
                    event.preventDefault(); // Prevent the default form submission behavior
                    $.magnificPopup.close();
                });
            }, error: function (xhr, status, error) {
                console.error(xhr, status, error);
            }
        });
    }

    $(document).on('click', '.open-modal', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var caseId = $(this).data('case');
        var modalType = $(this).data('type');

        loadPopupContent(id, caseId, modalType);
        $.magnificPopup.open({
            items: {
                src: '#caseModal', type: 'inline', modal: true
            }
        });
    });

    $.ajax({
        url: lang_prefix + "/constituencies/get_details/" + constituency_id,
        type: "GET",
        dataType: "html",
        success: function(response) {
            // `response` is the HTML content returned from the server
            $("#details").html(response);
            $("#details").find('[data-plugin-selectTwo]').select2({
                dropdownParent: $('#details')
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log("AJAX Error: " + textStatus + " - " + errorThrown);
        }
    });

    function loadWardPopupContent(id, constituencyId) {
        $.ajax({
            url: lang_prefix + "/constituencies/ward/" + constituencyId +"/"+id,
            method: 'GET',
            success: function (response) {
                // Set the HTML of the popup container to the loaded content
                $('#wardModal').html(response);

                $("#wardModal").find('[data-plugin-selectTwo]').select2({
                    dropdownParent: $('#wardModal')
                });

                // Add click handlers for the update and cancel buttons
                $('.modal-confirm').on('click', function () {
                    // Make an AJAX request to update the data
                    $('#wardform').submit();
                    $.magnificPopup.close();
                });

                $('.modal-dismiss').on('click', function (event) {
                    // Close the popup when the cancel button is clicked
                    event.preventDefault(); // Prevent the default form submission behavior
                    $.magnificPopup.close();
                });
            }, error: function (xhr, status, error) {
                console.error(xhr, status, error);
            }
        });
    }


    $(document).on('click', '.open-ward-modal', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var constituencyId = $(this).data('constituency-id');

        loadWardPopupContent(id, constituencyId);
        $.magnificPopup.open({
            items: {
                src: '#wardModal', type: 'inline', modal: true
            }
        });
    });

    $(document).on('click', '.delete-ward-modal', function (e) {
        e.preventDefault();
        var t = $(this);
        $.magnificPopup.open({
            items: {
                src: '#deletewardModal',
                type: 'inline'
            },
            preloader: false,
            modal: true,
            callbacks: {
                open: function () {
                    var mp = $.magnificPopup.instance;
                    var content = $(this.content);
                    content.on('click', '.modal-dismiss', function (e) {
                        e.preventDefault();
                        $.magnificPopup.close();
                    });
                    content.on('click', '.modal-confirm', function (e) {
                        e.preventDefault();
                        $.magnificPopup.close();
                        $.ajax({
                            method: "GET",
                            url: lang_prefix + "/constituencies/ward_delete/" + t.data('id'),
                            dataType: "json",
                            cache: false,
                            success: function (data) {
                                // new PNotify({
                                //     title: '<b>SUCCESS</b>',
                                //     text: data.message,
                                //     type: 'success',
                                //     addclass: notificationclass,
                                //     stack: {"dir1": "up", "dir2": "left"},
                                //     width: "50%"
                                // });
                                location.reload();
                            }
                        });
                    });
                }
            }
        });
    });

    

});

$('.event-delete-modal').magnificPopup({
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
                $.ajax({
                    method: "GET",
                    url: lang_prefix+"/cases/event_delete/"+t.data('type')+"/"+t.data('id'),
                    dataType: "json",
                    cache: false,
                    success: function(data){
                        // new PNotify({
                        //     title: '<b>SUCCESS</b>',
                        //     text: data.message,
                        //     type: 'success',
                        //     addclass: notificationclass,
                        //     stack: {"dir1": "up", "dir2": "left"},
                        //     width: "50%"
                        // });
                        location.reload();
                    }
                });
            });
        }
    }
});