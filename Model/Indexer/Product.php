<?php

namespace LuigisBox\SearchSuite\Model\Indexer;

use LuigisBox\SearchSuite\Helper\Helper;
use Psr\Log\LoggerInterface;

class Product implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    private $_logger;
    private $_helper;

    public function __construct(
        LoggerInterface $logger,
        Helper $helper
    ) {
        $this->_logger = $logger;
        $this->_helper = $helper;
    }

    public function execute($ids)
    {
    }

    public function executeFull()
    {
        if (!$this->_helper->isConfigured()) {
            $this->_logger->debug('Luigi\'s Box not configured, can not index products');
            return;
        }

        // Run reindex if index timestamp is invalidated and
        if (!$this->_helper->isIndexValid() || $this->_helper->isIndexRunning() || $this->_helper->isIndexFinished()) {
            $this->_logger->debug('Luigi\'s Box reindex preventing run');
            return;
        }

        $this->_helper->markIndexRunning();

        $this->_helper->allContentUpdate();

        $this->_helper->markIndexFinished();
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
