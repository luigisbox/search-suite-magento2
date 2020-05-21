<?php
namespace LuigisBox\SearchSuite\Controller\Adminhtml\Request;

use LuigisBox\SearchSuite\Helper\Helper as LuigisBoxHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Reindex extends Action
{
    protected $_jsonFactory;
    protected $_luigisBoxHelper;

    /**
     * @param Context $context
     * @param LuigisBoxHelper $luigisBoxHelper
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        LuigisBoxHelper $luigisBoxHelper,
        JsonFactory $jsonFactory
    ) {
        $this->_luigisBoxHelper = $luigisBoxHelper;
        $this->_jsonFactory = $jsonFactory;
        parent::__construct($context);
    }

    /**
     * Collect relations data
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $this->_luigisBoxHelper->invalidateIndex();

        $result = $this->_jsonFactory->create();

        return $result->setData(['success' => true]);
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Luigisbox_LuigisboxSearch::config');
    }
}
