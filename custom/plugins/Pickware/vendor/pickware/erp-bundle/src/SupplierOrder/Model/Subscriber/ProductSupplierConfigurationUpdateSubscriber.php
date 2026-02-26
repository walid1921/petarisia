<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SupplierOrder\Model\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Pickware\PickwareErpStarter\Supplier\Model\ProductSupplierConfigurationDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSupplierConfigurationUpdateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductSupplierConfigurationDefinition::ENTITY_WRITTEN_EVENT => (
                'updateSupplierOrderLineItemSupplierProductNumber'
            ),
        ];
    }

    public function updateSupplierOrderLineItemSupplierProductNumber(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $productSupplierConfigurationIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                continue;
            }

            $productSupplierConfigurationIds[] = $writeResult->getPrimaryKey();
        }

        if (count($productSupplierConfigurationIds) === 0) {
            return;
        }

        $this->connection->executeStatement(
            <<<SQL
                UPDATE `pickware_erp_supplier_order_line_item` AS `lineItem`
                INNER JOIN `pickware_erp_supplier_order` AS `supplierOrder`
                    ON `lineItem`.`supplier_order_id` = `supplierOrder`.`id`
                INNER JOIN `pickware_erp_product_supplier_configuration` AS `productSupplierConfiguration`
                    ON `productSupplierConfiguration`.`product_id` = `lineItem`.`product_id`
                    AND `productSupplierConfiguration`.`product_version_id` = IFNULL(
                        `lineItem`.`product_version_id`,
                        :liveVersionId
                    )
                    AND `productSupplierConfiguration`.`supplier_id` = `supplierOrder`.`supplier_id`

                SET `lineItem`.`supplier_product_number` = `productSupplierConfiguration`.`supplier_product_number`
                WHERE `productSupplierConfiguration`.`id` IN (:productSupplierConfigurationIds)
                # Note that we only update the supplier product number _once_ if it was not set before
                AND `lineItem`.`supplier_product_number` IS NULL
                SQL,
            [
                'productSupplierConfigurationIds' => array_map('hex2bin', $productSupplierConfigurationIds),
                'liveVersionId' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'productSupplierConfigurationIds' => ArrayParameterType::BINARY,
                'liveVersionId' => ParameterType::BINARY,
            ],
        );
    }
}
