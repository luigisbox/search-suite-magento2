Luigi's Box Search Suite for Magento 2
==================

[Luigi's Box](https://luigisbox.com) is an Award Winning Search Solution for eCommerce, providing Search Analytics and Search as a Service.

This repository holds composer package of a Magento2 extension, providing integration between M2 store & Luigi's Box services. To use it, you need
to have an account on Luigi's Box platform. [Go and create one](https://www.luigisbox.com/signup/?ref=m2) if you do not have it already.

Installation
------------

We strongly recommend to use [Composer](https://getcomposer.org/).

Please run the following commands in root of your M2 installation:

- ```$ composer require luigisbox/search-suite-magento2```
- ```$ bin/magento module:enable LuigisBox_SearchSuite```
- ```$ bin/magento setup:upgrade```
- ```$ bin/magento setup:di:compile```
- ```$ bin/magento indexer:set-mode schedule luigisbox_index_product```

Once you completed these steps, you are done with the installation. Now please go to `Administration > Stores > Configuration > Luigi's Box` and configure the extension there.  
