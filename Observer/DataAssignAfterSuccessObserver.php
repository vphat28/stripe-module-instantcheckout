<?php

namespace Stripeofficial\InstantCheckout\Observer;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order;
use Stripeofficial\Core\Api\PaymentInterface;
use Stripeofficial\Core\Model\ResourceModel\Source as SourceRS;
use Stripeofficial\Core\Model\SourceFactory;
use Stripeofficial\InstantCheckout\Model\Payment\InstantCheckout;

class DataAssignAfterSuccessObserver implements ObserverInterface
{
    /**
     * @var PaymentInterface
     */
    private $creditCardPayment;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SourceRS
     */
    private $sourceRs;

    /**
     * @var SourceFactory
     */
    private $sourceFactory;

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    private $historyRepository;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * DataAssignAfterSuccessObserver constructor.
     * @param PaymentInterface $creditCardPayment
     * @param OrderRepositoryInterface $orderRepository
     * @param SourceRS $sourceRs
     * @param SourceFactory $sourceFactory
     * @param OrderStatusHistoryRepositoryInterface $historyRepository
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        PaymentInterface $creditCardPayment,
        OrderRepositoryInterface $orderRepository,
        SourceRS $sourceRs,
        SourceFactory $sourceFactory,
        OrderStatusHistoryRepositoryInterface $historyRepository,
        ManagerInterface $eventManager
    ) {
        $this->creditCardPayment = $creditCardPayment;
        $this->orderRepository = $orderRepository;
        $this->sourceFactory = $sourceFactory;
        $this->sourceRs = $sourceRs;
        $this->historyRepository = $historyRepository;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Observer $observer
     * @throws
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getOrder();
        $orders = $observer->getOrders();

        if (!empty($orders)) {
            // Multishipping checkout
            return;
        }
        $payment = $order->getPayment();

        if ($payment->getMethod() != InstantCheckout::METHOD_CODE) {
            return;
        }

        if ($order->getData('stripe_uncaptured')) {
            $order->setStatus(Order::STATE_PENDING_PAYMENT);
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $order->setData('stripe_uncaptured');
        }

        // If found stripe source id store it in database
        if ($order->getData('stripe_source_id')) {
            $source = $this->sourceFactory->create();
            $source->setData('source_id', $order->getData('stripe_source_id'));
            $source->setData('reference_order_id', $order->getId());
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $order->setStatus(Order::STATE_PENDING_PAYMENT);
            $payment->setAmountAuthorized(0);
            $comments = $order->getAllStatusHistory();

            foreach ($comments as $comment) {
                /** @var Order\Status\History $comment */
                $this->historyRepository->delete($comment);
            }

            $this->sourceRs->save($source);
        }

        $this->orderRepository->save($order);

        $metadata = [
            'Magento Order ID' => $order->getIncrementId(),
            'customer_name' => $order->getCustomerName(),
            'customer_email' => $order->getCustomerEmail(),
            'order_id' => $order->getId(),
        ];
        $chargeId = $order->getData('stripe_charge_id');

        if (!empty($chargeId)) {
            $this->eventManager->dispatch('stripe_charge_completed', ['order' => $order, 'charge_id' => $chargeId]);
            $this->creditCardPayment->updateChargeMetadata($chargeId, $metadata, $order);
        }
    }
}
