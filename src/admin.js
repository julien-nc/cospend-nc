/*jshint esversion: 6 */

import {generateUrl} from '@nextcloud/router';
import {
    showSuccess,
    showInfo,
    showError,
} from '@nextcloud/dialogs'

(function() {
    if (!OCA.Cospend) {
        OCA.Cospend = {};
    }
})();

function setAllowAnonymousCreation(val) {
    const url = generateUrl('/apps/cospend/allow-anonymous-creation');
    const req = {
        allow: val
    };
    $.ajax({
        type: 'PUT',
        url: url,
        data: req,
        async: true
    }).done(function() {
        showSuccess(
            t('cospend', 'Cospend setting saved.')
        );
    }).fail(function() {
        showError(
            t('cospend', 'Failed to save Cospend setting')
        );
    });
}

document.addEventListener('DOMContentLoaded', function(event) {
    const anonyCheck = document.getElementById('allowAnonymousCreation');
    anonyCheck.addEventListener('change', (event) => {
        setAllowAnonymousCreation(event.target.checked ? '1' : '0');
    });
});
