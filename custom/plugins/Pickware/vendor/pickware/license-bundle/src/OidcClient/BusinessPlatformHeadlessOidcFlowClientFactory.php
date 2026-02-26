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
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Pickware\HttpUtils\Guzzle\GuzzleServiceUnavailableRetryMiddleware;
use Pickware\OidcClientBundle\Client\OpenIdConnectClientConfiguration;
use Pickware\OidcClientBundle\Provider\OpenIdConnectDiscoveryProvider;
use Pickware\OidcClientBundle\Provider\OpenIdConnectDiscoveryProviderConfigurationFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BusinessPlatformHeadlessOidcFlowClientFactory
{
    public function __construct(
        #[Autowire(service: 'pickware_license.oidc.business_platform_client_configuration')]
        private readonly OpenIdConnectClientConfiguration $oidcClientConfiguration,
        private readonly OpenIdConnectDiscoveryProviderConfigurationFactory $providerConfigurationFactory,
        #[Autowire(param: 'pickware_license.business_platform_base_url')]
        private readonly string $businessPlatformBaseUrl,
    ) {}

    public function __invoke(): BusinessPlatformHeadlessOidcFlowClient
    {
        $provider = new OpenIdConnectDiscoveryProvider(
            options: $this->providerConfigurationFactory->getDiscoveryProviderOptions($this->oidcClientConfiguration),
            collaborators: $this->providerConfigurationFactory->getCollaborators(),
        );

        $handlerStack = HandlerStack::create();
        $handlerStack->push(GuzzleServiceUnavailableRetryMiddleware::withExponentialBackoffDelay());

        $httpClient = new Client([
            'base_uri' => $this->businessPlatformBaseUrl,
            'cookies' => new CookieJar(),
            'handler' => $handlerStack,
            'timeout' => 5,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HTTP_ERRORS => false,
        ]);

        return new BusinessPlatformHeadlessOidcFlowClient(
            provider: $provider,
            client: $httpClient,
        );
    }
}
