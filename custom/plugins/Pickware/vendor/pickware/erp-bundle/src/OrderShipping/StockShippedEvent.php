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

use Shopware\Core\Framework\Context;

class StockShippedEvent
{
    public function __construct(
        private readonly string $orderId,
        private readonly ProductQuantityLocationImmutableCollection $shippedStock,
        private readonly Context $context,
    ) {}

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getShippedStock(): ProductQuantityLocationImmutableCollection
    {
        return $this->shippedStock;
    }
}
