<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderShipping;

use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Pickware\PickwareErpStarter\Picking\ProductOrthogonalPickingStrategy;
use Pickware\PickwareErpStarter\Stock\StockArea;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderShippingStockCollector
{
    public function __construct(
        private readonly ProductOrthogonalPickingStrategy $pickingStrategy,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function collectStockToShip(
        string $orderId,
        ProductQuantityImmutableCollection $productQuantities,
        StockArea $stockArea,
        Context $context,
    ): ProductQuantityLocationImmutableCollection {
        $event = new PreCalculateStockToShipEvent(
            context: $context,
            productQuantities: $productQuantities,
            orderId: $orderId,
        );
        $this->eventDispatcher->dispatch($event);

        $pickingRequest = new PickingRequest(
            productQuantities: $event->getRemainingProductQuantities(),
            sourceStockArea: $stockArea,
        );

        $productQuantityLocations = $this->pickingStrategy->calculatePickingSolution(
            pickingRequest: $pickingRequest,
            context: $context,
        );

        /** @var ProductQuantityLocationImmutableCollection $productQuantityLocations */
        $productQuantityLocations = $event->getStockLocations()->merge($productQuantityLocations);

        return $productQuantityLocations;
    }
}
