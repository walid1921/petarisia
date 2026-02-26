<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;

class MinimumShelfLifeService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * Finds the minimum shelf lives for products in the context of one or more orders. Customer-specific shelf lives
     * take precedence over product-specific shelf lives. When the same product is ordered by multiple customers, the
     * maximum of the shelf lives is used. When no customer-specific shelf life is set, the product-specific shelf lives
     * are used, if available.
     *
     * @param array<string, string[]> $productIdsByOrderId
     * @return array<string, int> may not include all product IDs
     */
    public function getMinimumRemainingShelfLivesForProductsInOrders(array $productIdsByOrderId, Context $context): array
    {
        $minimumShelfLifeByProductId = [];
        $productIdsWithOrderWithoutCustomerTolerance = [];

        /** @var OrderCustomerCollection $orderCustomers */
        $orderCustomers = $this->entityManager->findBy(
            OrderCustomerDefinition::class,
            ['orderId' => array_keys($productIdsByOrderId)],
            $context,
            ['customer'],
        );
        foreach ($orderCustomers as $orderCustomer) {
            $orderId = $orderCustomer->getOrderId();
            $customerTolerance = $this->getMinimumShelfLifeFromCustomFields($orderCustomer->getCustomer()?->getCustomFields());
            if ($customerTolerance === null) {
                foreach ($productIdsByOrderId[$orderId] as $productId) {
                    $productIdsWithOrderWithoutCustomerTolerance[$productId] = true;
                }
            } else {
                foreach ($productIdsByOrderId[$orderId] as $productId) {
                    $minimumShelfLifeByProductId[$productId] = max(
                        $minimumShelfLifeByProductId[$productId] ?? $customerTolerance,
                        $customerTolerance,
                    );
                }
            }
        }

        $productIdsThatNeedProductTolerance = ImmutableCollection::create($productIdsByOrderId)
            ->flatMap(fn(array $productIds) => $productIds)
            ->filter(
                fn(string $productId) => !array_key_exists($productId, $minimumShelfLifeByProductId)
                    || array_key_exists($productId, $productIdsWithOrderWithoutCustomerTolerance),
            );
        if ($productIdsThatNeedProductTolerance->isEmpty()) {
            return $minimumShelfLifeByProductId;
        }

        /** @var ProductCollection $products */
        $products = $this->entityManager->findBy(
            ProductDefinition::class,
            ['id' => $productIdsThatNeedProductTolerance->asArray()],
            $context,
        );
        foreach ($products as $productId => $product) {
            $productTolerance = $this->getMinimumShelfLifeFromCustomFields($product->getCustomFields());
            if ($productTolerance === null) {
                continue;
            }

            $minimumShelfLifeByProductId[$productId] = max(
                $minimumShelfLifeByProductId[$productId] ?? $productTolerance,
                $productTolerance,
            );
        }

        return $minimumShelfLifeByProductId;
    }

    /**
     * @param array<string, mixed>|null $customFields
     */
    private function getMinimumShelfLifeFromCustomFields(?array $customFields): ?int
    {
        if ($customFields === null) {
            return null;
        }

        $rawTolerance = $customFields[BatchCustomFieldSet::CUSTOM_FIELD_MINIMUM_REMAINING_SHELF_LIFE_IN_DAYS] ?? null;
        if (!is_numeric($rawTolerance)) {
            return null;
        }

        return (int) $rawTolerance;
    }
}
