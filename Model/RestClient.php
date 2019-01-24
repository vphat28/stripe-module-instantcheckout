<?php

namespace Stripeofficial\InstantCheckout\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\UrlInterface;
use Magento\Integration\Model\Oauth\Token;
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

    /** @var Token */
    private $token = null;

    /**
     * @var Data
     */
    protected $helper; /* @codingStandardsIgnoreLine */

    /** @var UserContextInterface */
    protected $userContext;

    /** @var \Magento\Integration\Model\Oauth\TokenFactory */
    private $tokenFactory;

    /**
     * RestClient constructor.
     * @param StoreManagerInterface $storeManager
     * @param Data $helper
     * @param UserContextInterface $userContext
     * @param \Magento\Integration\Model\Oauth\TokenFactory $token
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Data $helper,
        UserContextInterface $userContext,
        \Magento\Integration\Model\Oauth\TokenFactory $tokenFactory
    )
    {
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->userContext = $userContext;
        $this->tokenFactory = $tokenFactory;
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
        $userId = null;
        /* @codingStandardsIgnoreStart */
        if (!empty($this->userContext->getUserId())) {
            $userId = $this->userContext->getUserId();
            /** @var \Magento\Integration\Model\Oauth\Token $token */
            $token = $this->tokenFactory->create();
            //$token->v();
            $token->loadByCustomerId($userId);
            $token->setCreatedAt(date('Y-m-d H:i:s'));
            $token->save();

            if (empty($token->getToken())) {
                $token->createCustomerToken($userId);
            }

            $ch = curl_init($this->baseUrl . 'rest/V1/carts/mine');
        } else {
            $ch = curl_init($this->baseUrl . 'rest/V1/guest-carts');
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = [
            'Content-Type:	application/json'
        ];

        if (!empty($userId)) {
            $headers[] = 'Authorization: Bearer ' . $token->getToken();
            $this->token = $token;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
        $userId = null;

        if (!empty($this->userContext->getUserId())) {
            $userId = $this->userContext->getUserId();
            $ch = curl_init($this->baseUrl . 'rest/V1/carts/mine/items');
        } else {
            $ch = curl_init($this->baseUrl . 'rest/V1/guest-carts/' . $cartId . '/items');
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        $headers = [
            'Content-Type:	application/json'
        ];

        if (!empty($userId)) {
            $headers[] = 'Authorization: Bearer ' . $this->token->getToken();
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
        $userId = null;

        if (!empty($this->userContext->getUserId())) {
            $userId = $this->userContext->getUserId();
            /* @codingStandardsIgnoreStart */
            $ch = curl_init($this->baseUrl . 'rest/V1/carts/mine/estimate-shipping-methods');
        } else {
            /* @codingStandardsIgnoreStart */
            $ch = curl_init($this->baseUrl . 'rest/V1/guest-carts/' . $cartId . '/estimate-shipping-methods');
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers = [
            'Content-Type:	application/json',
            'X-Requested-With: XMLHttpRequest',
        ];

        if (!empty($userId)) {
            $headers[] = 'Authorization: Bearer ' . $this->token->getToken();
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
