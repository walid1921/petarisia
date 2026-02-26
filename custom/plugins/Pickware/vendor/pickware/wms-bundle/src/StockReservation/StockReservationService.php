<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\StockReservation;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\Batch\BatchFeatureService;
use Pickware\PickwareErpStarter\Batch\BatchManagementProdFeatureFlag;
use Pickware\PickwareErpStarter\Batch\MinimumShelfLifeService;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityImmutableCollection;
use Pickware\PickwareErpStarter\Picking\BatchAwarePickingFeatureService;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\PickingStrategy;
use Pickware\PickwareErpStarter\Picking\PickingStrategyStockShortageException;
use Pickware\PickwareErpStarter\Stock\Model\StockCollection;
use Pickware\PickwareErpStarter\Stock\Model\StockDefinition;
use Pickware\PickwareErpStarter\Stock\Model\StockEntity;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Pickware\PickwareErpStarter\StockApi\StockMovementService;
use Pickware\PickwareErpStarter\StockApi\StockMovementServiceValidationException;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessEntity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessReservedItemDefinition;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessReservedItemEntity;
use Pickware\PickwareWms\PickingProcess\PickingItem;
use Pickware\PickwareWms\PickingProcess\PickingProcessException;
use Pickware\PickwareWms\PickingProfile\PickingProfileService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StockReservationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        #[Autowire(service: 'pickware_wms.default_picking_strategy')]
        private readonly PickingStrategy $pickingStrategy,
        private readonly StockMovementService $stockMovementService,
        private readonly PickingProfileService $pickingProfileService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly ?BatchFeatureService $batchFeatureService = null,
        private readonly ?BatchAwarePickingFeatureService $batchAwarePickingFeatureService = null,
        private readonly ?MinimumShelfLifeService $minimumShelfLifeService = null,
    ) {}

    public function reserveStockForPickingProcess(
        string $pickingProcessId,
        string $pickingProfileId,
        Context $context,
    ): void {
        /** @var PickingProcessEntity $pickingProcess */
        $pickingProcess = $this->entityManager->getByPrimaryKey(
            PickingProcessDefinition::class,
            $pickingProcessId,
            $context,
            [
                'deliveries.lineItems',
                'deliveries.stockContainer.stocks',
                'preCollectingStockContainer.stocks',
            ],
        );

        $pickingRequest = $this->createPickingRequest($pickingProcess, $context);

        $this->entityManager->runInTransactionWithRetry(
            function() use ($pickingProcessId, $pickingProfileId, $context, $pickingProcess, $pickingRequest): void {
                $this->entityManager->lockPessimistically(
                    StockDefinition::class,
                    [
                        new EqualsAnyFilter(
                            'productId',
                            $pickingRequest->getProductsToPick()->getProductIds()->asArray(),
                        ),
                        new MultiFilter('OR', [
                            new EqualsFilter('warehouseId', $pickingProcess->getWarehouseId()),
                            new EqualsFilter('binLocation.warehouseId', $pickingProcess->getWarehouseId()),
                        ]),
                    ],
                    $context,
                );

                try {
                    $productQuantityLocations = $this->pickingStrategy->calculatePickingSolution(
                        $pickingRequest,
                        $context,
                    );
                } catch (PickingStrategyStockShortageException $exception) {
                    if (!$this->pickingProfileService->isPartialDeliveryAllowed($pickingProfileId, $context)) {
                        throw PickingProcessException::partialDeliveryNotAllowed($exception);
                    }

                    $productQuantityLocations = $exception->getPartialPickingRequestSolution();
                }

                $reservedItemPosition = 1;
                $reservedItemPayloads = [];
                if (
                    $this->batchFeatureService?->isBatchManagementAvailable()
                    && $this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME)
                ) {
                    foreach ($productQuantityLocations->asBatchQuantityLocations() as $batchQuantityLocation) {
                        $reservedItemPayloads[] = [
                            ...$batchQuantityLocation->getLocation()->toPayload(),
                            'id' => Uuid::randomHex(),
                            'pickingProcessId' => $pickingProcessId,
                            'productId' => $batchQuantityLocation->getProductId(),
                            'batchId' => $batchQuantityLocation->getBatchId(),
                            'quantity' => $batchQuantityLocation->getQuantity(),
                            'position' => $reservedItemPosition,
                        ];
                        $reservedItemPosition += 1;
                    }
                } else {
                    foreach ($productQuantityLocations as $productQuantityLocation) {
                        $reservedItemPayloads[] = [
                            ...$productQuantityLocation->getStockLocationReference()->toPayload(),
                            'id' => Uuid::randomHex(),
                            'pickingProcessId' => $pickingProcessId,
                            'productId' => $productQuantityLocation->getProductId(),
                            'quantity' => $productQuantityLocation->getQuantity(),
                            'position' => $reservedItemPosition,
                        ];
                        $reservedItemPosition += 1;
                    }
                }

                $this->entityManager->create(
                    PickingProcessReservedItemDefinition::class,
                    $reservedItemPayloads,
                    $context,
                );
            },
        );
    }

    private function createPickingRequest(PickingProcessEntity $pickingProcess, Context $context): PickingRequest
    {
        $stockToPickByProductId = [];
        $productIdsByOrderId = [];
        foreach ($pickingProcess->getDeliveries() as $delivery) {
            if ($delivery->getStockContainer()) {
                $pickedStock = $delivery->getStockContainer()->getStocks();
            } else {
                $pickedStock = new StockCollection();
            }

            foreach ($delivery->getLineItems() as $lineItem) {
                $productId = $lineItem->getProductId();
                $pickedStockForLineItem = $pickedStock
                    ->filter(fn(StockEntity $stock) => $stock->getProductId() === $productId)
                    ->first();
                $pickedQuantityForLineItem = $pickedStockForLineItem ? $pickedStockForLineItem->getQuantity() : 0;
                $stockToPick = max(0, $lineItem->getQuantity() - $pickedQuantityForLineItem);
                if ($stockToPick === 0) {
                    continue;
                }

                $productIdsByOrderId[$delivery->getOrderId()][] = $productId;
                $stockToPickByProductId[$productId] ??= 0;
                $stockToPickByProductId[$productId] += $stockToPick;
            }
        }

        $preCollectingStockContainer = $pickingProcess->getPreCollectingStockContainer();
        if ($preCollectingStockContainer) {
            foreach ($preCollectingStockContainer->getStocks() as $stock) {
                $stockToPickByProductId[$stock->getProductId()] ??= 0;
                $stockToPickByProductId[$stock->getProductId()] -= $stock->getQuantity();
            }
        }

        $stockToPickByProductId = array_filter($stockToPickByProductId, fn(int $quantity) => $quantity > 0);
        $productQuantities = ProductQuantityImmutableCollection::create(array_map(
            fn(string $productId, int $quantity) => new ProductQuantity($productId, $quantity),
            array_keys($stockToPickByProductId),
            array_values($stockToPickByProductId),
        ));

        if ($this->batchAwarePickingFeatureService?->areBatchesSupportedDuringPicking()) {
            return new PickingRequest(
                productQuantities: $productQuantities,
                sourceStockArea: StockArea::warehouse($pickingProcess->getWarehouseId()),
                minimumShelfLifeByProductId: $this->minimumShelfLifeService->getMinimumRemainingShelfLivesForProductsInOrders(
                    $productIdsByOrderId,
                    $context,
                ),
            );
        }

        return new PickingRequest(
            productQuantities: $productQuantities,
            sourceStockArea: StockArea::warehouse($pickingProcess->getWarehouseId()),
        );
    }

    public function clearStockReservationsOfPickingProcess(string $pickingProcessId, Context $context): void
    {
        $this->entityManager->deleteByCriteria(
            PickingProcessReservedItemDefinition::class,
            ['pickingProcessId' => $pickingProcessId],
            $context,
        );
    }

    public function moveReservedOrFreeStock(
        string $pickingProcessId,
        PickingItem $item,
        StockLocationReference $destination,
        Context $context,
    ): void {
        $this->entityManager->runInTransactionWithRetry(function() use ($pickingProcessId, $item, $destination, $context): void {
            $stockFilter = [
                new EqualsFilter('productId', $item->getProductId()),
                $item->getSource()->getFilterForStockDefinition(),
            ];

            $this->entityManager->lockPessimistically(
                StockDefinition::class,
                $stockFilter,
                $context,
            );

            /** @var StockEntity|null $stock */
            $stock = $this->entityManager->findOneBy(StockDefinition::class, $stockFilter, $context);
            // In case there is not enough stock at all we just want to execute a stock-movement that itself
            // will fail with a not-enough-stock exception
            if ($stock && $stock->getQuantity() >= $item->getQuantity()) {
                if (
                    $this->batchFeatureService?->isBatchManagementAvailable()
                    && $this->featureFlagService->isActive(BatchManagementProdFeatureFlag::NAME)
                    && $item->getBatchId() !== null
                ) {
                    $stockFilter[] = new EqualsFilter('batchId', $item->getBatchId());
                }
                /** @var ImmutableCollection<PickingProcessReservedItemEntity> $reservedStocks */
                $reservedStocks = ImmutableCollection::create($this->entityManager->findBy(
                    PickingProcessReservedItemDefinition::class,
                    $stockFilter,
                    $context,
                ));
                $reservedForOtherPickingProcess = $reservedStocks
                    ->filter(fn(PickingProcessReservedItemEntity $reservedStock) => $reservedStock->getPickingProcessId() !== $pickingProcessId)
                    ->map(fn(PickingProcessReservedItemEntity $reservedStock) => $reservedStock->getQuantity())
                    ->sum();
                if ($item->getQuantity() > $stock->getQuantity() - $reservedForOtherPickingProcess) {
                    throw PickingProcessException::stockIsReservedForOtherPickingProcess();
                }

                $ownReservation = $reservedStocks->first(
                    fn(PickingProcessReservedItemEntity $reservedStock) => $reservedStock->getPickingProcessId() === $pickingProcessId,
                );

                if ($ownReservation) {
                    if ($ownReservation->getQuantity() > $item->getQuantity()) {
                        $this->entityManager->update(
                            PickingProcessReservedItemDefinition::class,
                            [
                                [
                                    'id' => $ownReservation->getId(),
                                    'quantity' => $ownReservation->getQuantity() - $item->getQuantity(),
                                ],
                            ],
                            $context,
                        );
                    } else {
                        $this->entityManager->delete(
                            PickingProcessReservedItemDefinition::class,
                            [$ownReservation->getId()],
                            $context,
                        );
                    }
                }
            }

            $stockMovement = $item->createStockMovementForDestination($destination, $context);
            try {
                $this->stockMovementService->moveStock([$stockMovement], $context);
            } catch (StockMovementServiceValidationException $e) {
                throw PickingProcessException::stockMovementNotPossible($e);
            }
        });
    }
}
