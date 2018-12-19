<?php

namespace Stripeofficial\InstantCheckout\Model;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stripeofficial\InstantCheckout\Helper\Data;

class RestClient
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager; /* @codingStandardsIgnoreLine */

    /**
     * @var string
     */
    protected $baseUrl; /* @codingStandardsIgnoreLine */

    /**
     * @var Data
     */
    protected $helper; /* @codingStandardsIgnoreLine */

    /**
     * RestClient constructor.
     * @param StoreManagerInterface $storeManager
     * @param Data $helper
     */
    public function __construct(StoreManagerInterface $storeManager, Data $helper)
    {
        $this->storeManager = $storeManager;
        $this->helper = $helper;
    }

    private function _init()
    {
        $this->baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
    }

    /**
     * Create cart
     * @return string
     */
    public function createCart()
    {
        $this->_init();
        /* @codingStandardsIgnoreStart */
        $ch = curl_init($this->baseUrl . 'rest/V1/guest-carts');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type:	application/json'
        ]);

        $result = curl_exec($ch);
        /* @codingStandardsIgnoreEnd */
        $cartid = (string)(json_decode($result));

        return $cartid;
    }

    /**
     * @param string $cartId
     * @param array $cartItem
     * @return string
     */
    public function addToCart($cartId, $cartItem)
    {
        $this->_init();
        /* @codingStandardsIgnoreStart */
        $json = json_encode($cartItem);
        $ch = curl_init($this->baseUrl . 'rest/V1/guest-carts/' . $cartId . '/items');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type:	application/json'
        ]);

        $resultCh = curl_exec($ch);
        /* @codingStandardsIgnoreEnd */

        return (string)$resultCh;
    }

    /**
     * @param string $cartId
     * @param array $address
     * @return string
     */
    public function estimateShippingRates($cartId, $address)
    {
        $this->_init();
        $json = json_encode($address);

        /* @codingStandardsIgnoreStart */
        $ch = curl_init($this->baseUrl . 'rest/V1/guest-carts/' . $cartId . '/estimate-shipping-methods');

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type:	application/json',
            'X-Requested-With: XMLHttpRequest',
        ]);

        $resultCh = curl_exec($ch);
        /* @codingStandardsIgnoreEnd */

        return (string)$resultCh;
    }

    /**
     * @param $cartId
     * @param array $addressInformation
     * @return string
     */
    public function checkoutShipping($cartId, $addressInformation)
    {
        $this->_init();
        /* @codingStandardsIgnoreStart */
        $json = json_encode($addressInformation);
        $ch = curl_init($this->baseUrl . 'rest/V1/guest-carts/' . $cartId . '/shipping-information');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type:	application/json'
        ]);

        $resultCh = curl_exec($ch);
        /* @codingStandardsIgnoreEnd */

        return (string)$resultCh;
    }

    /**
     * @param $cartId
     * @param array $paymentInformation
     * @return string
     */
    public function checkoutPayment($cartId, $paymentInformation)
    {
        $this->_init();
        /* @codingStandardsIgnoreStart */
        $json = json_encode($paymentInformation);
        $ch = curl_init($this->baseUrl . 'rest/V1/guest-carts/' . $cartId . '/payment-information');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type:	application/json'
        ]);

        $resultCh = curl_exec($ch);
        /* @codingStandardsIgnoreEnd */
        return (string)$resultCh;
    }
}
