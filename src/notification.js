/*jshint esversion: 6 */

export const showTemporary = function(text) {
    const options = {};
    options.timeout = 7;
    const toast = window.OCP.Toast.message(text, options);
    return $(toast.toastElement);
};
