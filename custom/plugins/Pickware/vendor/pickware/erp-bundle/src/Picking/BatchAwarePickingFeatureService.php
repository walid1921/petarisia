<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Picking;

use Pickware\FeatureFlagBundle\FeatureFlagService;

class BatchAwarePickingFeatureService
{
    public function __construct(
        private FeatureFlagService $featureFlagService,
    ) {}

    /**
     * This method exists to allow feature-detection in other plugins.
     */
    public function areBatchesSupportedDuringPicking(): bool
    {
        // When removing the feature flag, this method should be deprecated and always return true.
        return $this->featureFlagService->isActive(BatchAwarePickingDevFeatureFlag::NAME);
    }
}
