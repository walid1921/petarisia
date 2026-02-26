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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SingleFieldFilter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Defines a virtual entity association that can be added to Shopware entities without modifying the database schema.
 *
 * Virtual associations behave like real DAL associations from the consumer's perspective: they can be filtered on
 * in search criteria and get loaded/injected into parent entities. However, instead of being stored in the database,
 * they are computed at runtime.
 *
 * @template VirtualFieldName of string
 * @template PreProcessResult
 * @template ParentEntity of Entity
 */
#[AutoconfigureTag('pickware_erp.virtual_entity_association')]
interface VirtualEntityAssociationDefinition
{
    /**
     * The association name on the parent entity (e.g., 'pickwareErpSingleItemOrder')
     */
    public function getAssociationName(): string;

    /**
     * List of field names on the virtual entity that can be used as filters
     *
     * @return list<VirtualFieldName>
     */
    public function getVirtualFilterFieldNames(): array;

    /**
     * Called once before processing all filter injections for a criteria.
     * Use this to validate the filters and pre-calculate expensive data that will be shared across all injections.
     *
     * @param list<VirtualEntityAssociationFilterInjectionContext<static>> $contexts
     * @return PreProcessResult Any data structure you need. Will be passed to {@link createRealFiltersForVirtualFilters}
     */
    public function preProcessFilterInjections(array $contexts): mixed;

    /**
     * Factory method to create replacement filters for a single injection. The returned filters must use the field
     * prefix from {@link VirtualEntityAssociationFilterInjectionContext::getFieldPrefix()}.
     *
     * @param VirtualEntityAssociationFilterInjectionContext<static> $context
     * @param PreProcessResult $preProcessResult The result of {@link preProcessFilterInjections}
     * @return list<SingleFieldFilter> Replacement filters
     */
    public function createRealFiltersForVirtualFilters(
        VirtualEntityAssociationFilterInjectionContext $context,
        mixed $preProcessResult,
    ): array;

    /**
     * Injects the virtual entity association into the parent entities.
     *
     * @param non-empty-list<ParentEntity> $parentEntities Parent entities that requested this association
     */
    public function injectAssociationsIntoParentEntities(array $parentEntities): void;
}
