<?php

namespace Stripeofficial\InstantCheckout\Block;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment;
use Stripeofficial\Core\Block\Info as CoreInfo;

class Info extends CoreInfo
{
    /**
     * Get some specific information in format of array($label => $value)
     *
     * @return array
     * @throws LocalizedException
     */
    public function getSpecificInformation()
    {
        $info = parent::getSpecificInformation();

        if ($this->getIsSecureMode()) {
            /** @var Payment $payment */
            $payment = $this->getInfo();

            try {
                $chargeId = $payment->getAdditionalInformation('base_charge_id');
                $charge = $this->creditCardPayment->getCharge($chargeId)->jsonSerialize();
            } catch (\Exception $e) {
                $this->_logger->error(__('No charge found'));
            }

            $additional = [];

            if (isset($charge['source']['type'])) {
                if ($charge['source']['type'] == 'card') {
                    $additional['Last 4 digits'] = $charge['source']['card']['last4'];
                }
            }

            if (!empty($charge['id'])) {
                $additional['Charge ID'] = $charge['id'];
                $additional['Source ID'] = $charge['source']['id'];
            }
            
            $info = array_merge($info, $additional);
        }

        return $info;
    }
}
