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

use Pickware\PhpStandardLibrary\Collection\Sorting\Comparator;
use Pickware\PhpStandardLibrary\DateTime\CalendarDate;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @implements Comparator<string>
 */
#[Exclude]
class BatchExpirationComparator implements Comparator
{
    /**
     * @param array<string, CalendarDate> $batchBestBeforeDates
     */
    public function __construct(
        private readonly array $batchBestBeforeDates,
    ) {}

    public function compare(mixed $lhs, mixed $rhs): int
    {
        $lhsBestBeforeDate = $this->batchBestBeforeDates[$lhs] ?? null;
        $rhsBestBeforeDate = $this->batchBestBeforeDates[$rhs] ?? null;
        if ($lhsBestBeforeDate === null && $rhsBestBeforeDate === null) {
            return 0;
        }
        if ($lhsBestBeforeDate === null) {
            return 1;
        }
        if ($rhsBestBeforeDate === null) {
            return -1;
        }

        return $lhsBestBeforeDate->toIsoString() <=> $rhsBestBeforeDate->toIsoString();
    }
}
