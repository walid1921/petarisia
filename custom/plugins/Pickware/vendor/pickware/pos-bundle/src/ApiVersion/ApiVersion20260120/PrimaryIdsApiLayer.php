<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwarePos\ApiVersion\ApiVersion20260120;

use Pickware\ApiVersioningBundle\ApiLayer;
use Pickware\ApiVersioningBundle\ApiVersion;
use Pickware\ApiVersioningBundle\JsonRequestModifier;
use Pickware\PickwarePos\ApiVersion\ApiVersion20260120;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PrimaryIdsApiLayer implements ApiLayer
{
    public function getVersion(): ApiVersion
    {
        return new ApiVersion20260120();
    }

    public function transformRequest(Request $request, Context $context): void
    {
        JsonRequestModifier::modifyJsonContent(
            $request,
            function(int|float|bool|array|stdClass &$jsonContent): void {
                if (!property_exists($jsonContent, 'order')) {
                    return;
                }
                if (property_exists($jsonContent->order, 'transactions') && count($jsonContent->order->transactions) > 0) {
                    $primaryOrderTransaction = $jsonContent->order->transactions[0];
                    $primaryOrderTransactionId = $primaryOrderTransaction->id ?? Uuid::randomHex();
                    $primaryOrderTransaction->id = $primaryOrderTransactionId;
                    $jsonContent->order->primaryOrderTransactionId = $primaryOrderTransactionId;
                }

                if (property_exists($jsonContent->order, 'deliveries') && count($jsonContent->order->deliveries) > 0) {
                    $primaryOrderDelivery = $jsonContent->order->deliveries[0];
                    $primaryOrderDeliveryId = $primaryOrderDelivery->id ?? Uuid::randomHex();
                    $primaryOrderDelivery->id = $primaryOrderDeliveryId;
                    $jsonContent->order->primaryOrderDeliveryId = $primaryOrderDeliveryId;
                }
            },
            asObject: true,
        );
    }

    public function transformResponse(Request $request, Response $response, Context $context): void {}
}
