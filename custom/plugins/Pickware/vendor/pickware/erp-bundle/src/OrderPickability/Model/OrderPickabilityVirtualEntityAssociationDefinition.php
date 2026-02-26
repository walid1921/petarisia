<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability\Model;

use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityCalculator;
use Pickware\PickwareErpStarter\OrderPickability\OrderPickabilityException;
use Pickware\PickwareErpStarter\VirtualEntityAssociation\VirtualEntityAssociationDefinition;
use Pickware\PickwareErpStarter\VirtualEntityAssociation\VirtualEntityAssociationFilterInjectionContext;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

/**
 * @phpstan-type OrderPickabilityPreProcessResult array{orderPickabilities: OrderPickabilityCollection, orderIdsWithoutPickabilities: ?list<string>}
 * @implements VirtualEntityAssociationDefinition<'id' | 'warehouseId' | 'orderPickabilityStatus', OrderPickabilityPreProcessResult, OrderEntity>
 */
class OrderPickabilityVirtualEntityAssociationDefinition implements VirtualEntityAssociationDefinition
{
    public function __construct(
        private readonly OrderPickabilityCalculator $orderPickabilityCalculator,
    ) {}

    public function getAssociationName(): string
    {
        return 'pickwareErpOrderPickabilities';
    }

    public function getVirtualFilterFieldNames(): array
    {
        return [
            'id',
            'warehouseId',
            'orderPickabilityStatus',
        ];
    }

    /**
     * @param list<VirtualEntityAssociationFilterInjectionContext<static>> $contexts
     * @return OrderPickabilityPreProcessResult
     */
    public function preProcessFilterInjections(array $contexts): mixed
    {
        $warehouseIds = [];
        $hasUnknownPickabilityFilter = false;

        foreach ($contexts as $context) {
            $contextWarehouseIds = $context->getEqualsFilterValuesOnVirtualField('warehouseId');
            $contextPickabilityStatus = $context->getEqualsFilterValuesOnVirtualField('orderPickabilityStatus');
            $idFilters = $context->getEqualsFilterValuesOnVirtualField('id');
            if (array_any($idFilters, fn(mixed $value) => $value !== null)) {
                throw OrderPickabilityException::unexpectedFilterOnIdField();
            }

            $isUnknownPickabilityFilter = count($idFilters) > 0;
            if ($isUnknownPickabilityFilter && (count($contextWarehouseIds) > 0 || count($contextPickabilityStatus) > 0)) {
                throw OrderPickabilityException::incompatibleFilterCombination();
            }
            if (count($contextWarehouseIds) > 0 && count($contextPickabilityStatus) === 0) {
                throw OrderPickabilityException::missingPickabilityStatusFilter();
            }
            if (count($contextWarehouseIds) === 0 && count($contextPickabilityStatus) > 0) {
                throw OrderPickabilityException::missingWarehouseFilter();
            }

            if ($isUnknownPickabilityFilter) {
                $hasUnknownPickabilityFilter = true;

                continue;
            }

            $warehouseIds[] = $contextWarehouseIds;
        }

        // Filter out null warehouse IDs which will cause the pickability calculation to return no results. See https://github.com/pickware/shopware-plugins/issues/7031
        $warehouseIds = array_values(array_unique(array_filter(array_merge(...$warehouseIds))));
        $orderPickabilities = count($warehouseIds) > 0 ? $this->orderPickabilityCalculator->calculateOrderPickabilitiesForWarehouses($warehouseIds) : new OrderPickabilityCollection();

        return [
            'orderPickabilities' => $orderPickabilities,
            'orderIdsWithoutPickabilities' => $hasUnknownPickabilityFilter ? $this->orderPickabilityCalculator->getOrderIdsWithoutPickabilities() : null,
        ];
    }

    /**
     * @param OrderPickabilityPreProcessResult $preProcessResult
     */
    public function createRealFiltersForVirtualFilters(VirtualEntityAssociationFilterInjectionContext $context, mixed $preProcessResult): array
    {
        $orderPickabilities = $preProcessResult['orderPickabilities'];
        $orderIdsWithoutPickabilities = $preProcessResult['orderIdsWithoutPickabilities'];

        $warehouseIds = $context->getEqualsFilterValuesOnVirtualField('warehouseId');
        $orderPickabilityStatus = $context->getEqualsFilterValuesOnVirtualField('orderPickabilityStatus');
        $isUnknownPickabilityFilter = count($context->getEqualsFilterValuesOnVirtualField('id')) > 0;

        $fieldPrefix = $context->getFieldPrefix();
        $fieldName = $fieldPrefix === '' ? 'id' : $fieldPrefix . '.id';

        if ($isUnknownPickabilityFilter) {
            $filteredOrderIds = $orderIdsWithoutPickabilities;
        } else {
            // Filter out null warehouse IDs which will cause the pickability calculation to return no results. See https://github.com/pickware/shopware-plugins/issues/7031
            $warehouseIds = array_filter($warehouseIds);

            $filteredOrderIds = $orderPickabilities
                ->filter(fn(OrderPickabilityEntity $pickability) => in_array($pickability->getWarehouseId(), $warehouseIds, true))
                ->filter(fn(OrderPickabilityEntity $pickability) => in_array($pickability->getOrderPickabilityStatus(), $orderPickabilityStatus, true))
                ->map(fn(OrderPickabilityEntity $pickability) => $pickability->getOrderId());
        }

        return [
            new EqualsAnyFilter($fieldName, array_values($filteredOrderIds)),
        ];
    }

    /**
     * @param non-empty-list<OrderEntity> $parentEntities
     */
    public function injectAssociationsIntoParentEntities(array $parentEntities): void
    {
        $orderIds = array_map(
            fn(OrderEntity $order): string => $order->getId(),
            $parentEntities,
        );
        $orderPickabilities = $this->orderPickabilityCalculator->calculateOrderPickabilitiesForOrders($orderIds);
        $pickabilitiesByOrderId = [];
        foreach ($orderPickabilities as $pickabilityEntity) {
            $pickabilitiesByOrderId[$pickabilityEntity->getOrderId()] ??= new OrderPickabilityCollection();
            $pickabilitiesByOrderId[$pickabilityEntity->getOrderId()]->add($pickabilityEntity);
        }

        foreach ($parentEntities as $order) {
            if (!array_key_exists($order->getId(), $pickabilitiesByOrderId)) {
                continue;
            }

            $order->addExtension($this->getAssociationName(), $pickabilitiesByOrderId[$order->getId()]);
        }
    }
}
