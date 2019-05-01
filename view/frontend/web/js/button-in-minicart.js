define(
    [
        'uiComponent',
        'jquery',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/checkout-data',
        'underscore',
        'mage/url',
        'mage/translate',
        'Stripeofficial_InstantCheckout/js/confirmation',
        'Stripeofficial_InstantCheckout/js/helper'
    ],
    function (Component, $, Data, Checkout, _, urlBuilder, $t, confirmation, helper) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Stripeofficial_InstantCheckout/button-in-cart'
            },

            initStripe: function () {
                if (StripeInstantCheckout.enable === false) {
                    return;
                }
                var config = {};
                var cartId;
                var formattedAddress;
                var initStripeForm = function () {
                    var stripe = Stripe(config.public_key);
                    var div = $('#stripe-payment-request-button-minicart');
                    div.appendTo('.block-minicart .block-content > .actions');

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
                            Data.set('cart', {});

                            if (ev.source.card.three_d_secure === 'required') {
                                window.location.href = urlBuilder.build('stripe/instantcheckout/redirect');
                            } else {
                                window.location.href = urlBuilder.build('checkout/onepage/success');
                            }
                        })
                    };

                    var chooseShipping = function (ev) {
                        var urlXhr;

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
                        }).done(function (returnData) {
                            // Close payment sheet with success message
                            ev.complete('success');
                            confirmation({
                                title: $t('Checkout Confirmation'),
                                data: {
                                    address: ev.shippingAddress,
                                    paymentMethod: ev.source,
                                    shippingMethod: ev.shippingOption.label,
                                    total: returnData.totals
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

                    var paymentRequest = stripe.paymentRequest({
                        country: config.country_code,
                        currency: config.currency_code.toLowerCase(),
                        total: {
                            label: $t('Shopping Cart'),
                            amount: 1,
                            pending: true
                        },
                        requestShipping: true,
                        requestPayerName: true,
                        requestPayerEmail: true
                    });

                    var elements = stripe.elements();
                    var prButton = elements.create('paymentRequestButton', {
                        paymentRequest: paymentRequest
                    });

                    var getCartTotal = function () {
                        var cartItems = JSON.parse(window.localStorage['mage-cache-storage']).cart.items;
                        var total = 0;

                        for (var i = 0; i < cartItems.length; i++) {
                            total += (cartItems[i].qty * cartItems[i].product_price_value);
                        }

                        return total;
                    };

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

                    prButton.on('click', function () {
                        var total = getCartTotal();

                        paymentRequest.update({
                            total: {
                                label: $t('Shopping Cart'),
                                amount: total * 100,
                                pending: true
                            },
                            displayItems: getDisplayItems()
                        });
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

                $.ajax({
                    url: urlBuilder.build('stripe/instantcheckout/config'),
                    data: {},
                    type: 'get',
                    dataType: 'json'
                }).done(function (data) {
                    config.public_key = data.api;
                    config.currency_code = data.currencyCode;
                    config.country_code = data.countryCode;
                    cartId = data.quoteId;
                    initStripeForm();
                });
            }
        });
    }
);