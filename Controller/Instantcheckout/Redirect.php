<?php

namespace Stripeofficial\InstantCheckout\Controller\InstantCheckout;

use Magento\Framework\App\Action\Action;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;

class Redirect extends Action
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * Redirect constructor.
     * @param Context $context
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * @throws \Exception
     */
    public function execute()
    {
        /** @var Order $order */
        $order = $this->checkoutSession->getLastRealOrder();
        /** @var Order\Payment $payment */
        $payment = $order->getPayment();
        $stripeRedirectUrl = $payment->getAdditionalInformation('stripe_3ds_redirect_url');

        if (@!empty($stripeRedirectUrl)) {
            $resultPage = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultPage->setPath($stripeRedirectUrl);

            return $resultPage;
        }


        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_RAW);

        $resultPage->setHttpResponseCode(404);

        return $resultPage;
    }
}
