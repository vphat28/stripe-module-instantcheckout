<?php

namespace Stripeofficial\InstantCheckout\Gateway\Http\Client;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Stripeofficial\Core\Helper\Data;
use Stripeofficial\Core\Model\Logger;
use Stripeofficial\Core\Api\PaymentInterface;
use Stripe\Refund;
use Stripe\Source;
use Stripe\Stripe;

class ClientMock implements ClientInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var PaymentInterface
     */
    protected $creditCardPayment;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Data
     */
    protected $data;

    /**
     * ClientMock constructor.
     * @param PaymentInterface $creditCardPayment
     * @param CustomerRepositoryInterface $customerRepository
     * @param UrlInterface $urlBuilder
     * @param Data $data
     */
    public function __construct(
        PaymentInterface $creditCardPayment,
        CustomerRepositoryInterface $customerRepository,
        UrlInterface $urlBuilder,
        Data $data
    ) {
        $this->creditCardPayment = $creditCardPayment;
        $this->customerRepository = $customerRepository;
        $this->urlBuilder = $urlBuilder;
        $this->data = $data;
    }

    /**
     * @param array $body
     * @return array
     * @throws LocalizedException
     */
    private function place3DSRequest($body)
    {
        try {
            $returnUrl = $this->urlBuilder->getUrl('stripe/cc/returnurl');
            $response = $this->creditCardPayment->create3DSSource($body['STRIPE_TOKEN'], $body['AMOUNT'] * 100, $body['CURRENCY_CODE'], $returnUrl);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        $response = $response->jsonSerialize();
        $response['captured'] = false;

        return $response;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     * @throws LocalizedException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $body = $transferObject->getBody();

        if ($body['TXN_TYPE'] == 'refund') {
            return $this->placeRefundRequest($body);
        }

        if ($body['TXN_TYPE'] == 'invoice_refund_only') {
            return $this->getRefund($body['TXN_ID']);
        }

        if ($body['TXN_TYPE'] == 'capture') {
            return $this->placeCaptureOnlyRequest($body);
        }

        if ($body['TXN_TYPE'] == 'authorize') {
            $capture = false;
        } elseif ($body['TXN_TYPE'] == 'authorize_capture') {
            $capture = true;
        }

        $customerStripe = null;

        // Try to get customer id from database
        if (!empty($body['CUSTOMER_ID'])) {
            $customer = $this->customerRepository->getById($body['CUSTOMER_ID']);
            $customerStripe = $customer->getCustomAttribute('stripe_customer_id') === null ? null : $customer->getCustomAttribute('stripe_customer_id')->getValue();
        }

        if (empty($customerStripe) and !empty($body['CUSTOMER_ID'])) {
            $customerStripe = $this->creditCardPayment->createCustomerToken($body['CUSTOMER_EMAIL']);
            $customerStripe = $customerStripe->id;
        }

        // Try to save id to customer object
        if (!empty($body['CUSTOMER_ID'])) {
            $customer->setCustomAttribute('stripe_customer_id', $customerStripe);
            $this->customerRepository->save($customer);
        }

        // Check for 3DS
        $sourceObject = $this->creditCardPayment->getSource($body['STRIPE_TOKEN'])->jsonSerialize();

        if (@$sourceObject['card']['three_d_secure'] === 'required') {
            return $this->place3DSRequest($body);
        }

        try {
            $response = $this->creditCardPayment->charge($capture, $body['STRIPE_TOKEN'], $body['AMOUNT'] * 100, $body['CURRENCY_CODE'], $customerStripe);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        if ($this->data->getDebugMode()) {
            $this->data->getLogger()->debug(
                'API call',
                [
                    'request' => $transferObject->getBody(),
                    'response' => $response
                ]
            );
        }

        return $response->jsonSerialize();
    }

    /**
     * @param array $body
     * @return array
     * @throws LocalizedException
     */
    private function placeRefundRequest($body)
    {
        try {
            $response = $this->creditCardPayment->refund($body['TXN_ID'], $body['REFUND_AMOUNT'] * 100);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        return $response->jsonSerialize();
    }

    /**
     * @param array $body
     * @return array
     * @throws LocalizedException
     */
    private function placeCaptureOnlyRequest($body)
    {
        try {
            $response = $this->creditCardPayment->capture($body['TXN_ID'], $body['AMOUNT'] * 100);
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        return $response->jsonSerialize();
    }

    private function getRefund($id)
    {
        Stripe::setApiKey($this->data->getAPISecretKey());
        return Refund::retrieve($id)->jsonSerialize();
    }
}
