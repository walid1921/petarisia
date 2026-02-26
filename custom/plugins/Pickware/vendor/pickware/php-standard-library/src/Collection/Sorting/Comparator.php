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
 */
interface Comparator
{
    /**
     * @param Element $lhs
     * @param Element $rhs
     * @return int positive if `$lhs > $rhs`, zero if `$lhs == $rhs`, negative if `$lhs < $rhs`
     */
    public function compare(mixed $lhs, mixed $rhs): int;
}
