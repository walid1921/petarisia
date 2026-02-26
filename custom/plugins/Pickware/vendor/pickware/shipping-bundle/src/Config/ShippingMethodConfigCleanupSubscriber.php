<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShippingBundle\Config;

use Doctrine\DBAL\Connection;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigCollection;
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigDefinition;
use Pickware\ShippingBundle\Config\Model\ShippingMethodConfigEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cleans up shipping method configurations after they are written by removing config values that are no longer relevant
 * based on their showConditions. This prevents old config values from persisting when switching between different
 * products or when a config option is removed.
 */
class ShippingMethodConfigCleanupSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
        private readonly ShippingMethodConfigCleaner $configCleaner,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ShippingMethodConfigDefinition::ENTITY_WRITTEN_EVENT => 'cleanupShippingMethodConfig',
        ];
    }

    public function cleanupShippingMethodConfig(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $configIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $configIds[] = $writeResult->getPrimaryKey();
        }

        if (count($configIds) === 0) {
            return;
        }

        /** @var ShippingMethodConfigCollection $shippingMethodConfigs */
        $shippingMethodConfigs = $this->entityManager->findBy(
            ShippingMethodConfigDefinition::class,
            ['id' => $configIds],
            $event->getContext(),
            ['carrier'],
        );

        /** @var ShippingMethodConfigEntity $config */
        foreach ($shippingMethodConfigs as $config) {
            $carrier = $config->getCarrier();
            if (!$carrier) {
                continue;
            }

            $originalShipmentConfig = $config->getShipmentConfig() ?? [];
            $originalReturnShipmentConfig = $config->getReturnShipmentConfig() ?? [];
            $originalStorefrontConfig = $config->getStorefrontConfig() ?? [];

            $cleanedShipmentConfig = $this->configCleaner->cleanConfig(
                $originalShipmentConfig,
                $carrier->getShipmentConfigOptions()['elements'] ?? [],
            );
            $cleanedReturnShipmentConfig = $this->configCleaner->cleanConfig(
                $originalReturnShipmentConfig,
                $carrier->getReturnShipmentConfigOptions()['elements'] ?? [],
            );
            $cleanedStorefrontConfig = $this->configCleaner->cleanConfig(
                $originalStorefrontConfig,
                $carrier->getStorefrontConfigOptions()['elements'] ?? [],
            );

            // Check if any config was actually modified to avoid unnecessary writes
            if (
                count($cleanedShipmentConfig) === count($originalShipmentConfig)
                && count($cleanedReturnShipmentConfig) === count($originalReturnShipmentConfig)
                && count($cleanedStorefrontConfig) === count($originalStorefrontConfig)
            ) {
                continue;
            }

            // Use direct SQL UPDATE to avoid triggering another written event (which would cause an infinite loop)
            $this->connection->executeStatement(
                'UPDATE `pickware_shipping_shipping_method_config`
                SET
                    `shipment_config` = :shipmentConfig,
                    `return_shipment_config` = :returnShipmentConfig,
                    `storefront_config` = :storefrontConfig
                WHERE `id` = :id',
                [
                    'id' => hex2bin($config->getId()),
                    'shipmentConfig' => Json::stringify($cleanedShipmentConfig),
                    'returnShipmentConfig' => Json::stringify($cleanedReturnShipmentConfig),
                    'storefrontConfig' => Json::stringify($cleanedStorefrontConfig),
                ],
            );
        }
    }
}
