<?php

namespace Stripeofficial\InstantCheckout\Controller\InstantCheckout;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Stripeofficial\InstantCheckout\Model\RestClient;

class CalculateShipping extends Action
{
    /**
     * @var RestClient
     */
    protected $restClient;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * CalculateShipping constructor.
     * @param Context $context
     * @param RestClient $restClient
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(Context $context, RestClient $restClient, ProductRepositoryInterface $productRepository)
    {
        $this->restClient = $restClient;
        $this->productRepository = $productRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $request = $this->getRequest();
        $data = $request->getParams();
        $address = $data['shippingAddress'];
        $productType = 'simple';
        $configurableProductAttributes = [];
        $bundleAttributes = [];
        $bundleQtyAttributes = [];

        if (!isset($data['cartId'])) {
            $form = [];

            if ($data['productType'] == 'bundle') {
                foreach ($data['form'] as $field) {
                    $form[$field['name']] = $field['value'];

                    if (strpos($field['name'], 'bundle_option') !== false && strpos($field['name'], 'bundle_option_qty') === false) {
                        if (empty($field['value'])) {
                            $result->setHttpResponseCode(400);
                            $result->setData(false);

                            return $result;
                        }

                        $productType = 'bundle';
                        preg_match_all('/\[([0-9]*)\]/', $field['name'], $matches);
                        @$bundleAttributes[$matches[1][0]][] = $field['value'];
                    }

                    if (strpos($field['name'], 'bundle_option_qty') !== false) {
                        preg_match_all('/\[([0-9]*)\]/', $field['name'], $matches);
                        @$bundleQtyAttributes[$matches[1][0]] = $field['value'];
                    }
                }
            } elseif ($data['productType'] == 'simple') {
                foreach ($data['form'] as $field) {
                    $form[$field['name']] = $field['value'];
                }
            } else {
                // type configurable
                $customizableOptions = [];
                foreach ($data['form'] as $field) {
                    $form[$field['name']] = $field['value'];

                    if (strpos($field['name'], 'super_attribute') !== false) {
                        if (empty($field['value'])) {
                            $result->setHttpResponseCode(400);
                            $result->setData(false);

                            return $result;
                        }

                        $productType = 'configurable';
                        preg_match('/\[(.*?)\]/', $field['name'], $matches);
                        $configurableProductAttributes[$matches[1]] = $field['value'];
                    }

                    if (strpos($field['name'], 'options') !== false) {
                        if (empty($field['value'])) {
                            $result->setHttpResponseCode(400);
                            $result->setData(false);

                            return $result;
                        }

                        preg_match('/\[(.*?)\]/', $field['name'], $matches);
                        $customizableOptions[$matches[1]] = $field['value'];
                    }
                }
            }


            $productId = @$form['product'];

            try {
                $product = $this->productRepository->getById($productId);
            } catch (\Exception $e) {
                $result->setHttpResponseCode(400);
                $result->setData(false);

                return $result;
            }

            $cartid = $this->restClient->createCart();

            $json = [
                'cartItem' => [
                    'sku' => $product->getSku(),
                    'quoteId' => $cartid,
                    'qty' => $form['qty'],
                ]
            ];

            if ($productType == 'simple') {
                $customizableOptions = [];

                foreach ($data['form'] as $field) {
                    if (strpos($field['name'], 'options') !== false) {
                        if (empty($field['value'])) {
                            $result->setHttpResponseCode(400);
                            $result->setData(false);

                            return $result;
                        }

                        preg_match('/\[(.*?)\]/', $field['name'], $matches);
                        $customizableOptions[$matches[1]] = $field['value'];
                    }
                }

                if (!empty($customizableOptions)) {
                    $json['cartItem']['productOption'] = ['extensionAttributes' => ['custom_options' => []]];
                    $json['cartItem']['productType'] = 'simple';

                    foreach ($customizableOptions as $attribute => $value) {
                        $json['cartItem']['productOption']['extensionAttributes']['custom_options'][] = [
                            'option_id' => $attribute,
                            'option_value' => $value,
                        ];
                    }
                }
            }

            if ($productType == 'configurable') {
                $json['cartItem']['productOption'] = ['extensionAttributes' => ['configurableItemOptions' => []]];
                $json['cartItem']['productType'] = 'configurable';

                foreach ($configurableProductAttributes as $attribute => $value) {
                    $json['cartItem']['productOption']['extensionAttributes']['configurableItemOptions'][] = [
                        'optionId' => $attribute,
                        'optionValue' => $value,
                    ];
                }

                if (!empty($customizableOptions)) {
                    $json['cartItem']['productOption']['extensionAttributes']['custom_options'] = [];

                    foreach ($customizableOptions as $attribute => $value) {
                        $json['cartItem']['productOption']['extensionAttributes']['custom_options'][] = [
                            'option_id' => $attribute,
                            'option_value' => $value,
                        ];
                    }
                }
            }

            // Proceed bundle options
            if ($productType == 'bundle') {
                $json['cartItem']['productOption'] = ['extensionAttributes' => ['bundle_options' => []]];
                $json['cartItem']['productType'] = 'bundle';

                foreach ($bundleAttributes as $attribute => $value) {
                    $json['cartItem']['productOption']['extensionAttributes']['bundle_options'][] = [
                        'option_id' => $attribute,
                        'option_qty' => @isset($bundleQtyAttributes[$attribute]) ? $bundleQtyAttributes[$attribute] : 0,
                        'option_selections' => $value,
                    ];
                }
            }

            $this->restClient->addToCart($cartid, $json);
        } else {
            $cartid = $data['cartId'];
        }

        $formattedAddress = [];
        $formattedAddress['country_id'] = $address['country'];
        $formattedAddress['region'] = $address['region'];
        // Street is required in magento to calculate rates
        // so we use city as workaround if addressLine not available
        $formattedAddress['street'] = @isset($address['addressLine']) ? $address['addressLine'] : [$address['city']];

        // Workaround when phone is empty in Apple pay
        $formattedAddress['telephone'] = @isset($address['phone']) ? $address['phone'] : '000000';
        $formattedAddress['postcode'] = $address['postalCode'];
        $formattedAddress['city'] = $address['city'];

        $rates = $this->restClient->estimateShippingRates($cartid, ['address' => $formattedAddress, 'cartId' => $cartid]);
        $rates = json_decode($rates, true);
        $formattedRates = [];

        foreach ($rates as $rate) {
            $formattedRates[] = [
                'id' => $rate['carrier_code'] . '|' . $rate['method_code'],
                'label' => $rate['carrier_title'],
                'detail' => $rate['method_title'],
                'amount' => (float)$rate['amount'] * 100,
            ];
        }

        $result->setData([
            'shippingOptions' => $formattedRates,
            'cartId' => $cartid,
        ]);

        return $result;
    }
}
