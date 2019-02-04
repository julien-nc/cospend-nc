(function() {
    if (!OCA.Payback) {
        OCA.Payback = {};
    }
})();

function setAllowAnonymousCreation(val) {
    var url = OC.generateUrl('/apps/payback/setAllowAnonymousCreation');
    var req = {
        allow: val
    }
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function (response) {
        OC.Notification.showTemporary(
            t('payback', 'Value was successfully saved')
        );
    }).fail(function() {
        OC.Notification.showTemporary(
            t('payback', 'Failed to save value')
        );
    });
}

$(document).ready(function() {
    $('body').on('change', 'input#allowAnonymousCreation', function(e) {
        setAllowAnonymousCreation($(this).is(':checked') ? '1' : '0');
    });
});
