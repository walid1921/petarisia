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

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class VirtualEntityAssociationCriteriaFilterResolver
{
    /**
     * @param iterable<VirtualEntityAssociationDefinition<string, mixed, Entity>> $virtualEntityAssociationDefinitions
     */
    public function __construct(
        #[AutowireIterator('pickware_erp.virtual_entity_association')]
        private readonly iterable $virtualEntityAssociationDefinitions,
    ) {}

    /**
     * Uses all registered {@link VirtualEntityAssociationDefinition}s to resolve filters on virtual entity
     * associations into real filters. All virtual filters are automatically removed. The given criteria is
     * modified in-place.
     */
    public function resolveVirtualEntityAssociationFilters(Criteria $criteria): void
    {
        $this->assertNoFiltersOnVirtualEntityAssociations($criteria);

        $filterContext = new VirtualEntityAssociationFilterResolutionContext();
        $this->buildVirtualEntityAssociationFilterResolutionContext(new FilterCollectionContainerAdapter($criteria), $filterContext);

        if (!$filterContext->hasFiltersToApply()) {
            return;
        }

        $criteria->removeState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $filterContext->applyFilters();
    }

    private function assertNoFiltersOnVirtualEntityAssociations(Criteria $criteria): void
    {
        foreach ($criteria->getAssociations() as $associationName => $associationCriteria) {
            if ($this->getVirtualEntityAssociationDefinitionByName($associationName) !== null && count($associationCriteria->getFilters()) > 0) {
                throw VirtualEntityAssociationException::filtersOnVirtualEntityAssociationNotSupported($associationName);
            }

            if (count($associationCriteria->getAssociations()) > 0) {
                $this->assertNoFiltersOnVirtualEntityAssociations($associationCriteria);
            }
        }
    }

    /**
     * Recursively processes filters from the container, identifies filters targeting virtual entity associations,
     * and registers them as injection points in the context for later application.
     */
    private function buildVirtualEntityAssociationFilterResolutionContext(FilterCollectionContainerAdapter $filterContainer, VirtualEntityAssociationFilterResolutionContext $context): void
    {
        $filters = $filterContainer->getFilters();
        $filterContainer->resetFilters();

        /** @var array<string, array<string, list<SingleFieldFilter>>> $virtualEntityAssociationByFieldPrefix */
        $virtualEntityAssociationByFieldPrefix = [];

        foreach ($filters as $filter) {
            if ($filter instanceof MultiFilter) {
                $wrapper = new FilterCollectionContainerAdapter($filter);
                $this->buildVirtualEntityAssociationFilterResolutionContext($wrapper, $context);
                $filterContainer->addFilter($wrapper->toUnderlyingContainer());

                continue;
            }

            // Filters on virtual entity association can only be SingleFieldFilters
            if (!($filter instanceof SingleFieldFilter)) {
                $filterContainer->addFilter($filter);

                continue;
            }

            $virtualEntityAssociationAndFieldPrefix = $this->extractVirtualEntityAssociationAndFieldPrefix($filter->getField());
            if ($virtualEntityAssociationAndFieldPrefix === null) {
                $filterContainer->addFilter($filter);

                continue;
            }

            $fieldPrefix = $virtualEntityAssociationAndFieldPrefix['fieldPrefix'];
            $virtualEntityAssociation = $virtualEntityAssociationAndFieldPrefix['virtualEntityAssociation'];
            $virtualEntityAssociationByFieldPrefix[$fieldPrefix][$virtualEntityAssociation] ??= [];
            $virtualEntityAssociationByFieldPrefix[$fieldPrefix][$virtualEntityAssociation][] = $filter;
        }

        foreach ($virtualEntityAssociationByFieldPrefix as $fieldPrefix => $associations) {
            foreach ($associations as $virtualEntityAssociation => $filters) {
                $context->addInjectionPoint(
                    injectionPoint: $filterContainer,
                    fieldPrefix: $fieldPrefix,
                    virtualEntityAssociationDefinition: $this->getVirtualEntityAssociationDefinitionByName($virtualEntityAssociation),
                    virtualFieldFilters: $filters,
                );
            }
        }
    }

    /**
     * Extracts the field prefix from a virtual filter field. Also verifies that the field name is a valid filter
     * field.
     * E.g., 'test.a.b.pickwareErpSingleItemOrder.isOpenSingleItemOrder' returns 'test.a.b'
     * E.g., 'pickwareErpSingleItemOrder.isOpenSingleItemOrder' returns ''
     *
     * @return ?array{virtualEntityAssociation: string, fieldPrefix: string}
     */
    private function extractVirtualEntityAssociationAndFieldPrefix(string $filterField): ?array
    {
        $parts = explode('.', $filterField);
        $prefixLength = 0;

        foreach ($parts as $index => $part) {
            foreach ($this->virtualEntityAssociationDefinitions as $definition) {
                if ($definition->getAssociationName() === $part) {
                    if ($index !== count($parts) - 2) {
                        throw VirtualEntityAssociationException::nestedFiltersNotSupported($filterField);
                    }

                    $fieldName = $parts[$index + 1];
                    if (!in_array($fieldName, $definition->getVirtualFilterFieldNames(), true)) {
                        throw VirtualEntityAssociationException::invalidFilterField($fieldName, $definition->getAssociationName());
                    }

                    return [
                        'virtualEntityAssociation' => $part,
                        'fieldPrefix' => $prefixLength === 0 ? '' : mb_substr($filterField, start: 0, length: $prefixLength - 1),
                    ];
                }
            }
            $prefixLength += mb_strlen($part) + 1;
        }

        return null;
    }

    /**
     * @return ?VirtualEntityAssociationDefinition<string, mixed, Entity>
     */
    private function getVirtualEntityAssociationDefinitionByName(string $associationName): ?VirtualEntityAssociationDefinition
    {
        foreach ($this->virtualEntityAssociationDefinitions as $definition) {
            if ($definition->getAssociationName() === $associationName) {
                return $definition;
            }
        }

        return null;
    }
}
