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
use Pickware\PhpStandardLibrary\Json\Json;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client for authenticating with the Pickware Business Platform using email and password credentials.
 * This is used for internal testing purposes to programmatically authenticate without browser interaction.
 */
#[Exclude]
class BusinessPlatformAuthenticationClient
{
    public function __construct(
        private readonly Client $client,
    ) {}

    /**
     * Authenticates with the Business Platform using email and password.
     *
     * @throws BusinessPlatformAuthenticationException
     */
    public function login(string $email, string $password): string
    {
        $response = $this->client->post('/api/v4/auth/token', [
            RequestOptions::JSON => [
                'username' => $email,
                'password' => $password,
            ],
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw BusinessPlatformAuthenticationException::loginFailed(
                $response->getStatusCode(),
                (string) $response->getBody(),
            );
        }

        $responseData = Json::decodeToArray((string) $response->getBody());

        return $responseData['accessToken'];
    }
}
