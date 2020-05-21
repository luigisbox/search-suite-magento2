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
        $this->_logger->debug('Luigi\'s Box Indexer triggered');

        if (!$this->_helper->isConfigured()) {
            $this->_logger->debug('Luigi\'s Box not configured, can not index products');
            return;
        }

        foreach ($ids as $id) {
            $this->_helper->singleContentUpdate($id);
        }
        $this->_logger->debug('Luigi\'s Box Indexer finished');
    }

    public function executeFull()
    {
        if (!$this->_helper->isConfigured()) {
            $this->_logger->debug('Luigi\'s Box not configured, can not index products');
            return;
        }

        $this->_helper->allContentUpdate();

    }

    public function executeList(array $ids)
    {
        $this->_logger->debug('Luigi\'s Box Indexer triggered');

        if (!$this->_helper->isConfigured()) {
            $this->_logger->debug('Luigi\'s Box not configured, can not index products');
            return;
        }

        foreach ($ids as $id) {
            $this->_helper->singleContentUpdate($id);
        }
        $this->_logger->debug('Luigi\'s Box Indexer finished');
    }

    public function executeRow($id)
    {
        if (!$this->_helper->isConfigured()) {
            $this->_logger->debug('Luigi\'s Box not configured, can not index products');
            return;
        }
        $this->_helper->singleContentUpdate($id);
    }
}
