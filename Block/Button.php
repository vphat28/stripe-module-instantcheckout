<?php

namespace Stripeofficial\InstantCheckout\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Api\Data\StoreInterface;
use Stripeofficial\Core\Helper\Data;

class Button extends Template
{
    /**
     * @var Data
     */
    protected $helper; // @codingStandardsIgnoreLine

    /**
     * Button constructor.
     * @param Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    /**
     * @return Data
     */
    public function getHelper()
    {
        return $this->helper;
    }

    /**
     * @return StoreInterface
     */
    public function getStore()
    {
        return $this->_storeManager->getStore();
    }

    /**
     * @return ScopeConfigInterface
     */
    public function getScopeConfig()
    {
        return $this->_scopeConfig;
    }

    public function toHtml()
    {
        if (!$this->_scopeConfig->isSetFlag('payment/stripeinstantcheckout/active')) {
            return null;
        }

        return parent::toHtml();
    }
}
