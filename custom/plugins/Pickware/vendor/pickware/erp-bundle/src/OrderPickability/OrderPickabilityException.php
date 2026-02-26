<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\OrderPickability;

use Exception;
use Pickware\ApiErrorHandlingBundle\JsonApiErrorTranslating\LocalizableJsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiError;
use Pickware\HttpUtils\JsonApi\JsonApiErrorSerializable;

class OrderPickabilityException extends Exception implements JsonApiErrorSerializable
{
    private const ERROR_CODE_NAMESPACE = 'PICKWARE_ERP__ORDER_PICKABILITY__';
    public const ERROR_CODE_MISSING_PICKABILITY_STATUS_FILTER = self::ERROR_CODE_NAMESPACE . 'MISSING_PICKABILITY_STATUS_FILTER';
    public const ERROR_CODE_MISSING_WAREHOUSE_FILTER = self::ERROR_CODE_NAMESPACE . 'MISSING_WAREHOUSE_FILTER';
    public const ERROR_CODE_UNSUPPORTED_ORDER_PICKABILITIES_FIELD_FILTER = self::ERROR_CODE_NAMESPACE . 'UNSUPPORTED_ORDER_PICKABILITIES_FIELD_FILTER';

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

    public static function missingPickabilityStatusFilter(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_MISSING_PICKABILITY_STATUS_FILTER,
            'title' => 'Missing pickability status filter',
            'detail' => (
                'The Pickware ERP order pickabilities filter is missing a filter condition for one or more order '
                . 'pickability statuses (i.e. `pickwareErpOrderPickabilities.orderPickabilityStatus`). Filtering for '
                . 'any pickability in a warehouse is currently not supported.'
            ),
        ]));
    }

    public static function missingWarehouseFilter(): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_MISSING_WAREHOUSE_FILTER,
            'title' => 'Missing warehouse filter',
            'detail' => (
                'The Pickware ERP order pickabilities filter is missing a filter condition for one or more warehouses '
                . '(i.e. `pickwareErpOrderPickabilities.warehouseId`). Filtering for a pickability in any warehouse is '
                . 'currently not supported.'
            ),
        ]));
    }

    public static function unsupportedOrderPickabilitiesFieldFilter(string $fieldName): self
    {
        return new self(new JsonApiError([
            'code' => self::ERROR_CODE_UNSUPPORTED_ORDER_PICKABILITIES_FIELD_FILTER,
            'title' => 'Unsupported order pickabilities field filter',
            'detail' => sprintf(
                'Filtering Pickware ERP order pickabilities by field "%1$s" is not supported.',
                $fieldName,
            ),
        ]));
    }

    public static function unexpectedFilterOnIdField(): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Unerwarteter Filter auf ID-Feld',
                    'en' => 'Unexpected filter on id field',
                ],
                'detail' => [
                    'de' => 'Unerwarteter Filter auf dem virtuellen Feld "id" mit einem Nicht-Null-Wert. Das Filtern nach bestimmten Bestellkommissionierbarkeits-IDs wird nicht unterstützt.',
                    'en' => 'Unexpected filter on virtual field "id" with a non-null value. Filtering by specific order pickability IDs is not supported.',
                ],
            ]),
        );
    }

    public static function incompatibleFilterCombination(): self
    {
        return new self(
            new LocalizableJsonApiError([
                'title' => [
                    'de' => 'Inkompatible Filterkombination',
                    'en' => 'Incompatible filter combination',
                ],
                'detail' => [
                    'de' => 'Unerwarteter Filter auf dem virtuellen Feld "warehouseId" oder "orderPickabilityStatus" in Kombination mit dem "id"-Filter. Beim Filtern nach Bestellungen ohne Kommissionierbarkeiten (mit "id"-Filter auf null) können keine anderen Filter kombiniert werden.',
                    'en' => 'Unexpected filter on virtual field "warehouseId" or "orderPickabilityStatus" in combination with "id" filter. When filtering for orders without pickabilities (using "id" filter with null), no other filters can be combined.',
                ],
            ]),
        );
    }
}
