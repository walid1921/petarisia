<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\OrderDocument;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PhpStandardLibrary\Json\Json;
use Pickware\PickwareErpStarter\Product\Model\ProductTrackingProfile;

/**
 * @extends ImmutableCollection<OrderDocumentBatchInfo>
 */
class OrderDocumentBatchInfoCollection extends ImmutableCollection
{
    /**
     * Consolidates entries that would have identical presentation based on their tracking profile.
     *
     * For example, if tracking profile is "BestBeforeDate", entries with different batch numbers
     * but the same BBD (or both null) will be merged with their quantities summed.
     */
    public function consolidateByPresentation(): self
    {
        $grouped = $this->groupBy(
            fn(OrderDocumentBatchInfo $info) => $this->getPresentationKey($info),
            fn(ImmutableCollection $group) => new OrderDocumentBatchInfo(
                $group->first()->batchNumber,
                $group->first()->bestBeforeDate,
                $group->map(fn(OrderDocumentBatchInfo $info) => $info->quantity)->sum(),
                $group->first()->trackingProfile,
            ),
        );

        return self::create(array_values($grouped));
    }

    private function getPresentationKey(OrderDocumentBatchInfo $info): string
    {
        return match ($info->trackingProfile) {
            ProductTrackingProfile::Number => Json::stringify([$info->batchNumber]),
            ProductTrackingProfile::BestBeforeDate => Json::stringify([$info->bestBeforeDate]),
            ProductTrackingProfile::BestBeforeDateAndNumber => Json::stringify([$info->batchNumber, $info->bestBeforeDate]),
        };
    }

    public function getTotalQuantity(): int
    {
        return $this->map(fn(OrderDocumentBatchInfo $info) => $info->quantity)->sum();
    }

    /**
     * @return list<array{batchNumber: ?string, bestBeforeDate: ?string, quantity: int, trackingProfile: string}>
     */
    public function toPayload(): array
    {
        return $this->map(fn(OrderDocumentBatchInfo $info) => [
            'batchNumber' => $info->batchNumber,
            'bestBeforeDate' => $info->bestBeforeDate,
            'quantity' => $info->quantity,
            'trackingProfile' => $info->trackingProfile->value,
        ])->asArray();
    }
}
