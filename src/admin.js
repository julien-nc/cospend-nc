/*jshint esversion: 6 */

import { generateUrl } from '@nextcloud/router';
import * as Notification from "./notification";

(function() {
    if (!OCA.Cospend) {
        OCA.Cospend = {};
    }
})();

function setAllowAnonymousCreation(val) {
    const url = generateUrl('/apps/cospend/setAllowAnonymousCreation');
    const req = {
        allow: val
    }
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function () {
        Notification.showTemporary(
            t('cospend', 'Cospend setting saved')
        );
    }).fail(function() {
        Notification.showTemporary(
            t('cospend', 'Failed to save Cospend setting')
        );
    });
}

$(document).ready(function() {
    $('body').on('change', 'input#allowAnonymousCreation', function() {
        setAllowAnonymousCreation($(this).is(':checked') ? '1' : '0');
    });
});
