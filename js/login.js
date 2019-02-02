/*jshint esversion: 6 */
/**
 * Nextcloud - Spend
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */
(function ($, OC) {
    'use strict';

    function login() {
        var projectid = $('#projectid').val();
        var password = $('#projectpassword').val();
        console.log('login '+projectid+' '+password);
        var url = OC.generateUrl('/apps/spend/project');
    }

    $(document).ready(function() {
        var pageUrlWithProjectid = (document.URL.indexOf('/loginproject') !== -1);
        if (!pageUrlWithProjectid) {
            $('#projectpassword').val('');
            $('#projectid').val('');
        }
        main();
    });

    function main() {
        var url = OC.generateUrl('/apps/spend/project');
        $('#loginform').attr('action', url);
        //$('#loginform').submit(function(e) {
        //    e.preventDefault();
        //    login();
        //});
        //$('#okbutton').click(function() {
        //    login();
        //});
        //$('#projectid).on('keyup', function(e) {
        //    if (e.key === 'Enter') {
        //        login();
        //    }
        //});
    }

})(jQuery, OC);
