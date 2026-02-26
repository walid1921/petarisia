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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @template VirtualAssociation of VirtualEntityAssociationDefinition<covariant string, covariant mixed, covariant Entity>
 * @phpstan-type VirtualFieldName template-type<VirtualAssociation, VirtualEntityAssociationDefinition, 'VirtualFieldName'>
 */
#[Exclude]
class VirtualEntityAssociationFilterInjectionContext
{
    /**
     * @param SingleFieldFilter[] $virtualFieldFilters
     * @param VirtualAssociation $virtualEntityAssociationDefinition
     */
    public function __construct(
        private readonly FilterCollectionContainerAdapter $filterContainer,
        private readonly string $fieldPrefix,
        private readonly VirtualEntityAssociationDefinition $virtualEntityAssociationDefinition,
        private readonly array $virtualFieldFilters,
    ) {}

    /**
     * @param VirtualFieldName $fieldName
     * @return array<SingleFieldFilter>
     */
    public function getFiltersOnVirtualField(string $fieldName): array
    {
        $fieldPrefix = $this->fieldPrefix === '' ? '' : $this->fieldPrefix . '.';
        $expectedFieldName = $fieldPrefix . $this->virtualEntityAssociationDefinition->getAssociationName() . '.' . $fieldName;

        return array_values(array_filter(
            $this->virtualFieldFilters,
            fn(SingleFieldFilter $filter): bool => $filter->getField() === $expectedFieldName,
        ));
    }

    /**
     * @param VirtualFieldName $fieldName
     * @return array<mixed>
     */
    public function getEqualsFilterValuesOnVirtualField(string $fieldName): array
    {
        return $this->getEqualsFilterValues($this->getFiltersOnVirtualField($fieldName));
    }

    /**
     * @return array<SingleFieldFilter>
     */
    public function getFiltersOnParentEntityField(string $fieldName): array
    {
        $expectedFieldName = $this->fieldPrefix === '' ? $fieldName : $this->fieldPrefix . '.' . $fieldName;

        return array_values(array_filter(
            $this->filterContainer->getFilters(),
            fn(Filter $filter): bool => $filter instanceof SingleFieldFilter && $filter->getField() === $expectedFieldName,
        ));
    }

    /**
     * @return array<mixed>
     */
    public function getEqualsFilterValuesOnParentEntityField(string $fieldName): array
    {
        return $this->getEqualsFilterValues($this->getFiltersOnParentEntityField($fieldName));
    }

    public function getFieldPrefix(): string
    {
        return $this->fieldPrefix;
    }

    /**
     * @param SingleFieldFilter[] $filters
     * @return array<mixed>
     */
    private function getEqualsFilterValues(array $filters): array
    {
        return array_values(array_merge(...array_map(
            fn(SingleFieldFilter $filter): array => match (true) {
                $filter instanceof EqualsFilter => [$filter->getValue()],
                $filter instanceof EqualsAnyFilter => $filter->getValue(),
                default => throw VirtualEntityAssociationException::unsupportedFilterType(get_class($filter)),
            },
            $filters,
        )));
    }
}
