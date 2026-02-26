<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\InstallationLibrary\DocumentType;

use Pickware\DalBundle\EntityManager;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Framework\Context;

/**
 * Sets default configuration values for existing document types without overwriting user-configured values.
 *
 * This is useful for adding new configuration options to Shopware's built-in document types (like invoice,
 * delivery_note, storno) during plugin installation.
 */
class DocumentConfigDefaultsInstaller
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * Ensures that default configuration values exist for the given document type.
     *
     * Only adds values for keys that don't already exist in the configuration, preserving any user-configured values.
     *
     * @param string $documentTypeTechnicalName The technical name of the document type (e.g., 'invoice', 'delivery_note')
     * @param array<string, mixed> $defaults Key-value pairs of configuration defaults to set
     */
    public function ensureConfigDefaults(
        string $documentTypeTechnicalName,
        array $defaults,
        Context $context,
    ): void {
        if (count($defaults) === 0) {
            return;
        }

        $this->entityManager->runInTransactionWithRetry(
            function() use ($documentTypeTechnicalName, $defaults, $context): void {
                $globalBaseConfigs = $this->entityManager->findBy(
                    DocumentBaseConfigDefinition::class,
                    [
                        'documentType.technicalName' => $documentTypeTechnicalName,
                        'global' => true,
                    ],
                    $context,
                );

                if ($globalBaseConfigs->count() === 0) {
                    return;
                }

                /** @var DocumentBaseConfigEntity $baseConfig */
                $baseConfig = $globalBaseConfigs->first();
                $currentConfig = $baseConfig->getConfig() ?? [];

                $updatedConfig = [
                    ...$defaults,
                    ...$currentConfig,
                ];
                if ($updatedConfig === $currentConfig) {
                    return;
                }

                $this->entityManager->update(
                    DocumentBaseConfigDefinition::class,
                    [
                        [
                            'id' => $baseConfig->getId(),
                            'config' => $updatedConfig,
                        ],
                    ],
                    $context,
                );
            },
        );
    }
}
