<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\Migration;

use Doctrine\DBAL\Connection;
use Pickware\PickwarePos\PickwarePosBundle;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1768919189SetPrimaryIdsForPosOrders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768919189;
    }

    public function update(Connection $connection): void
    {
        // Check if the primary_order_transaction_id and
        // primary_order_delivery_id columns exists (indicates Shopware 6.7+)
        $columnExists = (bool) $connection->fetchOne(
            'SELECT IF(COUNT(*) = 2, 1, 0)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "order"
            AND COLUMN_NAME IN ("primary_order_transaction_id", "primary_order_delivery_id");',
        );

        if (!$columnExists) {
            // Skip migration if we're not on Shopware 6.7+
            return;
        }

        // Set both primary IDs (delivery and transaction) for POS orders that do not have them set yet. We do not apply
        // the "real" logic to select the primaries here, because we know that POS orders always only have one delivery
        // and one transaction. This is a huge performance improvement.
        $connection->executeStatement(
            'UPDATE `order`
            INNER JOIN `sales_channel`
                ON `order`.`sales_channel_id` = `sales_channel`.`id`
                AND `sales_channel`.`type_id` = :posSalesChannelTypeId
            LEFT JOIN `order_transaction` `transaction`
                ON `order`.`id` = `transaction`.`order_id`
                AND `order`.`version_id` = `transaction`.`order_version_id`
            SET
                `order`.`primary_order_transaction_id` = `transaction`.`id`
            WHERE
                `order`.`primary_order_transaction_id` IS NULL',
            [
                'posSalesChannelTypeId' => bin2hex(PickwarePosBundle::SALES_CHANNEL_TYPE_ID),
            ],
        );
        $connection->executeStatement(
            'UPDATE `order`
            INNER JOIN `sales_channel`
                ON `order`.`sales_channel_id` = `sales_channel`.`id`
                AND `sales_channel`.`type_id` = :posSalesChannelTypeId
            LEFT JOIN `order_delivery` `delivery`
                ON `order`.`id` = `delivery`.`order_id`
                AND `order`.`version_id` = `delivery`.`order_version_id`
            SET
                `order`.`primary_order_delivery_id` = `delivery`.`id`
            WHERE
                `order`.`primary_order_delivery_id` IS NULL',
            [
                'posSalesChannelTypeId' => bin2hex(PickwarePosBundle::SALES_CHANNEL_TYPE_ID),
            ],
        );
    }
}
