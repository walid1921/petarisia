<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DhlExpressBundle\Api\Services;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class DutyTaxesPaidServiceOption extends AbstractShipmentOption
{
    public function __construct() {}

    /**
     * @param array{
     *     accounts: non-empty-list<array{
     *          typeCode: string,
     *          number: string,
     *      }>,
     *     valueAddedServices?: list<array{
     *          serviceCode: string,
     *      }>
     * } $shipmentArray
     */
    public function applyToShipmentArray(array &$shipmentArray): void
    {
        $shipmentArray['accounts'][] = [
            'typeCode' => 'duties-taxes',
            'number' => $shipmentArray['accounts'][0]['number'],
        ];

        $shipmentArray['valueAddedServices'] ??= [];

        $shipmentArray['valueAddedServices'][] = [
            'serviceCode' => 'DD',
        ];
    }
}
