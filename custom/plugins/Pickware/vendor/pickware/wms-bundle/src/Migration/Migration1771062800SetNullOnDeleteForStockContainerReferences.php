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

class Migration1771062800SetNullOnDeleteForStockContainerReferences extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1771062800;
    }

    // phpcs:disable ShopwarePlugins.Migration.ForeignKeyIndexPair.MissingDropIndex
    // We re-create the foreign keys in this migration and intentionally keep the existing indexes.
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery`
            DROP FOREIGN KEY `pickware_wms_delivery.fk.stock_container`',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_delivery`
            ADD CONSTRAINT `pickware_wms_delivery.fk.stock_container`
                FOREIGN KEY (`stock_container_id`)
                REFERENCES `pickware_erp_stock_container` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE',
        );

        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process`
            DROP FOREIGN KEY `pickware_wms_picking_process.fk.stock_container_id`',
        );
        $connection->executeStatement(
            'ALTER TABLE `pickware_wms_picking_process`
            ADD CONSTRAINT `pickware_wms_picking_process.fk.stock_container_id`
                FOREIGN KEY (`pre_collecting_stock_container_id`)
                REFERENCES `pickware_erp_stock_container` (`id`)
                ON DELETE SET NULL
                ON UPDATE CASCADE',
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
