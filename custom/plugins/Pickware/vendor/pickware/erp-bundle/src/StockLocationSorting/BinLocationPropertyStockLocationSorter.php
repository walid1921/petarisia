<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\StockLocationSorting;

use Pickware\PhpStandardLibrary\Collection\ImmutableCollection;
use Pickware\PhpStandardLibrary\Collection\Sorting\Comparator;
use Pickware\PickwareErpStarter\StockApi\StockLocationConfigurationService;
use Pickware\PickwareErpStarter\StockApi\StockLocationReference;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Sorts stock locations by their warehouse and bin location properties.
 * @see {@link BinLocationComparator} for sorting rules.
 */
#[Exclude]
class BinLocationPropertyStockLocationSorter
{
    /**
     * @param BinLocationProperty[] $sortByProperties
     */
    public function __construct(
        private readonly StockLocationConfigurationService $stockLocationConfigurationService,
        private readonly array $sortByProperties,
    ) {}

    public static function createBinLocationCodeStockLocationSorter(
        StockLocationConfigurationService $stockLocationConfigurationService,
    ): self {
        return new self(
            $stockLocationConfigurationService,
            [BinLocationProperty::Code],
        );
    }

    public static function createBinLocationPositionStockLocationSorter(
        StockLocationConfigurationService $stockLocationConfigurationService,
    ): self {
        return new self(
            $stockLocationConfigurationService,
            [
                BinLocationProperty::Position,
                BinLocationProperty::Code,
            ],
        );
    }

    /**
     * @param ImmutableCollection<StockLocationReference> $stockLocationReferences
     * @return ImmutableCollection<StockLocationReference>
     * @deprecated use {@link self::sortCollectionBy} or {@link self::createComparator} instead.
     */
    public function sort(ImmutableCollection $stockLocationReferences, Context $context): ImmutableCollection
    {
        return $stockLocationReferences->sorted($this->createComparator($stockLocationReferences, $context));
    }

    /**
     * @template Element
     * @template CollectionType of ImmutableCollection<Element>
     * @param CollectionType $collection
     * @param callable(Element):StockLocationReference $stockLocationReferenceProvider
     * @return CollectionType
     */
    public function sortCollectionBy(
        ImmutableCollection $collection,
        callable $stockLocationReferenceProvider,
        Context $context,
    ): ImmutableCollection {
        $comparator = $this->createComparator($collection->map($stockLocationReferenceProvider), $context);

        return $collection->sortedBy($stockLocationReferenceProvider, $comparator);
    }

    /**
     * @param ImmutableCollection<StockLocationReference> $stockLocationReferences
     * @return Comparator<StockLocationReference>
     */
    public function createComparator(ImmutableCollection $stockLocationReferences, Context $context): Comparator
    {
        $stockLocationConfigurations = $this->stockLocationConfigurationService->getStockLocationConfigurations(
            $stockLocationReferences,
            $context,
        );

        return new BinLocationComparator($stockLocationConfigurations, $this->sortByProperties);
    }
}
