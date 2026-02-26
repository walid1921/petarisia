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
use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;

final class Compare
{
    /**
     * Creates a derived comparator that compares elements by a key computed from the element. Be aware that the key
     * selector is applied to each element before each comparison; thus, it should be simple and without side effects.
     * If you have an expensive key selector, consider using {@link ImmutableCollection::sortedBy()} instead, as this
     * function computes the keys only once for each element.
     *
     * @template Element
     * @template Key
     * @param Closure(Element): Key $keySelector
     * @param Comparator<Key>|null $comparator a comparator for the key type, defaults to {@see NaturalComparator}
     * @return Comparator<Element>
     */
    public static function byKey(Closure $keySelector, ?Comparator $comparator = null): Comparator
    {
        return new KeySelectingComparator($keySelector, $comparator ?? new NaturalComparator());
    }

    /**
     * Reverses the given comparator.
     *
     * @template Element
     * @param Comparator<Element> $comparator
     * @return Comparator<Element>
     */
    public static function reversed(Comparator $comparator): Comparator
    {
        return new ReversedComparator($comparator);
    }

    /**
     * Creates a comparator that applies the given comparators in order. The first comparator that returns a non-zero
     * result determines the order of the elements.
     *
     * @template Element
     * @param Comparator<Element> ...$comparators
     * @return Comparator<Element>
     */
    public static function chain(Comparator ...$comparators): Comparator
    {
        return new ChainedComparator($comparators);
    }
}
