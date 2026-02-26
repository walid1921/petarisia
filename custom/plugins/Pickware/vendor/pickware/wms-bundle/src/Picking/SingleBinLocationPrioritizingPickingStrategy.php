<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Picking;

use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PhpStandardLibrary\Collection\Sorting\Compare;
use Pickware\PickwareErpStarter\Batch\BatchExpirationService;
use Pickware\PickwareErpStarter\Batch\BatchManagementProdFeatureFlag;
use Pickware\PickwareErpStarter\Batch\BatchQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\Picking\BatchAwarePickingFeatureService;
use Pickware\PickwareErpStarter\Picking\PickableStockProvider;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingStrategy;
use Pickware\PickwareErpStarter\Picking\PickingStrategyService;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\Routing\RoutingStrategy;
use Pickware\PickwareErpStarter\Stock\StockAreaType;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareErpStarter\StockLocationSorting\BinLocationPropertyStockLocationSorter;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias('pickware_wms.default_picking_strategy')]
class SingleBinLocationPrioritizingPickingStrategy implements PickingStrategy
{
    public function __construct(
        private readonly PickingStrategyService $pickingStrategyService,
        #[Autowire(service: 'pickware_erp.default_routing_strategy')]
        private readonly RoutingStrategy $routingStrategy,
        #[Autowire(service: 'pickware_wms.default_pickable_stock_provider')]
        private readonly PickableStockProvider $pickableStockProvider,
        #[Autowire(service: 'pickware_wms.bin_location_property_stock_location_sorter')]
        private readonly BinLocationPropertyStockLocationSorter $stockLocationSorter,
        private readonly FeatureFlagService $featureFlagService,
        private readonly ?BatchAwarePickingFeatureService $batchAwarePickingFeatureService = null,
        private readonly ?BatchExpirationService $batchExpirationService = null,
    ) {}

    public function calculatePickingSolution(
        PickingRequest $pickingRequest,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        // Step 1: Get all necessary data
        $warehouseIds = match ($pickingRequest->getSourceStockArea()->getStockAreaType()) {
            StockAreaType::Warehouse => [$pickingRequest->getSourceStockArea()->getWarehouseId()],
            StockAreaType::Warehouses => $pickingRequest->getSourceStockArea()->getWarehouseIds(),
            StockAreaType::Everywhere => null,
        };
        $productsToPick = $this->getProductToPick($pickingRequest);
        $pickableStock = $this->pickableStockProvider->getPickableStocks(
            $productsToPick->getKeys(),
            $warehouseIds,
            $context,
        );

        if (
            $this->batchAwarePickingFeatureService?->areBatchesSupportedDuringPicking()
            && $this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME)
        ) {
            $pickableBatchStock = $this->batchExpirationService->filterStockByBatchExpiration(
                $pickableStock->asBatchQuantityLocations(),
                $pickingRequest,
                $context,
            );

            // Step 2: Prioritize bin locations that can fulfill the product in one batch at one location.
            $preferredBatchStock = $pickableBatchStock->filter(
                fn(BatchQuantityLocation $stock) => $stock->getLocation()->isBinLocation()
                    && $stock->getQuantity() >= $productsToPick->get($stock->getProductId()),
            );
            $undesirableStock = $pickableBatchStock
                ->getElementsNotIdenticallyContainedIn($preferredBatchStock)
                ->groupByProductAndLocation();
            $preferredStock = $preferredBatchStock->groupByProductAndLocation();
        } else {
            // Step 2: Prioritize bin locations that can fulfill the products at one location.
            $preferredStock = $pickableStock->filter(
                fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference()->isBinLocation()
                    && $stock->getQuantity() >= $productsToPick->get($stock->getProductId()),
            );
            $undesirableStock = $pickableStock->getElementsNotIdenticallyContainedIn($preferredStock);
        }

        // Step 3: Sort the stock locations
        // @phpstan-ignore function.alreadyNarrowedType (only exists as of the next ERP-Starter version)
        if (method_exists($this->stockLocationSorter, 'createComparator')) {
            $binLocationComparator = $this->stockLocationSorter->createComparator(
                $pickableStock->map(fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference()),
                $context,
            );
            if (
                $this->batchAwarePickingFeatureService?->areBatchesSupportedDuringPicking()
                && $this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME)
            ) {
                $stockLocationComparator = Compare::chain(
                    // Sort by batch expiration first
                    Compare::byKey(
                        fn(BatchQuantityLocation $stock) => $stock->getBatchId() ?? 'NO_BATCH',
                        $this->batchExpirationService->createBatchExpirationComparator(
                            $pickableStock->asBatchQuantityLocations()->getBatchIds(),
                            $context,
                        ),
                    ),
                    // Then sort by bin location
                    Compare::byKey(fn(BatchQuantityLocation $stock) => $stock->getLocation(), $binLocationComparator),
                );
                $sortedPreferredStock = $preferredStock
                    ->asBatchQuantityLocations()
                    ->sorted($stockLocationComparator)
                    ->asProductQuantityLocations();
                $sortedUndesirableStock = $undesirableStock
                    ->asBatchQuantityLocations()
                    ->sorted($stockLocationComparator)
                    ->asProductQuantityLocations();
            } else {
                $sortedPreferredStock = $preferredStock->sortedBy(
                    fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference(),
                    $binLocationComparator,
                );
                $sortedUndesirableStock = $undesirableStock->sortedBy(
                    fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference(),
                    $binLocationComparator,
                );
            }
        } else {
            // Legacy implementation for old ERP versions where the stock location sorter does not implement the comparator yet.
            $sortedPreferredStockLocations = $this->stockLocationSorter->sort(
                stockLocationReferences: $preferredStock
                    ->map(fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference()),
                context: $context,
            );
            $sortedUndesirableStockLocations = $this->stockLocationSorter->sort(
                stockLocationReferences: $undesirableStock
                    ->map(fn(ProductQuantityLocation $stock) => $stock->getStockLocationReference()),
                context: $context,
            );
            $sortedPreferredStock = $preferredStock->sortLike($sortedPreferredStockLocations);
            $sortedUndesirableStock = $undesirableStock->sortLike($sortedUndesirableStockLocations);
        }
        $sortedStock = $sortedPreferredStock->merge($sortedUndesirableStock);

        // Step 4: Select locations to pick from
        try {
            $locationsToPickFrom = $this->pickingStrategyService->selectLocationsToPickFrom(
                prioritizedStock: $sortedStock,
                productsToPick: $pickingRequest->getProductsToPick(),
                context: $context,
            );
        } catch (PickingStrategyStockShortageException $exception) {
            // Step 5: Apply a routing through the warehouse for the partial picking request solution.
            throw new PickingStrategyStockShortageException(
                stockShortages: $exception->getStockShortages(),
                partialPickingRequestSolution: $this->routingStrategy->route(
                    $exception->getPartialPickingRequestSolution(),
                    $context,
                ),
                productNumbers: $exception->getProductNumbers(),
            );
        }

        // Step 5: Apply a routing through the warehouse for the selected locations.
        return $this->routingStrategy->route($locationsToPickFrom, $context);
    }

    /**
     * @return CountingMap<string>
     */
    private function getProductToPick(PickingRequest $pickingRequest): CountingMap
    {
        $productsToPick = $pickingRequest->getProductsToPick();
        // @phpstan-ignore function.alreadyNarrowedType (only exists as of ERP-Starter v4.34.0)
        if (method_exists($productsToPick, 'asCountingMap')) {
            return $productsToPick->asCountingMap();
        }

        return CountingMap::fromTuples(
            $productsToPick
                ->groupByProductId()
                ->map(fn(ProductQuantity $productQuantity) => [
                    $productQuantity->getProductId(),
                    $productQuantity->getQuantity(),
                ])
                ->asArray(),
        );
    }
}
