<?php
/**
 * @category  Aligent
 * @package
 * @author    Chris Rossi <chris.rossi@aligent.com.au>
 * @copyright 2022 Aligent Consulting.
 * @license
 * @link      http://www.aligent.com.au/
 */

namespace Aligent\GraphQLBundle\GraphQL\Resolver;

use Oro\Bundle\PricingBundle\Model\ProductPriceScopeCriteriaRequestHandler;
use Oro\Bundle\InventoryBundle\Inventory\LowInventoryProvider;
use Oro\Bundle\PricingBundle\Provider\ProductPriceProvider;
use Oro\Bundle\ProductBundle\Entity\Manager\ProductManager;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\RelatedItem\RelatedProduct;
use Oro\Bundle\ProductBundle\Search\ProductRepository;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;
use Oro\Bundle\SearchBundle\Query\Result\Item;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Overblog\GraphQLBundle\Definition\Argument;
use Overblog\GraphQLBundle\Definition\Resolver\QueryInterface;
use Overblog\GraphQLBundle\Error\UserError;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class ProductResolver implements
    QueryInterface,
    ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected ProductPriceProvider $productPriceProvider;
    protected ProductPriceScopeCriteriaRequestHandler $scopeCriteriaRequestHandler;
    protected TokenAccessorInterface $tokenAccessor;
    protected ProductManager $productManager;
    protected LowInventoryProvider $lowInventoryProvider;
    protected array $productMessages = array();

    public function __construct(
        ProductPriceProvider $productPriceProvider,
        LowInventoryProvider $lowInventoryProvider,
        ProductPriceScopeCriteriaRequestHandler $scopeCriteriaRequestHandler,
        TokenAccessorInterface $tokenAccessor,
        ProductManager $productManager
    ) {
        $this->productPriceProvider = $productPriceProvider;
        $this->lowInventoryProvider = $lowInventoryProvider;
        $this->scopeCriteriaRequestHandler = $scopeCriteriaRequestHandler;
        $this->tokenAccessor = $tokenAccessor;
        $this->productManager = $productManager;
    }

    public function resolveProduct(int $id)
    {
        $repo = $this->container->get('doctrine.orm.entity_manager')->getRepository(Product::class);

        $product = $repo->find($id);

        if (!$product instanceof Product) {
            throw new UserError(sprintf("Product %s not found", $id));
        }

        /**
         * NOTE: Hacky workaround
         * For debugging purposes I'm changing the name of the product based on whether the
         * Customer is logged in or not, so that we can test authorizations.
         */
        if ($this->tokenAccessor->getUser()) {
            $product->setDefaultName('logged in');
        } else {
            $product->setDefaultName('not logged in');
        }

        return $product;
    }

    public function doProductSearch(Argument $args, ?array $filters, ?array $sortBy)
    {
        /** @var ProductRepository $productSearchRepository */
        $productSearchRepository = $this->container->get('oro_product.website_search.repository.product');

        $totalRecords = 0;
        // TODO: Make this a parameter
        $pageSize = 25;
        // TODO: Make this a parameter
        $currentPage = 0;

        if ($nameOrSku = $args['name'] ?? $args['sku']) {
            $result = $productSearchRepository->findBySkuOrName($nameOrSku);
        }
        else {
            $query = $productSearchRepository
                ->getSearchQuery('', $currentPage, $pageSize)
                ->addSelect('integer.product_id')
                ->addSelect('text.type')
                ->addSelect('text.sku')
                ->addSelect('text.names_LOCALIZATION_ID as name')
                ->addSelect('text.image_product_large as image')
                ->addSelect('text.primary_unit as unit')
                ->addSelect('text.product_units')
                ->addSelect('integer.newArrival')
                ->addSelect('integer.isVariant')
                ->addSelect('integer.variant_fields_count as variantFieldsCount')
                ->addSelect('decimal.low_inventory_threshold as lowInventoryThreshold')
                ->addSelect('integer.is_upcoming as isUpcoming')
                ->addSelect('datetime.availability_date as availabilityDate')
                ->addSelect('decimal.minimal_price_CPL_ID_CURRENCY as cplPrice')
                ->addSelect('decimal.minimal_price_PRICE_LIST_ID_CURRENCY as plPrice')
                ->addSelect('integer.brand as brandId')
            ;

            /**
             * TODO: These filters should be dynamically generated so we can provide an operator as well.
             * For example, integer.is_upcoming=0 does not work because the value is null, so we need
             *  to provide a '!=' operator (and call Criteria::expre()->neq()).
             */
            if ($args['isUpcoming']) {
                // Filter by isUpcoming:{0/1}
                $query
                    ->addWhere(Criteria::expr()->eq('integer.is_upcoming', $args['isUpcoming']));
            }

            if ($args['type']) {
                // Filter by type:{simple/configurable}
                $query
                    ->addWhere(Criteria::expr()->eq('text.type', $args['type']));
            }

            if ($filters) {
                foreach ($filters as $filter) {
                    $query
                        ->addWhere(Criteria::expr()->eq($filter['name'], $filter['value']));
                }
            }

            if ($sortBy) {
                $query
                    ->setOrderBy($sortBy['field'], $sortBy['dir'])
                ;
            }

            // Restrict Product Search using Product Visibility and other restriction Event Listeners
            $query->setQuery($this->productManager->restrictSearchQuery($query->getQuery()));

            $totalRecords = $query->getTotalCount();
            $result = $query->execute();
        }

        // Pagination
        $totalPages = ($pageSize === 0) ? 1 : floor($totalRecords / $pageSize);

        $output = array_map(function (Item $item) {
            return $item->getSelectedData();
        }, $result);

        /**
         * Search index returns empty strings even for int/float fields.
         * We need to convert them to null so as to not break GraphQL
         */
        foreach ($output as $item => $productData) {
            foreach ($productData as $key => $value) {
                if ($value === "") {
                    $output[$item][$key] = null;
                }
            }
        }

        return [
            'totalRecords' => $totalRecords,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'results' => $output,
        ];
    }

    public function resolvePrices(Product $product, Argument $args)
    {
        $scopeCriteria = $this->scopeCriteriaRequestHandler->getPriceScopeCriteria();

        $prices = $this->productPriceProvider->getPricesByScopeCriteriaAndProducts(
            $scopeCriteria,
            [$product->getId()],
            ['USD']
        );

        return $prices[$product->getId()];
    }

    public function resolveLowInventory(Product $product, Argument $args)
    {
        if ($product->getInventoryStatus() === 'out_ofstock') {
            array_push($this->productMessages, [['type' => 'error', 'text' => $product->getInventoryStatus()]]);
        }
        $output = $this->lowInventoryProvider->isLowInventoryProduct(
            $product
        );

        $lowInventoryMessageText = $this->container->get('translator')
                                    ->trans('oro.inventory.low_inventory.message');

        if ($output) {
            array_push($this->productMessages, ['type' => 'warning', 'text' => $lowInventoryMessageText]);
        }

        return $this->productMessages;
    }

    public function resolveRelatedProducts(Product $product, Argument $args)
    {
        $repo = $this->container->get('doctrine.orm.entity_manager')->getRepository(RelatedProduct::class);

        return $repo->findRelated(
            $product->getId(),
            false,
            null
        );
    }
}
