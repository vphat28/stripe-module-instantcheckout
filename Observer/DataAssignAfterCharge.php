<?php

namespace Stripeofficial\InstantCheckout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Stripeofficial\Core\Model\ResourceModel\Charge as ChargeResource;
use Stripeofficial\Core\Model\ChargeFactory;

class DataAssignAfterCharge implements ObserverInterface
{
    /**
     * @var ChargeResource
     */
    private $chargeResource;

    /**
     * @var ChargeFactory
     */
    private $chargeFactory;

    /**
     * DataAssignAfterCharge constructor.
     * @param ChargeResource $chargeResource
     * @param ChargeFactory $chargeFactory
     */
    public function __construct(ChargeResource $chargeResource, ChargeFactory $chargeFactory)
    {
        $this->chargeResource = $chargeResource;
        $this->chargeFactory = $chargeFactory;
    }

    /**
     * @param Observer $observer
     * @throws \Exception
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getData('order');
        $chargeId = $observer->getData('charge_id');
        $payment = $order->getPayment();
        $payment->setAdditionalData('base_charge_id', $chargeId);

        // Saving charge to database
        $charge = $this->chargeFactory->create();
        $charge->setData('charge_id', $chargeId);
        $charge->setData('reference_order_id', $order->getId());
        $this->chargeResource->save($charge);
    }
}
