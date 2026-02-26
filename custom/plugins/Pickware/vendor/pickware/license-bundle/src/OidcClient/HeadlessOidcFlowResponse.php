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

use League\Uri\Http;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class HeadlessOidcFlowResponse
{
    private function __construct(
        private readonly ResponseInterface $response,
    ) {}

    public static function from(ResponseInterface $response): self
    {
        return new self($response);
    }

    public function throwIfNotExpectedStatusCode(int $expectedStatusCode, string $step): self
    {
        if ($this->response->getStatusCode() !== $expectedStatusCode) {
            throw BusinessPlatformHeadlessOidcFlowException::unexpectedStatusCode(
                step: $step,
                expected: $expectedStatusCode,
                actual: $this->response->getStatusCode(),
            );
        }

        return $this;
    }

    public function getLocationUri(): Http
    {
        return Http::new($this->response->getHeaderLine('Location'));
    }
}
