<?php

namespace Stripeofficial\InstantCheckout\Controller\InstantCheckout;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use Stripeofficial\Core\Helper\Data;
use Stripeofficial\InstantCheckout\Helper\Data as ICHelper;

class Config extends Action
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ICHelper
     */
    protected $icHelper;

    /**
     * Config constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param Session $session
     * @param Data $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param ICHelper $icHelper
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        Session $session,
        Data $helper,
        ScopeConfigInterface $scopeConfig,
        ICHelper $icHelper
    ) {
        $this->storeManager = $storeManager;
        $this->session = $session;
        $this->helper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->icHelper = $icHelper;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $currencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
        $quoteId = $this->session->getQuoteId();

        if (!empty($quoteId) && !$this->icHelper->isCustomerLogged()) {
            $quoteId = $this->icHelper->getQuoteMaskIdFromQuoteId($quoteId);
        }

        $resultPage->setData([
            'currencyCode' => $currencyCode,
            'quoteId' => $quoteId,
            'api' => $this->helper->getAPIKey(),
            'countryCode' => $this->scopeConfig->getValue('general/country/default'),
            'isLogged' => $this->icHelper->isCustomerLogged(),
            ]);

        return $resultPage;
    }
}
