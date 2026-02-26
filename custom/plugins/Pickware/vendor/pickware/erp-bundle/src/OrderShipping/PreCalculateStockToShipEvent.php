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

use InvalidArgumentException;
use Pickware\PickwareErpStarter\Stocking\ProductQuantity;
use Shopware\Core\Framework\Context;

class PreCalculateStockToShipEvent
{
    private ProductQuantityLocationImmutableCollection $stockLocations;

    public function __construct(
        private readonly Context $context,
        private readonly ProductQuantityImmutableCollection $productQuantities,
        private readonly string $orderId,
    ) {
        $this->stockLocations = new ProductQuantityLocationImmutableCollection([]);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function addStockLocations(ProductQuantityLocationImmutableCollection $stockLocations): void
    {
        $newStockLocations = $this->stockLocations->merge($stockLocations);
        $productQuantitiesByProductId = $this->productQuantities->groupByProductId()->asCountingMap();
        $newStockLocationsByProductId = $newStockLocations->groupByProductId()->asCountingMap();
        if (!$newStockLocationsByProductId->isSubsetOf($productQuantitiesByProductId)) {
            throw new InvalidArgumentException('Stock locations exceed remaining product quantities.');
        }

        $this->stockLocations = $newStockLocations;
    }

    public function getStockLocations(): ProductQuantityLocationImmutableCollection
    {
        return $this->stockLocations;
    }

    public function getRemainingProductQuantities(): ProductQuantityImmutableCollection
    {
        $productQuantities = $this->productQuantities->groupByProductId();
        $stockQuantities = $this->stockLocations->groupByProductId();

        $remainingProductQuantities = [];
        foreach ($productQuantities as $productQuantity) {
            $stockQuantity = $stockQuantities->first(
                fn(ProductQuantity $stockQuantity) => $stockQuantity->getProductId() === $productQuantity->getProductId(),
            );

            $remainingQuantity = $productQuantity->getQuantity() - ($stockQuantity?->getQuantity() ?? 0);
            if ($remainingQuantity <= 0) {
                continue;
            }

            $remainingProductQuantities[] = new ProductQuantity(
                productId: $productQuantity->getProductId(),
                quantity: $remainingQuantity,
            );
        }

        return new ProductQuantityImmutableCollection($remainingProductQuantities);
    }
}
