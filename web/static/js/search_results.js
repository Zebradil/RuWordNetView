$(function () {
    $('#meanings').change(function () {
        var synsetId = $(this).val();
        $('.result-item').hide().filter('[data-synsetId="' + synsetId + '"]').show();
    });
});