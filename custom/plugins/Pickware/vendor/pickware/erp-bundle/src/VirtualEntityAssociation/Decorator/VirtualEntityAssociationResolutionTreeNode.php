<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\VirtualEntityAssociation\Decorator;

use Pickware\PickwareErpStarter\VirtualEntityAssociation\VirtualEntityAssociationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class VirtualEntityAssociationResolutionTreeNode
{
    /**
     * @var array<string, Entity>
     */
    private array $resolvedEntitiesById = [];

    /**
     * @param array<VirtualEntityAssociationDefinition<string, mixed, Entity>> $virtualEntityAssociations
     * @param array<string, VirtualEntityAssociationResolutionTreeNode> $childNodesByAssociationName
     */
    public function __construct(
        private readonly array $virtualEntityAssociations,
        private readonly array $childNodesByAssociationName,
    ) {}

    public function addResolvedEntity(Entity $entity): void
    {
        $this->resolvedEntitiesById[$entity->get('id')] = $entity;
    }

    /**
     * @return array<string, Entity>
     */
    public function getResolvedEntitiesById(): array
    {
        return $this->resolvedEntitiesById;
    }

    /**
     * @return VirtualEntityAssociationDefinition<string, mixed, Entity>[]
     */
    public function getVirtualEntityAssociations(): array
    {
        return $this->virtualEntityAssociations;
    }

    /**
     * @return array<string, VirtualEntityAssociationResolutionTreeNode>
     */
    public function getChildNodesByAssociationName(): array
    {
        return $this->childNodesByAssociationName;
    }
}
