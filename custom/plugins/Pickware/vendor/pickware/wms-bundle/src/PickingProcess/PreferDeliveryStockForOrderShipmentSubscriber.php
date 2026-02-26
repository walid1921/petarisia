<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Batch\ImmutableBatchQuantityMap;
use Pickware\PickwareErpStarter\OrderShipping\PreCalculateStockToShipEvent;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PreferDeliveryStockForOrderShipmentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PreCalculateStockToShipEvent::class => 'preferDeliveryStock',
        ];
    }

    public function preferDeliveryStock(PreCalculateStockToShipEvent $event): void
    {
        $orderId = $event->getOrderId();
        $remainingProductQuantities = $event->getRemainingProductQuantities();
        if ($remainingProductQuantities->count() === 0) {
            return;
        }

        /** @var EntityCollection<DeliveryEntity> $deliveries */
        $deliveries = $this->entityManager->findBy(
            DeliveryDefinition::class,
            [
                'orderId' => $orderId,
                'state.technicalName' => DeliveryStateMachine::READY_TO_SHIP_STATES,
            ],
            $event->getContext(),
            [
                'stockContainer.stocks.batchMappings',
            ],
        );

        $remainingStockToShipByProductId = $remainingProductQuantities->groupByProductId()->asCountingMap();
        $stockToShipFromDeliveries = [];
        foreach ($deliveries as $delivery) {
            $stockContainer = $delivery->getStockContainer();
            if (!$stockContainer) {
                continue;
            }

            $deliveryStock = $stockContainer->getStocks()->getProductQuantityLocations();
            if ($deliveryStock->count() === 0) {
                continue;
            }

            $deliveryStockToShip = [];
            foreach ($deliveryStock as $stock) {
                $remainingQuantity = $remainingStockToShipByProductId->get($stock->getProductId());
                if ($remainingQuantity === 0) {
                    continue;
                }

                $quantityToUse = min($remainingQuantity, $stock->getQuantity());
                if ($quantityToUse === 0) {
                    continue;
                }

                $deliveryStockToShip[] = new ProductQuantityLocation(
                    $stock->getStockLocationReference(),
                    $stock->getProductId(),
                    $quantityToUse,
                    $stock->getBatches() ? $this->getSubsetOfBatches($stock->getBatches(), $quantityToUse) : null,
                );
                $remainingStockToShipByProductId->set(
                    $stock->getProductId(),
                    $remainingQuantity - $quantityToUse,
                );
            }

            if (count($deliveryStockToShip) === 0) {
                continue;
            }

            $stockToShipFromDeliveries = array_merge($stockToShipFromDeliveries, $deliveryStockToShip);
        }

        if (count($stockToShipFromDeliveries) === 0) {
            return;
        }

        $event->addStockLocations(new ProductQuantityLocationImmutableCollection($stockToShipFromDeliveries));
    }

    private function getSubsetOfBatches(ImmutableBatchQuantityMap $batches, int $quantity): ImmutableBatchQuantityMap
    {
        if ($batches->getTotal() <= $quantity) {
            return $batches;
        }

        $remainingQuantity = $quantity;
        $reducedBatchQuantities = [];
        foreach ($batches as $batchId => $batchQuantity) {
            if ($remainingQuantity === 0) {
                break;
            }

            $quantityToUse = min($batchQuantity, $remainingQuantity);
            if ($quantityToUse > 0) {
                $reducedBatchQuantities[$batchId] = $quantityToUse;
            }
            $remainingQuantity -= $quantityToUse;
        }

        return new ImmutableBatchQuantityMap($reducedBatchQuantities);
    }
}
