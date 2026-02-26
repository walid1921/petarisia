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

use DateTimeZone;
use Pickware\DalBundle\EntityManager;
use Pickware\PhpStandardLibrary\Collection\Sorting\Comparator;
use Pickware\PhpStandardLibrary\DateTime\CalendarDate;
use Pickware\PickwareErpStarter\Batch\Model\BatchCollection;
use Pickware\PickwareErpStarter\Batch\Model\BatchDefinition;
use Pickware\PickwareErpStarter\Batch\Model\BatchEntity;
use Pickware\PickwareErpStarter\Config\GlobalPluginConfig;
use Pickware\PickwareErpStarter\Picking\PickingRequest;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class BatchExpirationService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly GlobalPluginConfig $pluginConfig,
        private readonly ClockInterface $clock,
    ) {}

    public function filterStockByBatchExpiration(
        BatchQuantityLocationImmutableCollection $stock,
        PickingRequest $pickingRequest,
        Context $context,
    ): BatchQuantityLocationImmutableCollection {
        /** @var BatchCollection $batches */
        $batches = $this->entityManager->findBy(
            BatchDefinition::class,
            [
                'id' => $stock->getBatchIds(),
                'product.pickwareErpPickwareProduct.isBatchManaged' => true,
            ],
            $context,
        );
        $today = CalendarDate::fromDateTimeInTimezone($this->clock->now(), new DateTimeZone('UTC'));

        return $stock->filter(function(BatchQuantityLocation $element) use ($batches, $pickingRequest, $today) {
            if ($element->getBatchId() === null) {
                return true;
            }
            $batch = $batches->get($element->getBatchId());
            if ($batch === null || $batch->getBestBeforeDate() === null) {
                return true;
            }

            $minimumShelfLife = $pickingRequest->getMinimumShelfLifeByProductId()[$element->getProductId()] ?? $this->pluginConfig->getBatchMinimumRemainingShelfLifeInDays();

            return $today->getDaysUntil($batch->getBestBeforeDate()) >= $minimumShelfLife;
        });
    }

    /**
     * @param string[] $batchIds
     * @return Comparator<string>
     */
    public function createBatchExpirationComparator(
        array $batchIds,
        Context $context,
    ): Comparator {
        /** @var BatchCollection $batches */
        $batches = $this->entityManager->findBy(
            BatchDefinition::class,
            (new Criteria())
                ->addFilter(new EqualsAnyFilter('id', $batchIds))
                ->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('bestBeforeDate', null)])),
            $context,
        );

        return new BatchExpirationComparator($batches->map(fn(BatchEntity $batch) => $batch->getBestBeforeDate()));
    }
}
