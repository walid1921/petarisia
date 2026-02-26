<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\DeliveryNote;

use Pickware\DalBundle\EntityManager;
use Pickware\FeatureFlagBundle\FeatureFlagService;
use Pickware\PhpStandardLibrary\Collection\CountingMap;
use Pickware\PickwareErpStarter\Picking\OrderQuantitiesToShipCalculator;
use Pickware\PickwareWms\Config\FeatureFlags\OutstandingItemsOnPartialDeliveryNotesDevelopmentFeatureFlag;
use Pickware\ProductSetBundle\Order\OrderUpdater;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddRemainingQuantitiesForPartialDeliveriesSubscriber implements EventSubscriberInterface
{
    public const DOCUMENT_CONFIG_DISPLAY_OUTSTANDING_ITEMS_KEY = 'pickwareWmsDisplayOutstandingItemsOnPartialDeliveryNote';
    public const ORDER_CUSTOM_FIELD_REMAINING_LINE_ITEMS_KEY = 'pickwareWmsRemainingLineItems';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly FeatureFlagService $featureFlagService,
        private readonly ?OrderQuantitiesToShipCalculator $orderQuantitiesToShipCalculator = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            DeliveryNoteLineItemFilterEvent::class => 'addRemainingQuantities',
        ];
    }

    public function addRemainingQuantities(DeliveryNoteLineItemFilterEvent $event): void
    {
        if (!$this->featureFlagService->isActive(OutstandingItemsOnPartialDeliveryNotesDevelopmentFeatureFlag::NAME)) {
            return;
        }

        if (!$this->isOutstandingItemsDisplayEnabled($event->getContext())) {
            return;
        }

        /** @var OrderEntity $order */
        $order = $this->entityManager->getByPrimaryKey(
            OrderDefinition::class,
            $event->getOrderId(),
            $event->getContext(),
            ['lineItems'],
        );
        $orderLineItems = $order->getLineItems();
        $remainingLineItemQuantities = $this->calculateRemainingLineItemQuantities(
            $orderLineItems,
            $this->getShippableOrderLineItemQuantities($orderLineItems, $event->getOrderId(), $event->getContext()),
            $event->getLineItemQuantities(),
        );

        $event->updateCustomFields([
            self::ORDER_CUSTOM_FIELD_REMAINING_LINE_ITEMS_KEY => $this->calculateRemainingLineItems(
                $orderLineItems,
                $remainingLineItemQuantities,
            ),
        ]);
    }

    private function isOutstandingItemsDisplayEnabled(Context $context): bool
    {
        /** @var DocumentBaseConfigEntity|null $deliveryNoteDocumentConfig */
        $deliveryNoteDocumentConfig = $this->entityManager->findOneBy(
            DocumentBaseConfigDefinition::class,
            [
                'documentType.technicalName' => 'delivery_note',
                'global' => true,
            ],
            $context,
        );

        if (!$deliveryNoteDocumentConfig) {
            return true;
        }

        return (bool) ($deliveryNoteDocumentConfig->getConfig()[self::DOCUMENT_CONFIG_DISPLAY_OUTSTANDING_ITEMS_KEY] ?? true);
    }

    /**
     * @return CountingMap<string>
     */
    private function getShippableOrderLineItemQuantities(
        OrderLineItemCollection $orderLineItems,
        string $orderId,
        Context $context,
    ): CountingMap {
        if ($this->orderQuantitiesToShipCalculator) {
            // @phpstan-ignore function.alreadyNarrowedType (Method does not exist in older class versions)
            if (method_exists($this->orderQuantitiesToShipCalculator, 'calculateLineItemQuantitiesToShipForOrder')) {
                return $this->orderQuantitiesToShipCalculator->calculateLineItemQuantitiesToShipForOrder(
                    $orderId,
                    $context,
                );
            }

            $lineItemsToShip = $this->orderQuantitiesToShipCalculator->calculateLineItemsToShipForOrder(
                $orderId,
                $context,
            );
            $countingMapData = [];
            foreach ($lineItemsToShip as $orderLineItemQuantity) {
                $countingMapData[$orderLineItemQuantity->getOrderLineItemId()] = $orderLineItemQuantity->getQuantity();
            }

            return new CountingMap($countingMapData);
        }

        $countingMapData = [];
        foreach ($orderLineItems as $lineItem) {
            if ($lineItem->getProductId() === null) {
                continue;
            }
            $countingMapData[$lineItem->getId()] = $lineItem->getQuantity();
        }

        return new CountingMap($countingMapData);
    }

    /**
     * @param CountingMap<string> $shippableOrderLineItemQuantities
     * @param CountingMap<string> $lineItemQuantitiesToBeShipped
     * @return CountingMap<string>
     */
    private function calculateRemainingLineItemQuantities(
        OrderLineItemCollection $lineItems,
        CountingMap $shippableOrderLineItemQuantities,
        CountingMap $lineItemQuantitiesToBeShipped,
    ): CountingMap {
        $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId = $this->getProductSetChildLineItemQuantitiesPerProductSetByParentLineItemId(
            $lineItems,
        );
        $shippableOrderLineItemQuantities = $this->expandProductSetParentLineItemsToChildLineItems(
            $shippableOrderLineItemQuantities,
            $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId,
        );
        $lineItemQuantitiesToBeShipped = $this->expandProductSetParentLineItemsToChildLineItems(
            $lineItemQuantitiesToBeShipped,
            $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId,
        );

        $remainingLineItemQuantities = new CountingMap($shippableOrderLineItemQuantities->asArray());
        $remainingLineItemQuantities->subtractMap($lineItemQuantitiesToBeShipped);

        return $this->replaceProductSetChildLineItemsWithParentLineItems(
            $remainingLineItemQuantities,
            $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId,
        );
    }

    /**
     * @param CountingMap<string> $lineItemQuantitiesByLineItemId
     * @param array<string, CountingMap<string>> $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId
     * @return CountingMap<string>
     */
    private function expandProductSetParentLineItemsToChildLineItems(
        CountingMap $lineItemQuantitiesByLineItemId,
        array $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId,
    ): CountingMap {
        $lineItemQuantitiesByLineItemId = new CountingMap($lineItemQuantitiesByLineItemId->asArray());
        foreach (
            $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId as $productSetParentLineItemId => $productSetChildLineItemQuantitiesPerProductSet
        ) {
            $parentLineItemQuantity = $lineItemQuantitiesByLineItemId->get($productSetParentLineItemId);
            if ($parentLineItemQuantity <= 0) {
                continue;
            }

            $lineItemQuantitiesByLineItemId->set($productSetParentLineItemId, 0);

            foreach ($productSetChildLineItemQuantitiesPerProductSet as $productSetChildLineItemId => $productSetChildLineItemQuantity) {
                $lineItemQuantitiesByLineItemId->add(
                    $productSetChildLineItemId,
                    $productSetChildLineItemQuantity * $parentLineItemQuantity,
                );
            }
        }

        return $lineItemQuantitiesByLineItemId;
    }

    /**
     * @param CountingMap<string> $lineItemQuantitiesByLineItemId
     * @param array<string, CountingMap<string>> $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId
     * @return CountingMap<string>
     */
    private function replaceProductSetChildLineItemsWithParentLineItems(
        CountingMap $lineItemQuantitiesByLineItemId,
        array $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId,
    ): CountingMap {
        $lineItemQuantitiesByLineItemId = new CountingMap($lineItemQuantitiesByLineItemId->asArray());
        foreach (
            $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId as $productSetParentLineItemId => $productSetChildLineItemQuantitiesPerProductSet
        ) {
            while ($productSetChildLineItemQuantitiesPerProductSet->isSubsetOf($lineItemQuantitiesByLineItemId)) {
                $lineItemQuantitiesByLineItemId->subtractMap($productSetChildLineItemQuantitiesPerProductSet);
                $lineItemQuantitiesByLineItemId->add($productSetParentLineItemId, 1);
            }
        }

        return $lineItemQuantitiesByLineItemId;
    }

    /**
     * @param CountingMap<string> $remainingLineItemQuantities
     * @return list<array{productNumber: string, label: string, options: list<array{group: string, option: string}>, quantity: int, totalQuantity: int, taxRates: list<float>, unitPrice: float, totalPrice: float}>
     */
    private function calculateRemainingLineItems(
        OrderLineItemCollection $lineItems,
        CountingMap $remainingLineItemQuantities,
    ): array {
        $remainingLineItems = [];

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getProductId() === null) {
                continue;
            }

            $remainingQuantity = $remainingLineItemQuantities->get($lineItem->getId());
            if ($remainingQuantity <= 0) {
                continue;
            }

            $productNumber = '';
            $options = [];
            $payload = $lineItem->getPayload();
            if (is_array($payload) && is_string($payload['productNumber'] ?? null)) {
                $productNumber = $payload['productNumber'];
            }
            if (is_array($payload) && is_array($payload['options'] ?? null)) {
                foreach ($payload['options'] as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    if (!is_string($option['group'] ?? null) || !is_string($option['option'] ?? null)) {
                        continue;
                    }
                    $options[] = [
                        'group' => $option['group'],
                        'option' => $option['option'],
                    ];
                }
            }

            $taxRates = [];
            if ($lineItem->getPrice()) {
                $taxRates = $lineItem
                    ->getPrice()
                    ->getTaxRules()
                    ->map(fn(TaxRule $taxRule) => $taxRule->getTaxRate());
            }

            $remainingLineItems[] = [
                'productNumber' => $productNumber,
                'label' => $lineItem->getLabel(),
                'options' => $options,
                'quantity' => $remainingQuantity,
                'totalQuantity' => $lineItem->getQuantity(),
                'taxRates' => $taxRates,
                'unitPrice' => $lineItem->getUnitPrice(),
                'totalPrice' => $lineItem->getUnitPrice() * $remainingQuantity,
            ];
        }

        return $remainingLineItems;
    }

    /**
     * @return array<string, CountingMap<string>>
     */
    private function getProductSetChildLineItemQuantitiesPerProductSetByParentLineItemId(
        OrderLineItemCollection $lineItems,
    ): array {
        $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId = [];
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getParentId() === null) {
                continue;
            }

            $payload = $lineItem->getPayload();
            if (!is_array($payload)) {
                continue;
            }

            $productSetConfigurationSnapshot = $payload[OrderUpdater::PICKWARE_PRODUCT_SET_CHILD_LINE_PAYLOAD_FIELD_KEY] ?? null;
            if (!is_array($productSetConfigurationSnapshot)) {
                continue;
            }

            $productSetChildLineItemQuantity = $productSetConfigurationSnapshot['quantity'] ?? null;
            if (!is_int($productSetChildLineItemQuantity) || $productSetChildLineItemQuantity <= 0) {
                continue;
            }

            $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId[$lineItem->getParentId()] ??= [];
            $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId[$lineItem->getParentId()][$lineItem->getId()] = $productSetChildLineItemQuantity;
        }

        return array_map(
            fn(array $productSetChildLineItemQuantities) => new CountingMap($productSetChildLineItemQuantities),
            $productSetChildLineItemQuantitiesPerProductSetByParentLineItemId,
        );
    }
}
