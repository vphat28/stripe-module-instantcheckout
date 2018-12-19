<?php

namespace Stripeofficial\InstantCheckout\Gateway\Request;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class AuthorizationRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * AuthorizationRequest constructor.
     * @param ConfigInterface $config
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        ConfigInterface $config,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->config = $config;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Builds ENV request
     *
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
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();
        $paymentAdditionalInformation = $payment->getAdditionalInformation();
        $address = $order->getShippingAddress();

        if (!$payment instanceof OrderPaymentInterface) {
            throw new \LogicException('Order payment should be provided.');
        }

        return [
            'TXN_TYPE' => 'authorize',
            'STRIPE_TOKEN' => $paymentAdditionalInformation['stripeToken'],
            'TXN_ID' => $payment->getLastTransId(),
            'CURRENCY_CODE' => $order->getCurrencyCode(),
            'CUSTOMER_ID' => $order->getCustomerId(),
            'CUSTOMER_EMAIL' => $address->getEmail(),
            'AMOUNT' => $payment->getAmountOrdered(),
            'ADDITIONAL_INFO' => $paymentAdditionalInformation,
        ];
    }
}
