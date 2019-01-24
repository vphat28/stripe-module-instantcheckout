define([
    'jquery',
    'mage/url',
    'mage/translate',
    'Stripeofficial_InstantCheckout/js/confirmation',
    'Stripeofficial_InstantCheckout/js/helper'
], function ($, urlBuilder, $t, confirmation, helper) {
    return function (config, element) {
        var initStripeForm = function () {
            var formattedAddress;
            var stripe = Stripe(config.public_key);
            var cartId = null;
            var div = $(element).find('[data-payment-element]');

            var paymentRequest = stripe.paymentRequest({
                country: config.country_code,
                currency: config.currency_code.toLowerCase(),
                total: {
                    label: $('[data-ui-id=page-title-wrapper]').text(),
                    amount: parseFloat($('[data-price-amount]').attr('data-price-amount')) * 100
                },
                requestShipping: true,
                requestPayerName: true,
                requestPayerEmail: true
            });

            var elements = stripe.elements();
            var prButton = elements.create('paymentRequestButton', {
                paymentRequest: paymentRequest
            });

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
                    if (ev.source.card.three_d_secure === 'required') {
                        window.location.href = urlBuilder.build('stripe/instantcheckout/redirect');
                    } else {
                        window.location.href = urlBuilder.build('checkout/onepage/success');
                    }
                }).fail(function () {
                    cartId = null;
                });
            };

            var chooseShipping = function (ev) {
                // Select shipping method
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
                            'shipping_method_code': shippingOption[0],
                            'shipping_carrier_code': shippingOption[1]
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

            var getCartTotal = function () {
                return parseFloat($('[data-price-amount]').attr('data-price-amount')) * parseInt($('#qty').val());
            };

            var getDisplayItems = function () {
                var displayItems = [
                    {
                        amount: getCartTotal() * 100,
                        label: $('[data-ui-id=page-title-wrapper]').text()
                    }
                ]

                return displayItems;
            };

            var validateButton = function () {
                var isGood = true;
                if ($('body').hasClass('page-product-configurable')) {
                    $('#product-options-wrapper .swatch-attribute').each(function (index) {
                        if ($(this).find('.swatch-option.selected').length === 0) {
                            isGood = false;
                        }
                    });

                    if (!isGood) {
                        $('#product-addtocart-button').trigger('click');
                    }
                }

                return isGood;
            };

            paymentRequest.canMakePayment().then(function (result) {
                if (result) {
                    prButton.mount('#' + div.attr('id'));
                } else {
                    document.getElementById(div.attr('id')).style.display = 'none';
                }
            });

            prButton.on('click', function (event) {
                paymentRequest.update({
                    total: {
                        label: $('[data-ui-id=page-title-wrapper]').text(),
                        amount: getCartTotal() * 100,
                        pending: true
                    }
                });

                if (!validateButton()) {
                    event.preventDefault();
                }
            });

            paymentRequest.on('cancel', function () {
                cartId = null;
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
                            label: $t('Total'),
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
                var calculateUrl = urlBuilder.build('stripe/instantcheckout/calculateshipping');
                var form = $('#product_addtocart_form');

                var data = {
                    'form': form.serializeArray(),
                    'shippingAddress': ev.shippingAddress
                };

                // Format address
                formattedAddress = {
                    'country_id': ev.shippingAddress.country,
                    'region': ev.shippingAddress.region ? ev.shippingAddress.region : ev.shippingAddress.city,
                    'street': ev.shippingAddress.addressLine ? ev.shippingAddress.addressLine : [ev.shippingAddress.city],
                    'telephone': ev.shippingAddress.phone ? ev.shippingAddress.phone : '000000000',
                    'postcode': ev.shippingAddress.postalCode,
                    'city': ev.shippingAddress.city
                };

                if (cartId !== null) {
                    data.cartId = cartId;
                }

                data.productType = 'simple';

                if ($('#bundleSummary').length > 0) {
                    data.productType = 'bundle';
                } else if ($('body.page-product-configurable').length) {
                    data.productType = 'configurable';
                }

                $.ajax({
                    url: calculateUrl,
                    data: data,
                    type: 'post',
                    dataType: 'json',

                    /** Show loader before send */
                    beforeSend: function () {
                        $('body').trigger('processStart');
                    }
                }).done(function (result) {
                    var displayItems = getDisplayItems();
                    displayItems.push({ amount: result.shippingOptions[0].amount, label: $t('Shipping') });
                    ev.updateWith({
                        status: 'success',
                        shippingOptions: result.shippingOptions,
                        total: {
                            label: $t('Total'),
                            amount: getCartTotal() * 100 + result.shippingOptions[0].amount,
                            pending: true
                        },
                        displayItems: displayItems
                    });

                    cartId = result.cartId;
                }).fail(function () {
                    ev.updateWith({
                        status: 'invalid_shipping_address'
                    });
                }).always(function () {
                    $('body').trigger('processStop');
                });
            });
        };

        initStripeForm();
    };
});