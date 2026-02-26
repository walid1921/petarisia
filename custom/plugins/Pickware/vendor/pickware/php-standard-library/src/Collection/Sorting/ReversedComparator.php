<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PhpStandardLibrary\Collection\Sorting;

/**
 * @template Element
 * @implements Comparator<Element>
 */
final readonly class ReversedComparator implements Comparator
{
    /**
     * @param Comparator<Element> $comparator
     */
    public function __construct(private Comparator $comparator) {}

    public function compare(mixed $lhs, mixed $rhs): int
    {
        return $this->comparator->compare($rhs, $lhs);
    }
}
