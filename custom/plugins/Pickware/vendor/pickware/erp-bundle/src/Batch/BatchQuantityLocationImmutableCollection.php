<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;

/**
 * @extends ImmutableCollection<BatchQuantityLocation>
 */
class BatchQuantityLocationImmutableCollection extends ImmutableCollection
{
    /**
     * @return string[]
     */
    public function getBatchIds(): array
    {
        return $this
            ->compactMap(fn(BatchQuantityLocation $element) => $element->getBatchId())
            ->deduplicate()
            ->asArray();
    }

    public function groupByProductAndLocation(): ProductQuantityLocationImmutableCollection
    {
        return new ProductQuantityLocationImmutableCollection($this->groupBy(
            fn(BatchQuantityLocation $element) => sprintf(
                '%s-%s-%s',
                $element->getLocation()->getLocationTypeTechnicalName(),
                $element->getLocation()->getPrimaryKey(),
                $element->getProductId(),
            ),
            function(ImmutableCollection $group) {
                $batchQuantities = $group
                    ->filter(fn(BatchQuantityLocation $element) => $element->getBatchId() !== null)
                    ->map(fn(BatchQuantityLocation $element) => [
                        $element->getBatchId() => $element->getQuantity(),
                    ]);
                $batches = null;
                if (!$batchQuantities->isEmpty()) {
                    $batches = new ImmutableBatchQuantityMap(array_merge(...$batchQuantities->asArray()));
                }

                return new ProductQuantityLocation(
                    locationReference: $group->first()->getLocation(),
                    productId: $group->first()->getProductId(),
                    quantity: $group->map(fn(BatchQuantityLocation $element) => $element->getQuantity())->sum(),
                    batches: $batches,
                );
            },
        ));
    }

    public function asProductQuantityLocations(): ProductQuantityLocationImmutableCollection
    {
        return $this->map(
            function(BatchQuantityLocation $element) {
                $batches = null;
                if ($element->getBatchId() !== null) {
                    $batches = new ImmutableBatchQuantityMap([$element->getBatchId() => $element->getQuantity()]);
                }

                return new ProductQuantityLocation(
                    locationReference: $element->getLocation(),
                    productId: $element->getProductId(),
                    quantity: $element->getQuantity(),
                    batches: $batches,
                );
            },
            ProductQuantityLocationImmutableCollection::class,
        );
    }
}
