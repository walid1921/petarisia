<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\PickingProcess;

use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\OrderShipping\StockShippedEvent;
use Pickware\PickwareErpStarter\Stock\Model\LocationTypeDefinition;
use Pickware\PickwareWms\Delivery\DeliveryService;
use Pickware\PickwareWms\Delivery\Model\DeliveryDefinition;
use Pickware\PickwareWms\Delivery\Model\DeliveryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShipReadyToShipDeliveriesOnOrderShipmentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly DeliveryService $deliveryService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            StockShippedEvent::class => 'shipReadyToShipDeliveries',
        ];
    }

    public function shipReadyToShipDeliveries(StockShippedEvent $event): void
    {
        $stockContainerIds = [];
        foreach ($event->getShippedStock() as $stock) {
            $stockLocation = $stock->getStockLocationReference();
            if ($stockLocation->getLocationTypeTechnicalName() !== LocationTypeDefinition::TECHNICAL_NAME_STOCK_CONTAINER) {
                continue;
            }

            $stockContainerIds[] = $stockLocation->getPrimaryKey();
        }

        $stockContainerIds = array_values(array_unique($stockContainerIds));
        if (count($stockContainerIds) === 0) {
            return;
        }

        /** @var EntityCollection<DeliveryEntity> $deliveries */
        $deliveries = $this->entityManager->findBy(
            DeliveryDefinition::class,
            [
                'stockContainerId' => $stockContainerIds,
                'orderId' => $event->getOrderId(),
                'state.technicalName' => DeliveryStateMachine::READY_TO_SHIP_STATES,
            ],
            $event->getContext(),
            [
                'stockContainer.stocks',
            ],
        );

        foreach ($deliveries as $delivery) {
            $stockContainer = $delivery->getStockContainer();
            if (!$stockContainer) {
                continue;
            }

            if ($stockContainer->getStocks()->count() > 0) {
                continue;
            }

            $this->deliveryService->ship($delivery->getId(), $event->getContext());
        }
    }
}
