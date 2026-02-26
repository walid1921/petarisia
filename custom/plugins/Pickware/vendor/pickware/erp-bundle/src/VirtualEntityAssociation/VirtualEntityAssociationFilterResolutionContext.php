<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\VirtualEntityAssociation;

use LogicException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;

/**
 * @phpstan-type Injection array{
 *     filterContainer: FilterCollectionContainerAdapter,
 *     fieldPrefix: string,
 *     virtualEntityAssociationDefinition: VirtualEntityAssociationDefinition<string, mixed, Entity>,
 *     virtualFieldFilters: SingleFieldFilter[]
 * }
 */
class VirtualEntityAssociationFilterResolutionContext
{
    /**
     * @var list<Injection>
     */
    private array $filterInjections = [];

    /**
     * @param VirtualEntityAssociationDefinition<string, mixed, Entity> $virtualEntityAssociationDefinition
     * @param SingleFieldFilter[] $virtualFieldFilters
     */
    public function addInjectionPoint(
        FilterCollectionContainerAdapter $injectionPoint,
        string $fieldPrefix,
        VirtualEntityAssociationDefinition $virtualEntityAssociationDefinition,
        array $virtualFieldFilters,
    ): void {
        $this->filterInjections[] = [
            'filterContainer' => $injectionPoint,
            'fieldPrefix' => $fieldPrefix,
            'virtualEntityAssociationDefinition' => $virtualEntityAssociationDefinition,
            'virtualFieldFilters' => $virtualFieldFilters,
        ];
    }

    public function hasFiltersToApply(): bool
    {
        return count($this->filterInjections) > 0;
    }

    public function applyFilters(): void
    {
        if (count($this->filterInjections) === 0) {
            return;
        }

        foreach ($this->groupFilterInjectionsByDefinition() as $group) {
            $virtualEntityAssociationDefinition = $group['definition'];

            $injectionContexts = array_map(
                fn(array $injection): VirtualEntityAssociationFilterInjectionContext => new VirtualEntityAssociationFilterInjectionContext(
                    filterContainer: $injection['filterContainer'],
                    fieldPrefix: $injection['fieldPrefix'],
                    virtualEntityAssociationDefinition: $virtualEntityAssociationDefinition,
                    virtualFieldFilters: $injection['virtualFieldFilters'],
                ),
                $group['injections'],
            );

            $preProcessResult = $virtualEntityAssociationDefinition->preProcessFilterInjections($injectionContexts);

            foreach ($group['injections'] as $index => $injection) {
                $filterContainer = $injection['filterContainer'];
                $fieldPrefix = $injection['fieldPrefix'];
                $filters = $virtualEntityAssociationDefinition->createRealFiltersForVirtualFilters(
                    $injectionContexts[$index],
                    $preProcessResult,
                );
                $expectedPrefix = $fieldPrefix === '' ? '' : $fieldPrefix . '.';
                foreach ($filters as $filter) {
                    if (!str_starts_with($filter->getField(), $expectedPrefix)) {
                        throw new LogicException('Replacement filter contains unexpected filter field: ' . $filter->getField());
                    }

                    $filterContainer->addFilter($filter);
                }
            }
        }
    }

    /**
     * @return list<array{
     *     definition: VirtualEntityAssociationDefinition<string, mixed, Entity>,
     *     injections: list<Injection>
     * }>
     */
    private function groupFilterInjectionsByDefinition(): array
    {
        $groups = [];

        foreach ($this->filterInjections as $injection) {
            $definition = $injection['virtualEntityAssociationDefinition'];
            $groupKey = $definition->getAssociationName();
            $groups[$groupKey] ??= [
                'definition' => $definition,
                'injections' => [],
            ];
            $groups[$groupKey]['injections'][] = $injection;
        }

        return array_values($groups);
    }
}
