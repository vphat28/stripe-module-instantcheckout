<?php

namespace Stripeofficial\InstantCheckout\Block;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\View\Element\Template;
use Stripeofficial\InstantCheckout\Helper\Data;

// @codingStandardsIgnoreFile
class Config extends Template
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var FormatInterface
     */
    protected $format;

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    private $scopeConfig;

    /**
     * Config constructor.
     * @param Template\Context $context
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param Data $helper
     * @param FormatInterface $format
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        Data $helper,
        FormatInterface $format,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
        $this->customerSession = $customerSession;
        $this->scopeConfig = $context->getScopeConfig();
        $this->format = $format;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConfig()
    {
        $customerId = $this->customerSession->getId();
        $quoteId = $this->checkoutSession->getQuoteId();
        $basePriceFormat = $this->format->getPriceFormat(
            null,
            $this->_storeManager->getStore()->getBaseCurrencyCode()
        );

        if (empty($customerId) && !empty($quoteId)) {
            $quoteId = $this->helper->getQuoteMaskIdFromQuoteId($quoteId);
        }

        return [
            'cartId' => $quoteId,
            'basePriceFormat' => $basePriceFormat,
            'is_guest' => $customerId ? false : true,
            'enable' => $this->scopeConfig->isSetFlag('payment/stripeinstantcheckout/active')
        ];
    }
}
