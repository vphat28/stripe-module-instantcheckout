<?php

namespace Stripeofficial\InstantCheckout\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Stripe\Charge;

class ResponseCodeValidator extends AbstractValidator
{
    const SUCCESS_CODE = 'succeeded';

    /**
     * Performs validation of result code
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];

        if ($this->isSuccessfulTransaction($response)) {
            return $this->createResult(
                true,
                []
            );
        } else {
            return $this->createResult(
                false,
                [__('Gateway rejected the transaction.')]
            );
        }
    }

    /**
     * @param Charge $response
     * @return bool
     */
    private function isSuccessfulTransaction($response)
    {
        if ($response['status'] == self::SUCCESS_CODE) {
            return true;
        }

        if ($response['type'] == 'three_d_secure') {
            return true;
        }

        return false;
    }
}
