<?php

namespace Stripeofficial\InstantCheckout\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class TxnIdHandler implements HandlerInterface
{
    /**
     * Handles transaction id
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        if ($response['object'] == 'refund') {
            $this->handleRefund($handlingSubject, $response);
            return;
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];

        $payment = $paymentDO->getPayment();

        // Attach charge id to payment object
        /** @var $payment \Magento\Sales\Model\Order\Payment */
        $payment->setTransactionId($response['id']);

        if (@$response['object'] == 'charge') {
            $payment->setAdditionalInformation('base_charge_id', $response['id']);
        }

        $payment->setIsTransactionClosed(false);

        // Set order metadata
        $order = $payment->getOrder();

        if ($response['object'] == 'charge') {
            $order->setData('stripe_charge_id', $response['id']);
        }

        if ($response['captured']) {
            $payment->setAdditionalInformation('stripe_captured', true);
        } else {
            $order->setData('stripe_uncaptured', true);
        }

        // If this is a source object store it in database
        if (@$response['object'] == 'source') {
            $order->setData('stripe_source_id', $response['id']);
        }

        if (@$response['flow'] == 'redirect') {
            $stripeData['3ds_redirect_url'] = $response['redirect']['url'];
            $payment->setAdditionalInformation('stripe_redirect_url', $response['redirect']['url']);
        }
    }

    /**
     * Handle refund request
     * @param array $handlingSubject
     * @param array $response
     */
    private function handleRefund($handlingSubject, $response)
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];
        $payment = $paymentDO->getPayment();
        $payment->setAdditionalInformation('stripe_refunded', true);
        $payment->setTransactionId($response['id']);
        $payment->setIsTransactionClosed(false);
    }
}
