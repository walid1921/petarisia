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

class Migration1767692383AddBatchToPickingProcessReservedItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1767692383;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_wms_picking_process_reserved_item`
                ADD COLUMN `batch_id` BINARY(16) NULL AFTER `product_version_id`,
                ADD CONSTRAINT `pw_wms_picking_process_reserved_item.fk.batch`
                    FOREIGN KEY (`batch_id`) REFERENCES `pickware_erp_batch` (`id`)
                        ON UPDATE CASCADE
                        ON DELETE SET NULL;
                SQL
        );
    }
}
