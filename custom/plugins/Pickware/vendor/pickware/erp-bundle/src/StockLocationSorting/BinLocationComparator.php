<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockLocationSorting;

use Pickware\PhpStandardLibrary\Collection\Sorting\Comparator;
use Pickware\PickwareErpStarter\StockApi\StockLocationConfigurations;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Applies the following sorting rules in order:
 * 1. Sort locations in the default warehouse to the beginning
 * 2. Sort locations in non-default warehouses by the warehouse creation date
 * 3. Sort unknown bin locations to the end of each warehouse
 * 4. Sort by bin location properties given in {@link self::$sortByProperties} ASC
 *
 * Expects the given stock location configurations to contain a configuration for each bin location.
 *
 * @implements Comparator<StockLocationReference>
 */
#[Exclude]
readonly class BinLocationComparator implements Comparator
{
    /**
     * @param BinLocationProperty[] $sortByProperties
     */
    public function __construct(
        private StockLocationConfigurations $stockLocationConfigurations,
        private array $sortByProperties,
    ) {}

    public function compare(mixed $lhs, mixed $rhs): int
    {
        $lhsConfig = $this->stockLocationConfigurations->getForStockLocation($lhs);
        $rhsConfig = $this->stockLocationConfigurations->getForStockLocation($rhs);

        // Priority 1: Sort all locations in the default warehouse to the beginning.
        $sortingValue = $rhsConfig->getIsInDefaultWarehouse() <=> $lhsConfig->getIsInDefaultWarehouse();
        if ($sortingValue !== 0) {
            return $sortingValue;
        }

        // Priority 2: Sort locations in non-default warehouses by the warehouse creation date.
        $sortingValue = $lhsConfig->getWarehouseCreationDate() <=> $rhsConfig->getWarehouseCreationDate();
        if ($sortingValue !== 0) {
            return $sortingValue;
        }

        // Priority 3: Sort unknown bin locations to the end of each warehouse.
        $sortingValue = $lhs->isWarehouse() <=> $rhs->isWarehouse();
        if ($sortingValue !== 0) {
            return $sortingValue;
        }

        // Priority 4: Sort by provided properties.
        foreach ($this->sortByProperties as $sortByProperty) {
            $lhsValue = $sortByProperty->getPropertyValue($lhsConfig);
            $rhsValue = $sortByProperty->getPropertyValue($rhsConfig);

            $sortingValue = $sortByProperty->compare($lhsValue, $rhsValue);
            if ($sortingValue !== 0) {
                return $sortingValue;
            }
        }

        return 0;
    }
}
