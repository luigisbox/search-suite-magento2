<?php

namespace LuigisBox\SearchSuite\Cron;

use LuigisBox\SearchSuite\Helper\Helper;
use Psr\Log\LoggerInterface;

class IndexInvalidator
{
    protected $_indexerFactory;
    protected $_helper;
    protected $_logger;

    public function __construct(
        \Magento\Indexer\Model\IndexerFactory $indexerFactory,
        LoggerInterface $logger,
        Helper $helper
    ) {
        $this->_indexerFactory = $indexerFactory;
        $this->_helper = $helper;
        $this->_logger = $logger;
    }

    /**
     * Invalidates Luigi's Box indexer
     *
     * @return void
     */

    public function execute()
    {
        $this->_logger->info("Luigi's Box index invalidator triggered");
        if (!$this->_helper->isConfigured()) {
            return;
        }

        $indexer = $this->_indexerFactory->create();
        $indexer->load('luigisbox_reindex');
        $indexer->invalidate();
    }
}
