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

import { generateUrl } from '@nextcloud/router';

(function ($, OC) {
    'use strict';

    $(document).ready(function() {
        var pageUrlWithProjectid = (document.URL.indexOf('/loginproject') !== -1);
        if (!pageUrlWithProjectid) {
            $('#passwordInput').val('');
            $('#projectidInput').val('');
            $('#projectidInput').focus().select();
        }
        else {
            $('#passwordInput').focus().select();
        }
        main();
    });

    function main() {
        var url = generateUrl('/apps/cospend/project');
        $('#loginform').attr('action', url);

        if ($('#passwordInput').val().length > 0) {
            $('#loginform').submit();
        }
    }

})(jQuery, OC);
