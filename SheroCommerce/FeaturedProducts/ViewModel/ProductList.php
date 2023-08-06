<?php

namespace SheroCommerce\FeaturedProducts\ViewModel;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductRepository;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Pricing\Helper\Data;

class ProductList implements ArgumentInterface
{
    const XML_PATH_SKUS = 'shero_settings/general/skus';

    /**
     * @var ProductRepository
     */
    protected ProductRepository $productRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var FilterBuilder
     */
    protected FilterBuilder $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var Image
     */
    protected Image $imageHelper;

    /**
     * @var Data
     */
    protected Data $priceHelper;
    /**
     * @var Configurable
     */
    protected Configurable $configurable;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductRepository $productRepository
     * @param Configurable $configurable
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Image $imageHelper
     * @param Data $priceHelper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ProductRepository $productRepository,
        Configurable $configurable,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Image $imageHelper,
        Data $priceHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->filterBuilder = $filterBuilder;
        $this->productRepository = $productRepository;
        $this->configurable = $configurable;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->imageHelper = $imageHelper;
        $this->priceHelper = $priceHelper;
    }

    /**
     * Get the SKU(s) of featured products from configuration
     * @return mixed
     */
    public function getFeaturedProductSku(): mixed
    {
        return $this->scopeConfig->getValue(self::XML_PATH_SKUS, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get featured products based on the configured SKUs
     * @return array
     */
    public function getFeaturedProducts(): array
    {
        $configSkus = $this->getFeaturedProductSku();

        $filters = $this->filterBuilder
            ->setField('sku')
            ->setConditionType('in')
            ->setValue($configSkus)
            ->create();

        $this->searchCriteriaBuilder->addFilter($filters);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->productRepository->getList($searchCriteria);

        return $searchResults->getItems();
    }

    /**
     * Get the image URL for a product
     * @param $product
     * @return string
     */
    public function getProductImageUrl($product): string
    {
        return $this->imageHelper->init($product, 'product_page_image')
            ->setImageFile($product->getImage()) // image,small_image,thumbnail
            ->resize(500)
            ->getUrl();
    }

    /**
     * Returns price formatted with currency symbol
     * @param $price
     * @return float|string
     */
    public function getFormattedPrice($price): float|string
    {
        return $this->priceHelper->currency($price, true, false);
    }

    /**
     * Returns the product URL. If the product has a parent and isn't visible individually, return the parent URL.
     * @param $child
     * @return string|void
     */
    public function getProductUrl($child)
    {
        try {
            $parentIds = $this->configurable->getParentIdsByChild($child->getId());
            if (!empty($parentIds) && $child->getVisibility() == Visibility::VISIBILITY_NOT_VISIBLE) {
                $parentId = $parentIds[0];
                $parentProduct = $this->productRepository->getById($parentId);
                return $parentProduct->getProductUrl();
            }
            return $child->getProductUrl();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Be nice
        }
    }

}
