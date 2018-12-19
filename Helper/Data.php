<?php

namespace Stripeofficial\InstantCheckout\Helper;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\ResourceConnection;

class Data
{
    /**
     * @var AdapterInterface
     */
    protected $connection;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * Data constructor.
     * @param ResourceConnection $resourceConnection
     * @param CustomerSession $customerSession
     */
    public function __construct(ResourceConnection $resourceConnection, CustomerSession $customerSession)
    {
        $this->connection = $resourceConnection->getConnection();
        $this->customerSession = $customerSession;
    }

    /**
     * @return bool
     */
    public function isCustomerLogged()
    {
        $customerId = $this->customerSession->getId();

        return $customerId ? true : false;
    }

    /**
     * @return mixed|null
     */
    public function getCustomerData()
    {
        if ($this->isCustomerLogged()) {
            return $this->customerSession->getData();
        }

        return null;
    }

    /**
     * @param $quoteId
     * @return mixed
     */
    public function getQuoteMaskIdFromQuoteId($quoteId)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        
        $tableName = $resource->getTableName('quote_id_mask');
	$query = $connection->select()->from($tableName)->where('quote_id=?', $quoteId);
        $result = $connection->fetchAll($query);

        if (is_array($result) && !empty($result)) {
            return $result[0]['masked_id'];
        }

        return null;
    }
}
