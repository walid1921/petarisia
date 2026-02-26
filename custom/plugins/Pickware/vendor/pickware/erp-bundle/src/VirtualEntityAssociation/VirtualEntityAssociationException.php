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

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class VirtualEntityAssociationException extends Exception implements JsonApiErrorSerializable
{
    private JsonApiError $jsonApiError;

    public function __construct(JsonApiError $jsonApiError)
    {
        $this->jsonApiError = $jsonApiError;
        parent::__construct($jsonApiError->getDetail());
    }

    public function serializeToJsonApiError(): JsonApiError
    {
        return $this->jsonApiError;
    }

    public static function filtersOnVirtualEntityAssociationNotSupported(string $associationName): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Filter auf virtuelle Entitätsassoziation werden nicht unterstützt',
                    'en' => 'Filters on virtual entity association are not supported',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Filter auf die virtuelle Entitätsassoziation "%s" werden nicht unterstützt.',
                        $associationName,
                    ),
                    'en' => sprintf(
                        'Filters on virtual entity association "%s" are not supported.',
                        $associationName,
                    ),
                ],
            ]),
        );
    }

    public static function nestedFiltersNotSupported(string $filterField): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Verschachtelte Filter auf virtuelle Entitätsassoziation werden nicht unterstützt',
                    'en' => 'Nested filters on virtual entity association are not supported',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Filter auf virtuelle Entitätsassoziationen dürfen keine verschachtelten Filter haben. Ungültiges Filterfeld: "%s".',
                        $filterField,
                    ),
                    'en' => sprintf(
                        'Filters on virtual entity association must not have nested filters. Invalid filter field: "%s".',
                        $filterField,
                    ),
                ],
            ]),
        );
    }

    public static function invalidFilterField(string $fieldName, string $associationName): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Ungültiges Filterfeld für eine virtuelle Entitätsassoziation',
                    'en' => 'Invalid filter field for a virtual entity association',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Das Feld "%s" ist kein gültiges Filterfeld für die virtuelle Entitätsassoziation "%s".',
                        $fieldName,
                        $associationName,
                    ),
                    'en' => sprintf(
                        'Field "%s" is not a valid filter field for the virtual entity association "%s".',
                        $fieldName,
                        $associationName,
                    ),
                ],
            ]),
        );
    }

    public static function unsupportedFilterType(string $filterClass): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Nicht unterstützter Filtertyp',
                    'en' => 'Unsupported filter type',
                ],
                'detail' => [
                    'de' => sprintf(
                        'Unerwarteter Filtertyp "%s". Nur EqualsFilter und EqualsAnyFilter werden unterstützt auf virtuellen Entitätsassoziationen.',
                        $filterClass,
                    ),
                    'en' => sprintf(
                        'Unexpected filter type "%s". Only EqualsFilter and EqualsAnyFilter are supported on virtual entity associations.',
                        $filterClass,
                    ),
                ],
            ]),
        );
    }
}
