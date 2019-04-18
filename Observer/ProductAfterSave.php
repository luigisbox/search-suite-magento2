<?php

namespace LuigisBox\SearchSuite\Observer;

use LuigisBox\SearchSuite\Helper\Helper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class ProductAfterSave implements ObserverInterface
{
    protected $_logger;

    protected $_helper;

    public function __construct(
        LoggerInterface $logger,
        Helper $helper
    ) {
        $this->_logger = $logger;
        $this->_helper = $helper;
    }

    public function execute(Observer $observer)
    {
        $product = $observer->getData('product');

        $this->_logger->debug(sprintf('LuigisBox index product %d', $product->getEntityId()));

        $this->_helper->singleContentUpdate($product->getEntityId());
    }
}
