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
use GuzzleHttp\RequestOptions;
use League\Uri\Http;
use Pickware\OidcClientBundle\Provider\OpenIdConnectDiscoveryProvider;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Client for performing a headless (non-browser) OIDC flow with the Pickware Business Platform.
 */
#[Exclude]
class BusinessPlatformHeadlessOidcFlowClient
{
    public function __construct(
        private readonly OpenIdConnectDiscoveryProvider $provider,
        private readonly Client $client,
    ) {}

    /**
     * Performs the complete headless OIDC flow and returns the OIDC access token.
     *
     * @param string $businessPlatformAccessToken Access token obtained from BusinessPlatformAuthenticationClient
     * @param string $installationUuid The plugin installation UUID to include in the state
     *
     * @throws BusinessPlatformHeadlessOidcFlowException
     */
    public function obtainAccessToken(string $businessPlatformAccessToken, string $installationUuid): string
    {
        // Generate authorization URL with PKCE (this also generates the code verifier internally)
        $state = sprintf('%s-%s', $this->provider->getRandomState(), $installationUuid);
        $authorizationUrl = $this->provider->getAuthorizationUrl(['state' => $state]);
        $pkceCodeVerifier = $this->provider->getPkceCode();

        // Start the authorization flow
        $loginInteractionId = $this->startOidcAuthorization($authorizationUrl);

        // Confirm login interaction (Business Platform specific, mimics the SPA behavior)
        $scopeConfirmationInteractionId = $this->confirmLoginInteraction(
            $businessPlatformAccessToken,
            $loginInteractionId,
        );

        // Confirm scopes (Business Platform specific)
        $authorizationCode = $this->confirmScopes(
            $businessPlatformAccessToken,
            $scopeConfirmationInteractionId,
        );

        // Exchange authorization code for access token using the provider
        $accessToken = $this->provider->getAccessToken('authorization_code', [
            'code' => $authorizationCode,
            'code_verifier' => $pkceCodeVerifier,
        ]);

        return $accessToken->getToken();
    }

    /**
     * Starts the OIDC authorization flow by calling the authorization URL and extracting the interaction ID.
     *
     * @throws BusinessPlatformHeadlessOidcFlowException
     */
    private function startOidcAuthorization(string $authorizationUrl): string
    {
        // Extract path and query from authorization URL for the request
        $uri = Http::new($authorizationUrl);
        $query = $uri->getQuery();
        $requestPath = $query ? sprintf('%s?%s', $uri->getPath(), $query) : $uri->getPath();

        $response = HeadlessOidcFlowResponse::from($this->client->get($requestPath));
        $response->throwIfNotExpectedStatusCode(expectedStatusCode: 303, step: 'authorization start');

        $locationUri = $response->getLocationUri();
        $interactionId = basename($locationUri->getPath());

        // Follow to the interaction endpoint
        $interactionResponse = HeadlessOidcFlowResponse::from($this->client->get($locationUri->getPath()));
        $interactionResponse->throwIfNotExpectedStatusCode(expectedStatusCode: 302, step: 'interaction redirect');

        return $interactionId;
    }

    /**
     * Confirms the Business Platform specific login interaction (mimics the SPA behavior)
     *
     * @throws BusinessPlatformHeadlessOidcFlowException
     */
    private function confirmLoginInteraction(string $accessToken, string $loginInteractionId): string
    {
        // This is what the Pickware Account SPA does when the user logs in or is already logged in.
        $confirmResponse = HeadlessOidcFlowResponse::from($this->client->get(
            sprintf('/api/v4/oidc/interaction/%s/confirm', $loginInteractionId),
            $this->getAuthenticatedRequestOptions($accessToken),
        ));
        $confirmResponse->throwIfNotExpectedStatusCode(expectedStatusCode: 303, step: 'login confirmation');
        $authRedirectUri = $confirmResponse->getLocationUri();
        $authRedirectResponse = HeadlessOidcFlowResponse::from($this->client->get(
            $authRedirectUri->getPath(),
            $this->getAuthenticatedRequestOptions($accessToken),
        ));
        $authRedirectResponse->throwIfNotExpectedStatusCode(expectedStatusCode: 303, step: 'auth redirect');

        // Follow to the scope confirmation interaction
        $scopeInteractionUri = $authRedirectResponse->getLocationUri();
        $scopeInteractionResponse = HeadlessOidcFlowResponse::from($this->client->get(
            $scopeInteractionUri->getPath(),
            $this->getAuthenticatedRequestOptions($accessToken),
        ));
        $scopeInteractionResponse->throwIfNotExpectedStatusCode(expectedStatusCode: 302, step: 'scope interaction');
        $scopeConfirmationInteractionId = basename($scopeInteractionUri->getPath());

        return $scopeConfirmationInteractionId;
    }

    /**
     * Confirms the scopes and extracts the authorization code.
     *
     * @throws BusinessPlatformHeadlessOidcFlowException
     */
    private function confirmScopes(string $accessToken, string $scopeConfirmationInteractionId): string
    {
        // Skip ahead of any consent collection step and directly confirm the scopes.
        $confirmScopesResponse = HeadlessOidcFlowResponse::from($this->client->get(
            sprintf('/api/v4/oidc/interaction/%s/confirm-scopes', $scopeConfirmationInteractionId),
            $this->getAuthenticatedRequestOptions($accessToken),
        ));
        $confirmScopesResponse->throwIfNotExpectedStatusCode(expectedStatusCode: 303, step: 'scope confirmation');

        // Follow to get the authorization code
        $codeRedirectUri = $confirmScopesResponse->getLocationUri();
        $codeRedirectResponse = HeadlessOidcFlowResponse::from($this->client->get($codeRedirectUri->getPath()));
        $codeRedirectResponse->throwIfNotExpectedStatusCode(expectedStatusCode: 303, step: 'code redirect');

        $finalUri = $codeRedirectResponse->getLocationUri();
        $queryParams = [];
        parse_str($finalUri->getQuery(), $queryParams);

        if (!isset($queryParams['code'])) {
            throw BusinessPlatformHeadlessOidcFlowException::missingAuthorizationCode(redirectUrl: $finalUri->jsonSerialize());
        }

        return $queryParams['code'];
    }

    /**
     * @return array<string, mixed>
     */
    private function getAuthenticatedRequestOptions(string $accessToken): array
    {
        return [
            RequestOptions::HEADERS => [
                'Authorization' => sprintf('Bearer %s', $accessToken),
            ],
        ];
    }
}
