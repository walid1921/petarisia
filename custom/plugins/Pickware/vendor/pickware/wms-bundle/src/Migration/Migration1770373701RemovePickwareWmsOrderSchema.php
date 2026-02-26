<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1770373701RemovePickwareWmsOrderSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770373701;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `pickware_wms_order`');
    }

    public function updateDestructive(Connection $connection): void {}
}
