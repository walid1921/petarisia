<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\LicenseBundle\OidcClient;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Pickware\HttpUtils\Guzzle\GuzzleServiceUnavailableRetryMiddleware;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BusinessPlatformAuthenticationClientFactory
{
    public function __construct(
        #[Autowire(param: 'pickware_license.business_platform_base_url')]
        private readonly string $businessPlatformBaseUrl,
    ) {}

    public function __invoke(): BusinessPlatformAuthenticationClient
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(GuzzleServiceUnavailableRetryMiddleware::withExponentialBackoffDelay());

        return new BusinessPlatformAuthenticationClient(
            client: new Client([
                'base_uri' => $this->businessPlatformBaseUrl,
                'handler' => $handlerStack,
                'timeout' => 5,
                RequestOptions::HTTP_ERRORS => false,
            ]),
        );
    }
}
