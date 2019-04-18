Luigi's Box Search Suite for Magento 2
==================

Site Search & Product 
Discovery Experience Suite
For eCommerce stores that understand 
great UX drives sales.


All information at [luigisbox.com](https://luigisbox.com/).


Installation
------------

We hardly recommend to use [Composer](https://getcomposer.org/).

Please run the following commands in root of your M2 installation:

- ```$ composer require luigisbox/search-suite-magento2```
- ```$ bin/magento module:enable LuigisBox_SearchSuite```
- ```$ bin/magento setup:upgrade```
- ```$ bin/magento setup:di:compile```
- ```$ bin/magento indexer:set-mode schedule luigisbox_index_product```

You are done with installation. Now please go to `Administration > Stores > Configuration > Luigi's Box` and fill all required credentials.  