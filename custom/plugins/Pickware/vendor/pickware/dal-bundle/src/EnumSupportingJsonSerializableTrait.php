<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\DalBundle;

use BackedEnum;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use UnitEnum;

trait EnumSupportingJsonSerializableTrait
{
    use JsonSerializableTrait {
        JsonSerializableTrait::jsonSerialize as private parentJsonSerialize;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        $vars = $this->parentJsonSerialize();
        $this->convertEnumPropertiesToStringRepresentation($vars);

        return $vars;
    }

    /**
     * @param array<array-key, mixed> $array
     */
    private function convertEnumPropertiesToStringRepresentation(array &$array): void
    {
        foreach ($array as &$value) {
            if ($value instanceof BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof UnitEnum) {
                $value = $value->name;
            }
        }
    }
}
