$('.voucher-delete').magnificPopup({
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
                var courier = t.data('courier');
                var voucher_id = t.data('id');
                $.ajax({
                    method: "GET",
                    url: lang_prefix+"/vouchers/delete/"+courier+"/"+voucher_id,
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