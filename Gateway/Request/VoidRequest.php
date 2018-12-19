<?php

namespace Stripeofficial\InstantCheckout\Gateway\Request;

use Magento\Sales\Model\Order\Payment;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class VoidRequest implements BuilderInterface
{
    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];

        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        $transactionId = $payment->getLastTransId();

        $amount = $payment->getBaseAmountAuthorized();

        return [
            'TXN_TYPE' => 'refund',
            'TXN_ID' => $transactionId,
            'REFUND_AMOUNT' => $amount,
        ];
    }
}
