<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\SingleItemOrder\Model;

use Pickware\PickwareErpStarter\SingleItemOrder\SingleItemOrderCalculator;
use Pickware\PickwareErpStarter\SingleItemOrder\SingleItemOrderException;
use Pickware\PickwareErpStarter\VirtualEntityAssociation\VirtualEntityAssociationDefinition;
use Pickware\PickwareErpStarter\VirtualEntityAssociation\VirtualEntityAssociationFilterInjectionContext;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @implements VirtualEntityAssociationDefinition<'isOpenSingleItemOrder', list<string>, OrderEntity>
 */
class SingleItemOrderVirtualEntityAssociationDefinition implements VirtualEntityAssociationDefinition
{
    public function __construct(
        private readonly SingleItemOrderCalculator $singleItemOrderCalculator,
    ) {}

    public function getAssociationName(): string
    {
        return 'pickwareErpSingleItemOrder';
    }

    public function getVirtualFilterFieldNames(): array
    {
        return ['isOpenSingleItemOrder'];
    }

    /**
     * @param list<VirtualEntityAssociationFilterInjectionContext<static>> $contexts
     * @return list<string>
     */
    public function preProcessFilterInjections(array $contexts): mixed
    {
        foreach ($contexts as $context) {
            $filtersOnVirtualField = $context->getFiltersOnVirtualField('isOpenSingleItemOrder');
            foreach ($filtersOnVirtualField as $filter) {
                if (!($filter instanceof EqualsFilter && $filter->getValue() === true)) {
                    throw SingleItemOrderException::filteringForNonOpenSingleItemOrdersNotSupported();
                }
            }
        }

        return $this->singleItemOrderCalculator->getAllOpenSingleItemOrderIds();
    }

    /**
     * @param list<string> $preProcessResult
     */
    public function createRealFiltersForVirtualFilters(VirtualEntityAssociationFilterInjectionContext $context, mixed $preProcessResult): array
    {
        $openSingleItemOrderIds = $preProcessResult;
        $fieldPrefix = $context->getFieldPrefix();
        $fieldName = $fieldPrefix === '' ? 'id' : $fieldPrefix . '.id';

        // Future work could use others filters present on `order.id` to narrow down the calculation. Filtering on
        // `isSingleItemOrder` could also be allowed as long as such a filter is present.
        return [
            new EqualsAnyFilter($fieldName, $openSingleItemOrderIds),
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
        $singleItemOrderIds = $this->singleItemOrderCalculator->calculateSingleItemOrdersForOrderIds($orderIds);

        foreach ($parentEntities as $order) {
            $singleItemOrderEntity = new SingleItemOrderEntity();
            $singleItemOrderEntity->setId(Uuid::randomHex());
            $singleItemOrderEntity->setOrderId($order->getId());
            $singleItemOrderEntity->setOrderVersionId($order->getVersionId());
            $singleItemOrderEntity->setIsSingleItemOrder(in_array($order->getId(), $singleItemOrderIds, true));
            $order->addExtension($this->getAssociationName(), $singleItemOrderEntity);
        }
    }
}
