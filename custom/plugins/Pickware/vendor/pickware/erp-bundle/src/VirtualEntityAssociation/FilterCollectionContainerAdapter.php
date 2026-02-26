<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\VirtualEntityAssociation;

use LogicException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Adapter for a {@link Criteria} or {@link MultiFilter} that allows adding filters and resetting them regardless of
 * the underlying type.
 */
#[Exclude]
class FilterCollectionContainerAdapter
{
    private bool $converted = false;

    public function __construct(
        private Criteria|MultiFilter $criteriaOrMultiFilter,
    ) {}

    public function addFilter(Filter $filter): void
    {
        if ($this->criteriaOrMultiFilter instanceof Criteria) {
            $this->criteriaOrMultiFilter->addFilter($filter);

            return;
        }

        $this->criteriaOrMultiFilter->addQuery($filter);
    }

    /**
     * Removes all filters from the underlying container. If the underlying container is a {@link MultiFilter}, a copy
     * is created.
     */
    public function resetFilters(): void
    {
        if ($this->criteriaOrMultiFilter instanceof Criteria) {
            $this->criteriaOrMultiFilter->resetFilters();

            return;
        }

        // If someone has taken a reference to our underlying MultiFilter, we cannot replace it anymore. Otherwise, the
        // reference would become invalid.
        if ($this->converted) {
            throw new LogicException('This instance was already converted to the underlying container.');
        }

        $this->criteriaOrMultiFilter = $this->makeNewEmptyMultiFilter($this->criteriaOrMultiFilter);
    }

    /**
     * Returns a reference to the underlying container. After this method is called, {@link resetFilters} will throw an
     * exception if the underlying container is a {@link MultiFilter}.
     */
    public function toUnderlyingContainer(): MultiFilter|Criteria
    {
        $this->converted = true;

        return $this->criteriaOrMultiFilter;
    }

    /**
     * @return Filter[]
     */
    public function getFilters(): array
    {
        if ($this->criteriaOrMultiFilter instanceof Criteria) {
            return $this->criteriaOrMultiFilter->getFilters();
        }

        return $this->criteriaOrMultiFilter->getQueries();
    }

    private function makeNewEmptyMultiFilter(MultiFilter $filter): MultiFilter
    {
        if ($filter instanceof NotFilter) {
            return new NotFilter($filter->getOperator(), []);
        }

        return new MultiFilter($filter->getOperator(), []);
    }
}
