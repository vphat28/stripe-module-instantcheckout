<?php

namespace Stripeofficial\InstantCheckout\Model\Payment;

use Magento\Payment\Model\Method\Adapter;

class InstantCheckout extends Adapter
{
    const METHOD_CODE = 'stripeinstantcheckout';
}
