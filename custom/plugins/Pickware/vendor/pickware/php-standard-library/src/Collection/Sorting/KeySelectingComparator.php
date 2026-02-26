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

use Closure;

/**
 * @template Element
 * @template Key
 * @implements Comparator<Element>
 */
final readonly class KeySelectingComparator implements Comparator
{
    /**
     * @param Closure(Element):Key $keySelector
     * @param Comparator<Key> $comparator
     */
    public function __construct(
        private Closure $keySelector,
        private Comparator $comparator,
    ) {}

    public function compare(mixed $lhs, mixed $rhs): int
    {
        $lhsKey = ($this->keySelector)($lhs);
        $rhsKey = ($this->keySelector)($rhs);

        return $this->comparator->compare($lhsKey, $rhsKey);
    }
}
