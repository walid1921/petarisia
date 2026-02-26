<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\ShopwareExtensionsBundle\VersionCheck;

use Shopware\Core\Kernel;

function minimumShopwareVersion(string $shopwareVersion): bool
{
    return version_compare(Kernel::SHOPWARE_FALLBACK_VERSION, $shopwareVersion, '>=');
}

/**
 * @param array<string, string> $minimumVersionsPerMajor e.g. ['6.6' => '6.6.10.9', '6.7' => '6.7.9.3']
 */
function minimumShopwareVersionPerMajor(array $minimumVersionsPerMajor): bool
{
    $currentVersion = Kernel::SHOPWARE_FALLBACK_VERSION;

    foreach ($minimumVersionsPerMajor as $majorVersion => $minimumVersion) {
        if (str_starts_with($currentVersion, $majorVersion . '.')) {
            return version_compare($currentVersion, $minimumVersion, '>=');
        }
    }

    // If current version doesn't match any specified major version, check if it's newer than the
    // highest specified major. Returns true for 6.8 when 6.6/6.7 are specified, false for 6.5.
    $specifiedMajorVersions = array_keys($minimumVersionsPerMajor);

    // Guard against empty array
    if (empty($specifiedMajorVersions)) {
        return false;
    }

    // Use version_compare for correct semantic versioning
    $highestSpecifiedMajor = array_reduce(
        $specifiedMajorVersions,
        fn($carry, $version) => version_compare($version, $carry, '>') ? $version : $carry,
        $specifiedMajorVersions[0],
    );

    return version_compare($currentVersion, $highestSpecifiedMajor, '>');
}
