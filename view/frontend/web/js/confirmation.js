define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/confirm',
    'Magento_Catalog/js/price-utils'
], function ($, $t, confirmation, priceUtil) {
    return function (config) {
        var content = '';
        var address = config.data.address;
        var addressParts = [];
        var source = config.data.paymentMethod;

        content += $t('Please confirm your details to place the order. Your card will be charged for the total shown below.');
        content += '<br>';
        content += '<br>';
        content += '<b>' + $t('Total incl. tax') + '</b>';
        content += '<br>';
        content += priceUtil.formatPrice(config.data.total.base_grand_total, window.StripeInstantCheckout.basePriceFormat);
        content += '<br>';
        content += '<br>';
        content += '<b>' + $t('Shipping Address') + '</b>';
        content += '<br>';

        if (address.organization.length > 0) {
            addressParts.push(address.organization);
        }

        for (var i = 0; i < address.addressLine.length; i++) {
            addressParts.push(address.addressLine[i]);
        }

        if (address.city.length > 0) {
            addressParts.push(address.city);
        }

        if (address.region.length > 0) {
            addressParts.push(address.region);
        }

        if (address.postalCode.length > 0) {
            addressParts.push(address.postalCode);
        }

        content += addressParts.join(', ');
        content += '<br>';
        content += '<br>';
        content += '<b>' + $t('Billing Address') + '</b>';
        content += '<br>';
        content += addressParts.join(', ');
        content += '<br>';
        content += '<br>';
        content += '<b>' + $t('Payment Method') + '</b>';
        content += '<br>';
        content += $.mage.__('Credit Card: %1, ending: %2 (expires: %3/%4)')
            .replace('%1', source.card.brand)
            .replace('%2', source.card.last4)
            .replace('%3', source.card.exp_month)
            .replace('%4', source.card.exp_year)
            ;
        content += '<br>';
        content += '<br>';
        content += '<b>' + $t('Shipping Method') + '</b>';
        content += '<br>';
        content += config.data.shippingMethod;

        config.content = content;

        confirmation(config);
    }
});