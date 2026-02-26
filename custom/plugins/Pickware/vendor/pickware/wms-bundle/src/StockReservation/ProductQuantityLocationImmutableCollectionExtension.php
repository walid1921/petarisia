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

use Pickware\PickwareErpStarter\Batch\ImmutableBatchQuantityMap;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocation;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;

class ProductQuantityLocationImmutableCollectionExtension
{
    private const NO_BATCH_KEY = '__NO_BATCH__';

    public static function add(
        ProductQuantityLocationImmutableCollection $summand1,
        ProductQuantityLocationImmutableCollection $summand2,
    ): ProductQuantityLocationImmutableCollection {
        $quantities = [];
        $stockLocations = [];
        foreach ($summand1 as $element) {
            self::addElement($element, $quantities, $stockLocations);
        }
        foreach ($summand2 as $element) {
            self::addElement($element, $quantities, $stockLocations);
        }

        $results = [];
        foreach ($quantities as $locationHash => $productQuantities) {
            foreach ($productQuantities as $productId => $batchQuantities) {
                $results[] = self::createProductQuantityLocationFromBatchQuantities(
                    $stockLocations[$locationHash],
                    $productId,
                    $batchQuantities,
                );
            }
        }

        return new ProductQuantityLocationImmutableCollection($results);
    }

    /**
     * Reduces quantities from the minuend that are present in the subtrahend. Treats each collection like a multiset,
     * where two elements are identical if they represent the same product of the same batch at the same location.
     * Products without batch information are treated as a separate, "unknown", batch. The result contains only
     * positive quantities.
     * This operation has undefined behavior for negative quantities.
     */
    public static function removeMatching(
        ProductQuantityLocationImmutableCollection $minuend,
        ProductQuantityLocationImmutableCollection $subtrahend,
    ): ProductQuantityLocationImmutableCollection {
        $quantities = [];
        $stockLocations = [];
        foreach ($minuend as $element) {
            self::addElement($element, $quantities, $stockLocations);
        }
        foreach (self::negate($subtrahend) as $element) {
            self::addElement($element, $quantities, $stockLocations);
        }

        $results = [];
        foreach ($quantities as $locationHash => $productQuantities) {
            foreach ($productQuantities as $productId => $batchQuantities) {
                $nonNegativeBatchQuantities = array_filter($batchQuantities, fn(int $quantity) => $quantity > 0);
                if (count($nonNegativeBatchQuantities) === 0) {
                    continue;
                }
                $results[] = self::createProductQuantityLocationFromBatchQuantities(
                    $stockLocations[$locationHash],
                    $productId,
                    $nonNegativeBatchQuantities,
                );
            }
        }

        return new ProductQuantityLocationImmutableCollection($results);
    }

    /**
     * @param array<string, array<string, array<string, int>>> $quantities LocationHash => ProductId => BatchId => Quantity
     * @param array<string, StockLocationReference> $stockLocations LocationHash => StockLocationReference
     */
    private static function addElement(
        ProductQuantityLocation $element,
        array &$quantities,
        array &$stockLocations,
    ): void {
        $locationHash = $element->getStockLocationReference()->hash();
        $stockLocations[$locationHash] ??= $element->getStockLocationReference();
        $productId = $element->getProductId();
        $batchedQuantity = 0;
        // @phpstan-ignore function.alreadyNarrowedType (Method might not exist with old ERP versions)
        if (method_exists($element, 'getBatches') && $element->getBatches() !== null) {
            foreach ($element->getBatches()->asArray() as $batchId => $batchQuantity) {
                $quantities[$locationHash][$productId][$batchId] ??= 0;
                $quantities[$locationHash][$productId][$batchId] += $batchQuantity;
                $batchedQuantity += $batchQuantity;
            }
        }
        if ($element->getQuantity() !== $batchedQuantity) {
            $quantities[$locationHash][$productId][self::NO_BATCH_KEY] ??= 0;
            $quantities[$locationHash][$productId][self::NO_BATCH_KEY] += $element->getQuantity() - $batchedQuantity;
        }
    }

    /**
     * @param array<string, int> $batchQuantities BatchId => Quantity
     */
    private static function createProductQuantityLocationFromBatchQuantities(
        StockLocationReference $stockLocationReference,
        string $productId,
        array $batchQuantities,
    ): ProductQuantityLocation {
        $totalQuantity = array_sum($batchQuantities);
        unset($batchQuantities[self::NO_BATCH_KEY]);
        $batches = null;
        if (count($batchQuantities) > 0) {
            $batches = new ImmutableBatchQuantityMap($batchQuantities);
        }

        return new ProductQuantityLocation(
            $stockLocationReference,
            $productId,
            $totalQuantity,
            $batches,
        );
    }

    private static function negate(
        ProductQuantityLocationImmutableCollection $collection,
    ): ProductQuantityLocationImmutableCollection {
        return $collection->map(
            function(ProductQuantityLocation $productQuantityLocation) {
                $batches = null;
                // @phpstan-ignore function.alreadyNarrowedType (Method might not exist with old ERP versions)
                if (method_exists($productQuantityLocation, 'getBatches')) {
                    $batches = $productQuantityLocation->getBatches()?->negate();
                }

                return new ProductQuantityLocation(
                    $productQuantityLocation->getStockLocationReference(),
                    $productQuantityLocation->getProductId(),
                    -$productQuantityLocation->getQuantity(),
                    $batches,
                );
            },
            ProductQuantityLocationImmutableCollection::class,
        );
    }
}
