/*jshint esversion: 6 */

import cospend from './state';
import {generateUrl} from '@nextcloud/router';
import * as Notification from './notification';

function hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : null;
}

function componentToHex(c) {
    const hex = c.toString(16);
    return hex.length === 1 ? '0' + hex : hex;
}

export function rgbObjToHex(o) {
    return rgbToHex(o.r, o.g, o.b);
}

function rgbToHex(r, g, b) {
    return '#' + componentToHex(parseInt(r)) + componentToHex(parseInt(g)) + componentToHex(parseInt(b));
}

export function hexToDarkerHex(hex) {
    const rgb = hexToRgb(hex);
    while (getColorBrightness(rgb) > 100) {
        if (rgb.r > 0) {
            rgb.r--;
        }
        if (rgb.g > 0) {
            rgb.g--;
        }
        if (rgb.b > 0) {
            rgb.b--;
        }
    }
    return rgbToHex(rgb.r, rgb.g, rgb.b);
}

// this formula was found here : https://stackoverflow.com/a/596243/7692836
function getColorBrightness(rgb) {
    return 0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b;
}

export function Timer(callback, mydelay) {
    let timerId, start, remaining = mydelay;

    this.pause = function() {
        window.clearTimeout(timerId);
        remaining -= new Date() - start;
    };

    this.resume = function() {
        start = new Date();
        window.clearTimeout(timerId);
        timerId = window.setTimeout(callback, remaining);
    };

    this.resume();
}

let mytimer = 0;

export function delay(callback, ms) {
    return function() {
        const context = this, args = arguments;
        clearTimeout(mytimer);
        mytimer = setTimeout(function() {
            callback.apply(context, args);
        }, ms || 0);
    };
}

export function pad(n) {
    return (n < 10) ? ('0' + n) : n;
}

export function endsWith(str, suffix) {
    return str.indexOf(suffix, str.length - suffix.length) !== -1;
}

export function basename(str) {
    let base = String(str).substring(str.lastIndexOf('/') + 1);
    if (base.lastIndexOf('.') !== -1) {
        base = base.substring(0, base.lastIndexOf('.'));
    }
    return base;
}

export function getUrlParameter(sParam) {
    const sPageURL = window.location.search.substring(1);
    const sURLVariables = sPageURL.split('&');
    for (let i = 0; i < sURLVariables.length; i++) {
        const sParameterName = sURLVariables[i].split('=');
        if (sParameterName[0] === sParam) {
            return decodeURIComponent(sParameterName[1]);
        }
    }
}

export function saveOptionValue(optionValues) {
    if (!cospend.pageIsPublic) {
        const req = {
            options: optionValues
        };
        const url = generateUrl('/apps/cospend/saveOptionValue');
        $.ajax({
            type: 'POST',
            url: url,
            data: req,
            async: true
        }).done(function() {
        }).fail(function() {
            Notification.showTemporary(
                t('cospend', 'Failed to save option values')
            );
        });
    }
}

/*
 * get key events
 */
export function checkKey(e) {
    e = e || window.event;
    const kc = e.keyCode;
    //console.log(kc);

    // key '<'
    if (kc === 60 || kc === 220) {
        e.preventDefault();
    }

    if (e.key === 'Escape') {
    }
}

export function generatePublicLinkToFile(targetPath, successCallback) {
    $('.loading-bill').addClass('icon-loading-small');
    const req = {
        path: targetPath
    };
    const url = generateUrl('/apps/cospend/getPublicFileShare');
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function(response) {
        $('.loading-bill').removeClass('icon-loading-small');

        const filePublicUrl = window.location.protocol + '//' + window.location.hostname + generateUrl('/s/' + response.token);

        let what = $('#what').val();
        what = what + ' ' + filePublicUrl;
        $('#what').val(what);
        successCallback();
    }).always(function() {
    }).fail(function(response) {
        $('.loading-bill').removeClass('icon-loading-small');
        Notification.showTemporary(
            t('cospend', 'Failed to generate public link to file') +
            ': ' + response.responseJSON.message
        );
    });
}

export function updateCustomAmount() {
    let tot = 0;
    $('.amountinput').each(function() {
        const val = parseFloat($(this).val());
        if (!isNaN(val) && val > 0.0) {
            tot = tot + val;
        }
    });
    $('#amount').val(tot);
}

export function copyToClipboard(text) {
    const dummy = $('<input id="dummycopy">').val(text).appendTo('body').select();
    document.execCommand('copy');
    $('#dummycopy').remove();
}

export function reload(msg) {
    Notification.showTemporary(msg);
    new Timer(function() {
        location.reload();
    }, 5000);
}