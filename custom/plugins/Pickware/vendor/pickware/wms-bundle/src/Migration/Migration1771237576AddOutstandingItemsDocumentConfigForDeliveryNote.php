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
use Pickware\PickwareWms\DeliveryNote\AddRemainingQuantitiesForPartialDeliveriesSubscriber;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1771237576AddOutstandingItemsDocumentConfigForDeliveryNote extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1771237576;
    }

    public function update(Connection $connection): void
    {
        $documentConfigJsonPath = sprintf(
            '$.%s',
            AddRemainingQuantitiesForPartialDeliveriesSubscriber::DOCUMENT_CONFIG_DISPLAY_OUTSTANDING_ITEMS_KEY,
        );

        $connection->executeStatement(
            <<<'SQL'
                    UPDATE `document_base_config` `documentBaseConfig`
                    INNER JOIN `document_type` `documentType`
                        ON `documentBaseConfig`.`document_type_id` = `documentType`.`id`
                    SET `documentBaseConfig`.`config` = JSON_INSERT(
                        `documentBaseConfig`.`config`,
                        :documentConfigJsonPath,
                        JSON_EXTRACT('true', '$')
                    )
                    WHERE `documentType`.`technical_name` = :technicalName
                      AND JSON_CONTAINS_PATH(`documentBaseConfig`.`config`, 'one', :documentConfigJsonPath) = 0;
                SQL,
            [
                'documentConfigJsonPath' => $documentConfigJsonPath,
                'technicalName' => 'delivery_note',
            ],
        );
    }

    public function updateDestructive(Connection $connection): void {}
}
