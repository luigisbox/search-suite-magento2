<?php

namespace LuigisBox\SearchSuite\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Zend\Http\Client;
use Zend\Http\Request;

class Helper extends AbstractHelper
{
    const SCRIPT_ENABLED       = 'luigisboxsearch_settings/settings/enabled';
    const TRACKER_ID           = 'luigisboxsearch_settings/settings/tracker_id';
    const TRACKER_URL          = 'luigisboxsearch_settings/settings/tracker_url';
    const API_KEY              = 'luigisboxsearch_settings/settings/api_key';

    const API_CONTENT_URI        = 'https://live.luigisbox.com/v1/content';
    const API_CONTENT_COMMIT_URI = 'https://live.luigisbox.com/v1/content/commit';

    const API_CONTENT_ENDPOINT        = 'url_content';
    const API_CONTENT_DELETE_ENDPOINT = 'url_content_delete';
    const API_COMMIT_ENDPOINT         = 'url_commit';

    const TYPE_ITEM     = 'item';
    const TYPE_CATEGORY = 'category';

    const LOGFILE  = 'luigisbox/update.txt';
    const INTERVAL = 86400;

    protected $_scopeConfig;

    protected $_categoryCollectionFactory;

    protected $_productCollectionFactory;

    protected $_helperImage;

    protected $_helperPrice;

    protected $_productVisibility;

    protected $_productStatus;

    protected $_storeManager;

    protected $_filesystem;

    protected $_emulation;

    protected $_fixImageLinksOmittingPubFolder; // @see https://github.com/magento/magento2/issues/9111

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Helper\Image $helperImage,
        \Magento\Framework\Pricing\Helper\Data $helperPrice,
        \Magento\Catalog\Model\Product\Visibility $productVisibility,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\App\Emulation $emulation
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_helperImage = $helperImage;
        $this->_helperPrice = $helperPrice;
        $this->_productVisibility = $productVisibility;
        $this->_productStatus = $productStatus;
        $this->_storeManager = $storeManager;
        $this->_filesystem = $filesystem;
        $this->_emulation = $emulation;
        parent::__construct($context);
    }

    /**
     * Check if the Luigi's Box module is enabled in admin
     *
     * @return bool
     */
    public function isEnabled()
    {
        return (bool) $this->_scopeConfig->getValue(self::SCRIPT_ENABLED);
    }

    public function isConfigured()
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $trackerId = $this->getTrackerId();
        if (empty($trackerId)) {
            return false;
        }

        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return false;
        }

        return true;
    }

    public function getTrackerId()
    {
        return $this->_scopeConfig->getValue(self::TRACKER_ID);
    }

    public function getTrackerUrl()
    {
        return $this->_scopeConfig->getValue(self::TRACKER_URL);
    }

    public function hasTrackerUrl()
    {
        $url = $this->getTrackerUrl();

        return !empty($url);
    }

    public function getApiKey()
    {
        return $this->_scopeConfig->getValue(self::API_KEY);
    }

    public function getRequest($endpoint)
    {
        $request = new Request();
        $request->setHeaders($this->getHeaders($endpoint));
        $request->setUri($this->getUriForEndpoint($endpoint));
        $request->setMethod($this->getMethodForEndpoint($endpoint));

        return $request;
    }

    public function getContentRequest($data)
    {
        $request = $this->getRequest(self::API_CONTENT_ENDPOINT);
        $request->setContent(json_encode(['objects' => $data]));

        return $request;
    }

    public function getContentDeleteRequest($data)
    {
        $request = $this->getRequest(self::API_CONTENT_DELETE_ENDPOINT);
        $request->setContent(json_encode(['objects' => $data]));

        return $request;
    }

    public function getCommitRequest($generation, $type)
    {
        $request = $this->getRequest(self::API_COMMIT_ENDPOINT);

        $params = new \Zend\Stdlib\Parameters([
            'generation' => $generation,
            'type'       => $type
        ]);

        $request->setQuery($params);

        return $request;
    }

    public function appendTypeAndGenerationToData($data, $type, $generation)
    {
        foreach ($data as $key => $datum) {
            $data[$key]['type'] = $type;
            $data[$key]['generation'] = $generation;
        }

        return $data;
    }

    public function appendTypeToData($data, $type)
    {
        foreach ($data as $key => $datum) {
            $data[$key]['type'] = $type;
        }

        return $data;
    }

    public function getHeaders($endpoint)
    {
        $date = gmdate('D, d M Y H:i:s T');
        $method = $this->getMethodForEndpoint($endpoint);

        $signature = $this->digest($this->getApiKey(), $method, parse_url($this->getApiUri($endpoint), PHP_URL_PATH), $date);

        $httpHeaders = new \Zend\Http\Headers();
        $httpHeaders->addHeaders([
            'Content-Type'  => 'application/json',
            'date'          => $date,
            'Authorization' => "magento2 {$this->getTrackerId()}:{$signature}",
        ]);

        return $httpHeaders;
    }

    /**
     * @param $key
     * @param $method
     * @param $endpoint
     * @param $date
     * @return string
     */
    public function digest($key, $method, $endpoint, $date)
    {
        $content_type = 'application/json';

        $data = "{$method}\n{$content_type}\n{$date}\n{$endpoint}";

        $signature = trim(base64_encode(hash_hmac('sha256', $data, $key, true)));

        return $signature;
    }

    public function getApiUri($endpoint)
    {
        $uris = [
            self::API_CONTENT_ENDPOINT        => self::API_CONTENT_URI,
            self::API_CONTENT_DELETE_ENDPOINT => self::API_CONTENT_URI,
            self::API_COMMIT_ENDPOINT         => self::API_CONTENT_COMMIT_URI,
        ];

        if (array_key_exists($endpoint, $uris)) {
            return $uris[$endpoint];
        }

        return null;
    }

    public function getMethodForEndpoint($endpoint)
    {
        $methods = [
            self::API_CONTENT_ENDPOINT        => Request::METHOD_POST,
            self::API_CONTENT_DELETE_ENDPOINT => Request::METHOD_DELETE,
            self::API_COMMIT_ENDPOINT         => Request::METHOD_POST,
        ];

        if (array_key_exists($endpoint, $methods)) {
            return $methods[$endpoint];
        }

        return Request::METHOD_POST;
    }

    public function getUriForEndpoint($endpoint)
    {
        $uris = [
            self::API_CONTENT_ENDPOINT        => self::API_CONTENT_URI,
            self::API_CONTENT_DELETE_ENDPOINT => self::API_CONTENT_URI,
            self::API_COMMIT_ENDPOINT         => self::API_CONTENT_COMMIT_URI,
        ];

        if (array_key_exists($endpoint, $uris)) {
            return $uris[$endpoint];
        }

        return self::API_CONTENT_URI;
    }

    public function getCategories()
    {
        $storeId = $this->_storeManager->getDefaultStoreView()->getStoreId();

        $this->_emulation->startEnvironmentEmulation(null, Area::AREA_FRONTEND, true);

        $categoryCollection = $this->_categoryCollectionFactory->create()
            ->addFieldToSelect('name')
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('level', ['gt' => 1])
            ->setStoreId($storeId)
            ->addUrlRewriteToResult()
            ->load();

        $categories = [];
        $categoriesPerLevels = [];
        foreach ($categoryCollection as $item) {
            $category = [
                'type'   => self::TYPE_CATEGORY,
                'url'    => $item->getUrl(),
                'fields' => [
                    'title'     => $item->getName(),
                    'ancestors' => [],
                ]
            ];

            $categories[$item->getEntityId()] = $category;
            $categoriesPerLevels[($item->getLevel() - 1)][$item->getEntityId()] = $item->getParentId();
        }

        $this->_emulation->stopEnvironmentEmulation();

        ksort($categoriesPerLevels);
        foreach ($categoriesPerLevels as $level => $categoriesPerLevel) {
            // Skip first level categories
            if ($level === 1) {
                continue;
            }

            foreach ($categoriesPerLevel as $catId => $catParentId) {
                if (!array_key_exists($catParentId, $categories)) {
                    continue;
                }                

                $parentCategory = $categories[$catParentId];

                $ancestors = $parentCategory['fields']['ancestors'];

                $ancestors[] = [
                    'title' => $parentCategory['fields']['title'],
                    'url'   => $parentCategory['url'],
                ];

                $categories[$catId]['fields']['ancestors'] = $ancestors;
            }
        }

        return $categories;
    }

    public function getContentData($id = null)
    {
        $start = 0;
        $take = 500;
        $data = [];

        $storeId = $this->_storeManager->getDefaultStoreView()->getStoreId();

        $categories = $this->getCategories();

        while (true) {
            $productCollection = $this->_productCollectionFactory->create()
                ->addAttributeToSort('entity_id', 'asc')
                ->addFieldToSelect('description')
                ->addFieldToSelect('short_description')
                ->addFieldToSelect('small_image')
                ->addFieldToSelect('name')
                ->addAttributeToSelect('*')
                ->addStoreFilter($storeId)
                ->setPageSize($take)
                ->addUrlRewrite()
                ->setCurPage(1);

            if ($id === null) {
                $productCollection->addFieldToFilter('entity_id', ['gt' => $start])
                    ->addAttributeToFilter('status', ['in' => $this->_productStatus->getVisibleStatusIds()])
                    ->setVisibility($this->_productVisibility->getVisibleInSearchIds());
            } else {
                $productCollection->addFieldToFilter('entity_id', $id);
            }

            $productCollection->load();

            if ($productCollection->count() === 0) {
                break;
            }

            $this->_logger->debug('Luigi\'s Box content progress ' . $start);

            foreach ($productCollection as $item) {
                $datum = [
                    'url'    => $item->getProductUrl(),
                    'fields' => [
                        'title'             => $item->getName(),
                        'product_code'      => $item->getSku(),
                        'price_amount'      => (float) $item->getPrice(),
                        'price'             => $this->_helperPrice->currency($item->getPrice(), true, false),
                        'description'       => trim($item->getDescription()),
                        'short_description' => trim($item->getShortDescription()),
                    ],
                    'enabled' => (in_array($item->getStatus(), $this->_productStatus->getVisibleStatusIds()) && in_array($item->getVisibility(), $this->_productVisibility->getVisibleInSearchIds())),
                    'nested' => [],
                ];

                if ($item->getSmallImage() !== null) {
                    $imageLink = $this->_helperImage->init($item, 'product_page_image_small')->setImageFile($item->getSmallImage())->getUrl();
                    if ($this->_fixImageLinksOmittingPubFolder === null) {
                        // Check if given image url is valid
                        $this->_fixImageLinksOmittingPubFolder = $this->isFixImageLinkNeeded($imageLink);
                    }

                    if ($this->_fixImageLinksOmittingPubFolder) {
                        $imageLink = $this->trimPubFromUrl($imageLink);
                    }

                    $datum['fields']['image_link'] = $imageLink;
                }

                // Add attributes
                $attributes = $item->getAttributes();
                $keysAttributes = array_keys($attributes);

                $importantAttributes = array_diff($keysAttributes, $this->getIgnoredAttributes());
                foreach ($importantAttributes as $importantAttribute) {
                    $attribute = $item->getAttributeText($importantAttribute);
                    $attributeLabel = array_key_exists($importantAttribute, $attributes) ? $attributes[$importantAttribute]->getDefaultFrontendLabel() : null;

                    if ($attribute === null || $attributeLabel === null) {
                        continue;
                    }

                    $datum['fields'][$attributeLabel] = $attribute;
                }

                // Add categories
                $categoryIds = $item->getCategoryIds();
                if ($categoryIds !== null) {
                    foreach ($categoryIds as $categoryId) {
                        if (isset($categories[$categoryId])) {
                            $datum['nested'][] = $categories[$categoryId];
                        }
                    }
                }

                $data[] = $datum;
            }

            $start = $item->getEntityId();

            if ($id !== null) {
                break;
            }
        }

        return $data;
    }

    public function getIgnoredAttributes()
    {
        return [
            'entity_id',
            'type_id',
            'attribute_set_id',
            'status',
            'old_id',
            'name',
            'url_path',
            'required_options',
            'has_options',
            'image_label',
            'small_image_label',
            'thumbnail_label',
            'created_at',
            'sku',
            'updated_at',
            'sku_type',
            'price',
            'price_type',
            'tax_class_id',
            'quantity_and_stock_status',
            'ts_dimensions_length',
            'ts_dimensions_width',
            'ts_dimensions_height',
            'weight',
            'weight_type',
            'visibility',
            'category_ids',
            'news_from_date',
            'news_to_date',
            'country_of_manufacture',
            'links_purchased_separately',
            'samples_title',
            'links_title',
            'links_exist',
            'configurable_variation',
            'description',
            'short_description',
            'shipment_type',
            'image',
            'small_image',
            'swatch_image',
            'thumbnail',
            'media_gallery',
            'gallery',
            'url_key',
            'meta_title',
            'meta_keyword',
            'meta_description',
            'special_price',
            'special_from_date',
            'special_to_date',
            'cost',
            'tier_price',
            'minimal_price',
            'msrp',
            'msrp_display_actual_price_type',
            'price_view',
            'page_layout',
            'options_container',
            'custom_layout_update',
            'custom_design_from',
            'custom_design_to',
            'custom_design',
            'custom_layout',
            'gift_message_available',
        ];
    }

    public function contentRequest($data, $type, $generation = null, $nestedTypes = [])
    {
        if ($generation === null) {
            $data = $this->appendTypeToData($data, $type);
        } else {
            $data = $this->appendTypeAndGenerationToData($data, $type, $generation);
        }

        $client = new Client();
        $client->setOptions(['timeout' => 30]);

        $success = true;
        $chunks = 0;
        foreach (array_chunk($data, 500) as $chunk) {
            $request = $this->getContentRequest($chunk);

            try {
                $response = $client->send($request);

                $chunks += 1;
                if ($response->getStatusCode() != 201) {
                    $this->_logger->error('Invalid response from luigisbox content api');
                    $success = false;
                    break;
                }
            } catch (Exception $ex) {
                $this->_logger->error('Error accessing luigisbox content api');
                $success = false;
                break;
            }
        }

        if ($success && $chunks > 0 && $generation !== null) {
            $client = new Client();
            $client->setOptions(['timeout' => 30]);

            $types = array_merge([$type], $nestedTypes);
            foreach ($types as $commitType) {
                $request = $this->getCommitRequest($generation, $commitType);

                try {
                    $response = $client->send($request);
                    if ($response->getStatusCode() != 201) {
                        $this->_logger->error('Invalid response from luigisbox commit api');
                        $success = false;
                        break;
                    }
                } catch (Exception $ex) {
                    $this->_logger->error('Error accessing luigisbox commit api');
                    $success = false;
                    break;
                }
            }

            if ($success) {
                $this->_logger->info(sprintf('Luigi\'s Box content generation %d committed', $generation));
            }
        }

        return $success;
    }

    public function contentDeleteRequest($data, $type)
    {
        $objects = [];
        foreach ($data as $datum) {
            $objects[] = [
                'type' => $type,
                'url' => $datum['url']
            ];
        }

        $request = $this->getContentDeleteRequest($objects);

        $success = true;
        $client = new Client();
        $client->setOptions(['timeout' => 30]);
        try {
            $response = $client->send($request);

            if ($response->getStatusCode() != 200) {
                $this->_logger->error('Invalid response from luigisbox content api');
                $success = false;
            }
        } catch (Exception $ex) {
            $this->_logger->error('Error accessing luigisbox content api');
            $success = false;
        }

        return $success;
    }

    public function singleContentUpdate($id)
    {
        $data = $this->getContentData($id);

        if (count($data) === 0) {
            $this->_logger->debug('Luigi\'s Box product not found ' . $id);
            return;
        }

        if ($data[0]['enabled']) {
            $this->contentRequest($data, Helper::TYPE_ITEM);
        } else {
            $this->contentDeleteRequest($data, Helper::TYPE_ITEM);
        }
    }

    public function allContentUpdate()
    {
        $this->_logger->info('Luigi\'s Box product update start');
        $data = $this->getContentData();

        $generation = (string) round(microtime(true));

        $this->contentRequest($data, Helper::TYPE_ITEM, $generation, [Helper::TYPE_CATEGORY]);

        $this->_logger->info('Luigi\'s Box product update end');
    }

    public function trimPubFromUrl($url)
    {
        $host = parse_url($url, PHP_URL_HOST);

        return preg_replace(sprintf('@%s/pub/@', preg_quote($host)), "$host/", $url, 1);
    }

    public function isFixImageLinkNeeded($url)
    {
        if ($this->urlExists($url)) {
            return false;
        } elseif ($this->urlExists($this->trimPubFromUrl($url))) {
            return true;
        }

        return false;
    }

    public function urlExists($url)
    {
        $client = new Client($url);

        try {
            $response = $client->send();
        } catch (Exception $ex) {
            $this->_logger->error('Error accessing image link');
            return false;
        }

        return ($response->getStatusCode() === 200);
    }

    public function getLogfile()
    {
        $tmp = $this->_filesystem->getDirectoryRead(DirectoryList::TMP);

        if ($tmp->isExist(self::LOGFILE)) {
            try {
                $log = json_decode($tmp->readFile(self::LOGFILE));
            } catch (\Magento\Framework\Exception\FileSystemException $ex) {
                $this->_logger->debug('Luigi\'s Box file update.txt not readable');
                return false;
            }

            return $log;
        }

        return false;
    }

    public function setLogfile($log)
    {
        try {
            $tmp = $this->_filesystem->getDirectoryWrite(DirectoryList::TMP);
            $tmp->writeFile(self::LOGFILE, json_encode($log));
        } catch (\Magento\Framework\Exception\FileSystemException $ex) {
            $this->_logger->debug('Luigi\'s Box log file is not writable');
        }
    }

    public function isIndexValid()
    {
        $now = date('U');

        $last = 0;

        if ($log = $this->getLogfile()) {
            $last = $log->invalidated;
        }

        $diff = $now - $last;

        return ($diff < self::INTERVAL);
    }

    public function isIndexFinished()
    {
        if ($log = $this->getLogfile()) {
            return $log->finished;
        }

        return true;
    }

    public function isIndexRunning()
    {
        if ($log = $this->getLogfile()) {
            return $log->running;
        }

        return false;
    }

    public function markIndexFinished()
    {
        if ($log = $this->getLogfile()) {
            $log->finished = true;
            $log->running = false;

            $this->setLogfile($log);
        }
    }

    public function markIndexRunning()
    {
        if ($log = $this->getLogfile()) {
            $log->finished = false;
            $log->running = true;

            $this->setLogfile($log);
        }
    }

    public function setIndexInvalidationTimestamp()
    {
        $now = date('U');

        $log = ['invalidated' => $now, 'running' => false, 'finished' => false];

        $this->setLogfile($log);

        $this->_logger->debug('Luigi\'s Box index invalidated');
    }
}
