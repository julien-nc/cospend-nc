/*jshint esversion: 6 */
/**
 * Nextcloud - cospend
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

import {generateUrl} from '@nextcloud/router';

'use strict';

document.addEventListener('DOMContentLoaded', function(event) {
    const pageUrlWithProjectid = (document.URL.indexOf('/loginproject') !== -1);
    const pidInput = document.getElementById('projectidInput');
    const pwdInput = document.getElementById('passwordInput');

    if (!pageUrlWithProjectid) {
        pwdInput.value = '';
        pidInput.value = '';
        pidInput.focus();
        pidInput.select();
    } else {
        pwdInput.focus();
        pwdInput.select();
    }
    main();
});

function main() {
    const url = generateUrl('/apps/cospend/project');
    const form = document.getElementById('loginform');
    const pwdInput = document.getElementById('passwordInput');

    form.setAttribute('action', url);
    if (pwdInput.value.length > 0) {
        form.submit();
    }
}