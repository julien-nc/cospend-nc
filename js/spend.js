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

    //////////////// VAR DEFINITION /////////////////////

    var spend = {
    };

    //////////////// UTILS /////////////////////

    function pad(n) {
        return (n < 10) ? ('0' + n) : n;
    }

    function endsWith(str, suffix) {
        return str.indexOf(suffix, str.length - suffix.length) !== -1;
    }

    function basename(str) {
        var base = String(str).substring(str.lastIndexOf('/') + 1);
        if (base.lastIndexOf(".") !== -1) {
            base = base.substring(0, base.lastIndexOf("."));
        }
        return base;
    }

    /*
     * get key events
     */
    function checkKey(e) {
        e = e || window.event;
        var kc = e.keyCode;
        //console.log(kc);

        // key '<'
        if (kc === 60 || kc === 220) {
            e.preventDefault();
        }

        if (e.key === 'Escape') {
        }
    }

    $(document).ready(function() {
        spend.pageIsPublic = (document.URL.indexOf('/whatever') !== -1);
        if ( !spend.pageIsPublic ) {
            //restoreOptions();
        }
        else {
            //restoreOptionsFromUrlParams();
        }
        main();
    });

    function main() {

        // get key events
        document.onkeydown = checkKey;

        window.onclick = function(event) {
            if (!event.target.matches('.app-navigation-entry-utils-menu-button button')) {
                console.log(event.target);
                $('.app-navigation-entry-menu.open').removeClass('open');
            }
        }

        $('.app-navigation-entry-utils-menu-button').click(function() {
            $('.app-navigation-entry-menu.open').removeClass('open');
            $(this).parent().parent().parent().find('.app-navigation-entry-menu').addClass('open');
        });
        //$('#projectSelect').change(function() {
        //    if (isUserLoggedIn()) {
        //        saveOptions($(this).attr('id'));
        //    }
        //});

    }

})(jQuery, OC);
