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

use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PickwareErpStarter\SingleItemOrder\SingleItemOrderDevFeatureFlag;
use Pickware\PickwareErpStarter\VirtualEntityAssociation\VirtualEntityAssociationCriteriaFilterResolver;
use Pickware\PickwareErpStarter\VirtualEntityAssociation\VirtualEntityAssociationDefinition;
use Pickware\PickwareErpStarter\VirtualEntityAssociation\VirtualEntityAssociationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @see DefinitionInstanceRegistryDecorator::getRepository()
 * @extends EntityRepository<EntityCollection<covariant Entity>>
 * @phpstan-ignore-next-line class.extendsFinalByPhpDoc
 */
#[Exclude]
class EntityRepositoryDecorator extends EntityRepository
{
    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $decoratedInstance
     * @param iterable<VirtualEntityAssociationDefinition<string, mixed, Entity>> $virtualEntityAssociationDefinitions
     */
    public function __construct(
        private readonly EntityRepository $decoratedInstance,
        private readonly VirtualEntityAssociationCriteriaFilterResolver $virtualEntityAssociationCriteriaFilterResolver,
        private readonly iterable $virtualEntityAssociationDefinitions,
        private readonly FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return EntityDefinition<Entity>
     */
    public function getDefinition(): EntityDefinition
    {
        return $this->decoratedInstance->getDefinition();
    }

    /**
     * @return EntitySearchResult<EntityCollection<covariant Entity>>
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        if (!$this->featureFlagService->isActive(SingleItemOrderDevFeatureFlag::NAME)) {
            return $this->decoratedInstance->search($criteria, $context);
        }

        $originalCriteria = clone $criteria;

        $this->virtualEntityAssociationCriteriaFilterResolver->resolveVirtualEntityAssociationFilters($criteria);
        $virtualEntityAssociationTree = $this->collectAndRemoveVirtualEntityAssociations($criteria);

        $searchResult = $this->decoratedInstance->search($criteria, $context);

        if ($virtualEntityAssociationTree === null) {
            return $this->replaceCriteriaInEntitySearchResult($searchResult, $originalCriteria);
        }

        $this->collectParentEntitiesOfVirtualEntitiesFromEntities(
            $searchResult->getElements(),
            $virtualEntityAssociationTree,
        );

        $this->injectVirtualEntityIntoEntities(
            $searchResult->getElements(),
            $virtualEntityAssociationTree,
        );

        return $this->replaceCriteriaInEntitySearchResult($searchResult, $originalCriteria);
    }

    public function aggregate(Criteria $criteria, Context $context): AggregationResultCollection
    {
        if (!$this->featureFlagService->isActive(SingleItemOrderDevFeatureFlag::NAME)) {
            return $this->decoratedInstance->aggregate($criteria, $context);
        }

        $this->virtualEntityAssociationCriteriaFilterResolver->resolveVirtualEntityAssociationFilters($criteria);

        return $this->decoratedInstance->aggregate($criteria, $context);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        if (!$this->featureFlagService->isActive(SingleItemOrderDevFeatureFlag::NAME)) {
            return $this->decoratedInstance->searchIds($criteria, $context);
        }

        $this->virtualEntityAssociationCriteriaFilterResolver->resolveVirtualEntityAssociationFilters($criteria);

        return $this->decoratedInstance->searchIds($criteria, $context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->update($data, $context);
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->upsert($data, $context);
    }

    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->create($data, $context);
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        return $this->decoratedInstance->delete($ids, $context);
    }

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string
    {
        return $this->decoratedInstance->createVersion($id, $context, $name, $versionId);
    }

    public function merge(string $versionId, Context $context): void
    {
        $this->decoratedInstance->merge($versionId, $context);
    }

    public function clone(
        string $id,
        Context $context,
        ?string $newId = null,
        ?CloneBehavior $behavior = null,
    ): EntityWrittenContainerEvent {
        return $this->decoratedInstance->clone($id, $context, $newId, $behavior);
    }

    /**
     * Builds a tree which mirrors the structure of the existing associations in the criteria. Along the way,
     * information about virtual entity associations is collected and such associations are removed from the criteria.
     */
    private function collectAndRemoveVirtualEntityAssociations(Criteria $criteria): ?VirtualEntityAssociationResolutionTreeNode
    {
        /** @var list<VirtualEntityAssociationDefinition<string, mixed, Entity>> $virtualEntityDefinitionsAtPath */
        $virtualEntityDefinitionsAtPath = [];
        /** @var array<string, VirtualEntityAssociationResolutionTreeNode> $childNodesByAssociationName */
        $childNodesByAssociationName = [];
        foreach ($criteria->getAssociations() as $associationKey => $associationCriteria) {
            $definition = $this->getVirtualEntityAssociationDefinitionByName($associationKey);
            if ($definition !== null) {
                if (count($associationCriteria->getFilters()) > 0) {
                    throw VirtualEntityAssociationException::filtersOnVirtualEntityAssociationNotSupported($associationKey);
                }

                $virtualEntityDefinitionsAtPath[] = $definition;
                $criteria->removeAssociation($associationKey);
            } else {
                $otherTree = $this->collectAndRemoveVirtualEntityAssociations($associationCriteria);
                if ($otherTree !== null) {
                    $childNodesByAssociationName[$associationKey] = $otherTree;
                }
            }
        }

        if (empty($virtualEntityDefinitionsAtPath) && empty($childNodesByAssociationName)) {
            return null;
        }

        return new VirtualEntityAssociationResolutionTreeNode($virtualEntityDefinitionsAtPath, $childNodesByAssociationName);
    }

    /**
     * Traverses the tree of entities and collects parent entities of virtual entity associations.
     *
     * @param iterable<Entity> $entities
     */
    private function collectParentEntitiesOfVirtualEntitiesFromEntities(iterable $entities, VirtualEntityAssociationResolutionTreeNode $treeNode): void
    {
        foreach ($entities as $entity) {
            if (!empty($treeNode->getVirtualEntityAssociations())) {
                // We know that these entities are of the desired type due to criteria validation
                $treeNode->addResolvedEntity($entity);
            }

            foreach ($treeNode->getChildNodesByAssociationName() as $associationKey => $nestedAssociation) {
                if (!$entity->has($associationKey)) {
                    continue;
                }

                $this->collectParentEntitiesOfVirtualEntitiesFromEntities(
                    $this->getAssociationValueAsIterable($entity->get($associationKey)),
                    $treeNode->getChildNodesByAssociationName()[$associationKey],
                );
            }
        }
    }

    /**
     * Injects virtual entity associations into parent entities using the collected parent entities from
     * {@link collectParentEntitiesOfVirtualEntitiesFromEntities}.
     *
     * @param iterable<Entity> $entities
     */
    private function injectVirtualEntityIntoEntities(
        iterable $entities,
        VirtualEntityAssociationResolutionTreeNode $virtualEntityAssociationTree,
    ): void {
        if (count($virtualEntityAssociationTree->getResolvedEntitiesById()) > 0) {
            $parentEntities = array_values($virtualEntityAssociationTree->getResolvedEntitiesById());
            foreach ($virtualEntityAssociationTree->getVirtualEntityAssociations() as $virtualEntityAssociation) {
                $virtualEntityAssociation->injectAssociationsIntoParentEntities($parentEntities);
            }
        }

        foreach ($entities as $entity) {
            foreach ($virtualEntityAssociationTree->getChildNodesByAssociationName() as $associationKey => $treeNode) {
                if (!$entity->has($associationKey)) {
                    continue;
                }

                $this->injectVirtualEntityIntoEntities(
                    $this->getAssociationValueAsIterable($entity->get($associationKey)),
                    $treeNode,
                );
            }
        }
    }

    /**
     * @template Collection of EntityCollection<covariant Entity>
     * @param EntitySearchResult<Collection> $searchResult
     * @return EntitySearchResult<Collection>
     */
    private function replaceCriteriaInEntitySearchResult(
        EntitySearchResult $searchResult,
        Criteria $criteria,
    ): EntitySearchResult {
        return new EntitySearchResult(
            $searchResult->getEntity(),
            $searchResult->getTotal(),
            $searchResult->getEntities(),
            $searchResult->getAggregations(),
            $criteria,
            $searchResult->getContext(),
        );
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

    /**
     * @return iterable<Entity>
     */
    private function getAssociationValueAsIterable(mixed $associationValue): iterable
    {
        if (!is_iterable($associationValue)) {
            return [$associationValue];
        }

        return $associationValue;
    }
}
