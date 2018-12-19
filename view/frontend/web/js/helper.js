define([
    'jquery'
], function ($) {
    return {
        isGuest: function () {
            var mageStorage = JSON.parse(window.localStorage['mage-cache-storage']);

            if (typeof mageStorage.customer === 'undefined') {
                return true;
            }

            if (typeof mageStorage.customer.firstname === 'undefined') {
                return true;
            }

            return false;
        }
    }
});