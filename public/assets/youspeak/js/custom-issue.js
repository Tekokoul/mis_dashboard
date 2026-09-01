var $ = jQuery.noConflict();
$(document).ready(function() {
    $('#product-trigger-favourite').click(function (event) {
        event.preventDefault();
        var id = $(this).data("id");
        var isFavorite = $(this).data("favorite");
        if (isFavorite == 1) {
            removeFavorite(id);
        } else {
            addToFavourite(id)
        }
    });

    $('#add-reply').click(function (event) {
        $('#modal-add-reply').modal('toggle');
    });

    $('#add-redirect').click(function (event) {
        $('#modal-add-redirect').modal('toggle');
    });

    $('#add-delete').click(function (event) {
        $('#modal-add-delete').modal('toggle');
    });

    $('#add-action').click(function (event) {
        $('#modal-add-action').modal('toggle');
    });

    $('#add-status').click(function (event) {
        $('#modal-add-status').modal('toggle');
    });


});

function addToFavourite(id) {
    $jq.ajax({
        url: "/" + lang +"/issues/addFavorite?id=" + id,
        success: function (result) {
            if (result.message['status']) {
                $jq('#product-trigger-favourite').children('i').remove();
                $jq('#product-trigger-favourite').append('<i class="icon-heart"></i>');
                $jq('#product-trigger-favourite').data('favorite', 1);
            }
        },
        async: false
    });
}

function removeFavorite(id) {
    $jq.ajax({
        url: "/" + lang +"/issues/removeFavorite?id=" + id,
        success: function (result) {
            if (result.message['status']) {
                $jq('#product-trigger-favourite').children('i').remove();
                $jq('#product-trigger-favourite').append('<i class="icon-heart-empty"></i>');
                $jq('#product-trigger-favourite').data('favorite', 0);
            }
        },
        async: false
    });
}