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

/**
 * Cleans shipping method configurations by removing config values that are not relevant anymore based on their
 * showConditions. This ensures that old config values don't persist when switching between different products or
 * changing other config options that affect the visibility of certain fields.
 */
class ShippingMethodConfigCleaner
{
    /**
     * Removes all config values from the given configuration that should not be shown based on their showConditions.
     * Context conditions (starting with $context.) are ignored as they are transient and should not affect persistence.
     *
     * @param array<string, mixed> $config The configuration to clean (e.g., shipmentConfig, returnShipmentConfig, storefrontConfig)
     * @param array<int, array<string, mixed>> $configOptions The config options definition (e.g., shipmentConfigOptions['elements'] from carrier)
     * @return array<string, mixed> The cleaned configuration with irrelevant values removed
     */
    public function cleanConfig(array $config, array $configOptions): array
    {
        $cleanedConfig = $config;

        foreach (array_keys($config) as $configKey) {
            $configOption = $this->findConfigOptionByName($configKey, $configOptions);
            if (!$this->isConfigOptionShown($configOption, $config)) {
                unset($cleanedConfig[$configKey]);
            }
        }

        return $cleanedConfig;
    }

    /**
     * Finds a config option by name in the config options definition.
     * Handles nested groups by searching recursively and merges group showConditions with element showConditions.
     *
     * @param array<int, array<string, mixed>> $configOptions The config options definition
     * @param array<string, mixed> $inheritedShowConditions Conditions inherited from parent groups
     * @return array<string, mixed>|null The config option definition with merged showConditions or null if not found
     */
    private function findConfigOptionByName(
        string $configOptionName,
        array $configOptions,
        array $inheritedShowConditions = [],
    ): ?array {
        foreach ($configOptions as $option) {
            $currentShowConditions = array_merge(
                $inheritedShowConditions,
                $option['showConditions'] ?? [],
            );

            if (isset($option['name']) && $option['name'] === $configOptionName) {
                // Merge inherited conditions with element's own conditions
                if (!empty($currentShowConditions)) {
                    $option['showConditions'] = $currentShowConditions;
                }

                return $option;
            }

            // Check in groups and pass down group's showConditions
            if (($option['type'] ?? null) === 'group' && isset($option['elements'])) {
                $foundOption = $this->findConfigOptionByName(
                    $configOptionName,
                    $option['elements'],
                    $currentShowConditions,
                );
                if ($foundOption !== null) {
                    return $foundOption;
                }
            }
        }

        return null;
    }

    /**
     * Checks if a config option should be shown based on its showConditions.
     * Context conditions (starting with $context.) are ignored.
     *
     * @param array<string, mixed>|null $configOption The config option definition
     * @param array<string, mixed> $configuration The current configuration values
     * @return bool True if the option should be shown, false if it should be hidden/removed
     */
    private function isConfigOptionShown(?array $configOption, array $configuration): bool
    {
        // If no config option found or no showConditions defined, keep the value
        if ($configOption === null || !isset($configOption['showConditions'])) {
            return true;
        }

        return $this->checkAllConditions($configOption['showConditions'], $configuration);
    }

    /**
     * Checks if all conditions are met with the current configuration.
     * Context conditions (keys starting with $context.) are ignored as they are transient.
     *
     * @param array<string, mixed> $conditions The conditions to check (e.g., ['product' => ['V01PAK', 'V62KP']])
     * @param array<string, mixed> $configuration The current configuration values
     * @return bool True if all conditions are met, false otherwise
     */
    private function checkAllConditions(array $conditions, array $configuration): bool
    {
        foreach ($conditions as $key => $expectedValue) {
            // Skip context conditions - they should not affect persistence
            if (str_starts_with($key, '$context.')) {
                continue;
            }

            $actualValue = $configuration[$key] ?? null;

            // If expected value is an array, check if actual value is in the array
            if (is_array($expectedValue)) {
                if (!in_array($actualValue, $expectedValue, true)) {
                    return false;
                }
            } else {
                // Otherwise, check for exact match
                if ($actualValue !== $expectedValue) {
                    return false;
                }
            }
        }

        return true;
    }
}
