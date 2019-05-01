define([
    'jquery',
    'mage/url',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Stripeofficial_InstantCheckout/js/confirmation',
    'Stripeofficial_InstantCheckout/js/helper'
], function ($, urlBuilder, $t, customerData, confirmation, helper) {
    return function (config, element) {
        var initStripeForm = function () {
            var formattedAddress;
            var sourceId = null;
            var stripe = Stripe(config.public_key);
            var cartId = window.StripeInstantCheckout.cartId;
            var div = $(element).find('[data-payment-element]');

            var paymentRequest = stripe.paymentRequest({
                country: config.country_code,
                currency: config.currency_code.toLowerCase(),
                total: {
                    label: $t('Shopping Cart'),
                    amount: window.checkoutConfig.totalsData.grand_total * 100
                },
                requestShipping: true,
                requestPayerName: true,
                requestPayerEmail: true
            });

            var elements = stripe.elements();
            var prButton = elements.create('paymentRequestButton', {
                paymentRequest: paymentRequest
            });

            var getDisplayItems = function () {
                var cartItems = JSON.parse(window.localStorage['mage-cache-storage']).cart.items;
                var displayItems = cartItems.map(function (item) {
                    return {
                        amount: item.qty * item.product_price_value * 100,
                        label: item.product_name
                    };
                });

                return displayItems;
            };

            var chargeRequest = function (ev) {
                var urlXhr;

                if (!helper.isGuest()) {
                    urlXhr = urlBuilder.build('rest/V1/carts/' + 'mine' + '/payment-information');
                } else {
                    urlXhr = urlBuilder.build('rest/V1/guest-carts/' + cartId + '/payment-information');
                }

                var paymentInformation = {
                    'email': ev.payerEmail,
                    'paymentMethod': {
                        'method': 'stripeinstantcheckout',
                        'additional_data': {
                            'stripeToken': ev.source.id
                        }
                    },
                    'billingAddress': formattedAddress
                };

                $.ajax({
                    url: urlXhr,
                    data: JSON.stringify(paymentInformation),
                    type: 'post',
                    dataType: 'json',
                    contentType: 'application/json'
                }).done(function () {
                    customerData.set('cart', {});
                    $('body').trigger('processStart');

                    if (ev.source.card.three_d_secure === 'required') {
                        window.location.href = urlBuilder.build('stripe/instantcheckout/redirect');
                    } else {
                        window.location.href = urlBuilder.build('checkout/onepage/success');
                    }
                })
            };

            var chooseShipping = function (ev) {
                var urlXhr;
                sourceId = ev.source.id;

                var namesArray = (ev.payerName ? ev.payerName : ev.shippingAddress.recipient).split(' ');
                formattedAddress.firstname = namesArray[0];
                formattedAddress.lastname = namesArray[1];

                if (typeof formattedAddress.lastname === 'undefined') {
                    formattedAddress.lastname = '.';
                }

                // Needed because Apple Pay only exposes address after confirmation
                formattedAddress.street = ev.shippingAddress.addressLine;
                formattedAddress.region = ev.shippingAddress.region ? ev.shippingAddress.region : ev.shippingAddress.city;
                formattedAddress.telephone = ev.shippingAddress.phone ? ev.shippingAddress.phone : '000000000';

                if (!helper.isGuest()) {
                    urlXhr = urlBuilder.build('rest/V1/carts/' + 'mine' + '/shipping-information');
                } else {
                    urlXhr = urlBuilder.build('rest/V1/guest-carts/' + cartId + '/shipping-information');
                }

                var shippingAddress = formattedAddress;
                shippingAddress.same_as_billing = 1;
                var shippingOption = ev.shippingOption.id;
                shippingOption = shippingOption.split('|');

                $.ajax({
                    url: urlXhr,
                    data: JSON.stringify({
                        addressInformation: {
                            'shipping_address': shippingAddress,
                            'billing_address': formattedAddress,
                            'shipping_method_code': shippingOption[1],
                            'shipping_carrier_code': shippingOption[0]
                        }
                    }),
                    type: 'post',
                    dataType: 'json',
                    contentType: 'application/json'
                }).done(function (result) {
                    // Close payment sheet with success message
                    ev.complete('success');
                    confirmation({
                        title: $t('Checkout Confirmation'),
                        data: {
                            address: ev.shippingAddress,
                            paymentMethod: ev.source,
                            shippingMethod: ev.shippingOption.label,
                            total: result.totals
                        },
                        actions: {
                            confirm: function () {
                                $('body').trigger('processStart');
                                chargeRequest(ev);
                            },
                            cancel: function () { }
                        }
                    });
                }).fail(function () {
                    // Close payment sheet with failure message
                    ev.complete('fail');
                });
            };

            var getCartTotal = function () {
                var cartItems = JSON.parse(window.localStorage['mage-cache-storage']).cart.items;
                var total = 0;

                for (var i = 0; i < cartItems.length; i++) {
                    total += (cartItems[i].qty * cartItems[i].product_price_value);
                }

                return total;
            };

            paymentRequest.on('shippingoptionchange', function (ev) {
                var total = getCartTotal() * 100;
                var optionCost = ev.shippingOption.amount;
                var displayItems = getDisplayItems();
                displayItems.push({ amount: optionCost, label: $t('Shipping') });

                if (typeof optionCost !== 'undefined') {
                    ev.updateWith({
                        status: 'success',
                        total: {
                            label: $t('Shopping Cart'),
                            amount: total + optionCost,
                            pending: true
                        },
                        displayItems: displayItems
                    });
                } else {
                    ev.updateWith({ status: 'fail' });
                }
            });

            paymentRequest.canMakePayment().then(function (result) {
                if (result) {
                    prButton.mount('#' + div.attr('id'));
                } else {
                    document.getElementById(div.attr('id')).style.display = 'none';
                }
            });

            paymentRequest.on('source', function (ev) {
                chooseShipping(ev);
            });

            paymentRequest.on('shippingaddresschange', function (ev) {
                var urlXhr;

                if (!helper.isGuest()) {
                    urlXhr = urlBuilder.build('rest/V1/carts/' + 'mine' + '/estimate-shipping-methods');
                } else {
                    urlXhr = urlBuilder.build('rest/V1/guest-carts/' + cartId + '/estimate-shipping-methods');
                }

                // Format address
                formattedAddress = {
                    'country_id': ev.shippingAddress.country,
                    'region': ev.shippingAddress.region ? ev.shippingAddress.region : ev.shippingAddress.city,
                    'street': ev.shippingAddress.addressLine ? ev.shippingAddress.addressLine : [ev.shippingAddress.city],
                    'telephone': ev.shippingAddress.phone ? ev.shippingAddress.phone : '000000000',
                    'postcode': ev.shippingAddress.postalCode,
                    'city': ev.shippingAddress.city
                };

                $.ajax({
                    url: urlXhr,
                    data: JSON.stringify({ 'address': formattedAddress }),
                    type: 'post',
                    dataType: 'json',
                    contentType: 'application/json'
                }).done(function (result) {
                    var shippingOptions = [];

                    for (var i = 0; i < result.length; i++) {
                        shippingOptions.push({
                            'id': result[i]['carrier_code'] + '|' + result[i]['method_code'],
                            'label':
                                result[i]['carrier_title'],
                            'detail': result[i]['method_title'],
                            'amount': parseFloat(result[i]['amount']) * 100
                        });
                    }

                    var displayItems = getDisplayItems();
                    displayItems.push({ amount: shippingOptions[0].amount, label: $t('Shipping') });
                    ev.updateWith({
                        status: 'success',
                        shippingOptions: shippingOptions,
                        total: {
                            label: $t('Shopping Cart'),
                            amount: getCartTotal() * 100 + shippingOptions[0].amount,
                            pending: true
                        },
                        displayItems: displayItems
                    });
                });
            });
        };

        initStripeForm();
    };
});