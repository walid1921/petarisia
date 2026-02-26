<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PickwareErpStarter\OrderShipping\ProductQuantityLocationImmutableCollection;
use Shopware\Core\Framework\Context;

interface PickableStockProvider
{
    /**
     * @param string[] $productIds
     * @param string[]|null $warehouseIds
     * @return ProductQuantityLocationImmutableCollection all implementations return {@link ProductQuantityLocationImmutableCollection}s,
     *      the interface is kept as {@link ImmutableCollection} for backwards compatibility.
     */
    public function getPickableStocks(
        array $productIds,
        ?array $warehouseIds,
        Context $context,
    ): ImmutableCollection;
}
