<?php

namespace LuigisBox\SearchSuite\Cron;

use LuigisBox\SearchSuite\Helper\Helper;

class Checker
{
    protected $_indexerFactory;
    protected $_helper;

    public function __construct(
        \Magento\Indexer\Model\IndexerFactory $indexerFactory,
        Helper $helper
    ) {
        $this->_indexerFactory = $indexerFactory;
        $this->_helper = $helper;
    }

    /**
     * Check if index needs to be invalidated
     *
     * @return void
     */

    public function execute()
    {
        if (!$this->_helper->isConfigured() || $this->_helper->isIndexValid()) {
            return;
        }

        $indexer = $this->_indexerFactory->create();
        $indexer->load('luigisbox_index_product');
        $indexer->invalidate();

        $this->_helper->setIndexInvalidationTimestamp();
    }
}
