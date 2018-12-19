<?php


namespace Stripeofficial\InstantCheckout\Block\Adminhtml\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Model\Auth\Session;

/**
 * Show tokens for admin order
 */
class Fieldset extends \Magento\Config\Block\System\Config\Form\Fieldset
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    
    /**
     * @param \Magento\Backend\Block\Context $context
     * @param Session $authSession
     * @param \Magento\Framework\View\Helper\Js $jsHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Context $context,
        Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $authSession, $jsHelper, $data);
    }

    /**
     * Render fieldset html
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $this->setElement($element);
        $header = $this->_getHeaderHtml($element);

        $elements = $this->_getChildrenElementsHtml($element);

        $footer = $this->_getFooterHtml($element);

        return $header . $elements . $footer;
    }

    /**
     * Return header html for fieldset
     *
     * @param AbstractElement $element
     * @return string
     */
    public function _getHeaderHtml($element)
    {
        if ($element->getIsNested()) {
            $html = '<tr class="nested"><td colspan="4"><div class="' . $this->_getFrontendClass($element) . '">';
        } else {
            $html = '<div class="' . $this->_getFrontendClass($element) . '">';
        }

        $html .= '<div class="entry-edit-head admin__collapsible-block">' .
            '<span id="' .
            $element->getHtmlId() .
            '-link" class="entry-edit-head-link"></span>';

        $html .= $this->_getHeaderTitleHtml($element);

        $html .= '</div>';
        $html .= '<input id="' .
            $element->getHtmlId() .
            '-state" name="config_state[' .
            $element->getId() .
            ']" type="hidden" value="' .
            (int)$this->_isCollapseState(
                $element
            ) . '" />';
        $html .= '<fieldset class="config admin__collapsible-block" id="' . $element->getHtmlId() . '"><br />';
        $html .= '<legend>' . $element->getLegend().'</legend>';
        
        $html .= $this->_getHeaderCommentHtml($element);

        // field label column
        $html .= '<table cellspacing="0" class="form-list"><colgroup class="label" /><colgroup class="value" />';
        if ($this->getRequest()->getParam('website') || $this->getRequest()->getParam('store')) {
            $html .= '<colgroup class="use-default" />';
        }
        $html .= '<colgroup class="scope-label" /><colgroup class="" /><tbody>';
        
        $html .= '<div>
            <img src="'.$this->getViewFileUrl('Stripeofficial_Core::stripe/apple-pay.png').'" />
            <img src="'.$this->getViewFileUrl('Stripeofficial_Core::stripe/g-pay.png').'" />
        </div>';

        return $html;
    }
    
    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getChildrenElementsHtml(AbstractElement $element)
    {
        $elements = '';
        foreach ($element->getElements() as $field) {
            if ($field instanceof \Magento\Framework\Data\Form\Element\Fieldset) {
                $elements .= '<tr id="row_' . $field->getHtmlId() . '">'
                    . '<td colspan="4">' . $field->toHtml() . '</td></tr>';
            } else {
                $elements .= $field->toHtml();
            }
        }

        return $elements;
    }
}
