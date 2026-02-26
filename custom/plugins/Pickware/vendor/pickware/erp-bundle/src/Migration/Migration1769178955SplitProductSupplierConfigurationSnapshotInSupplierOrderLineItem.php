<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769178955SplitProductSupplierConfigurationSnapshotInSupplierOrderLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769178955;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_supplier_order_line_item`
                    ADD COLUMN `supplier_product_number` VARCHAR(255) DEFAULT NULL AFTER `product_supplier_configuration_snapshot`,
                    ADD COLUMN `min_purchase` INT(11) DEFAULT NULL AFTER `supplier_product_number`,
                    ADD COLUMN `purchase_steps` INT(11) DEFAULT NULL AFTER `min_purchase`;
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_supplier_order_line_item`
                SET `supplier_product_number` = JSON_UNQUOTE(
                        JSON_EXTRACT(`product_supplier_configuration_snapshot`, '$.supplierProductNumber')
                    ),
                    `min_purchase` = CAST(
                        JSON_UNQUOTE(JSON_EXTRACT(`product_supplier_configuration_snapshot`, '$.minPurchase')) AS SIGNED
                    ),
                    `purchase_steps` = CAST(
                        JSON_UNQUOTE(JSON_EXTRACT(`product_supplier_configuration_snapshot`, '$.purchaseSteps')) AS SIGNED
                    )
                WHERE `product_supplier_configuration_snapshot` IS NOT NULL;
                SQL,
        );

        $connection->executeStatement(
            <<<SQL
                ALTER TABLE `pickware_erp_supplier_order_line_item`
                    DROP COLUMN `product_supplier_configuration_snapshot`;
                SQL,
        );
    }
}
