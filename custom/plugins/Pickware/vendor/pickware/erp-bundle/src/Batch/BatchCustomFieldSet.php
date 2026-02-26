<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch;

use Pickware\InstallationLibrary\CustomFieldSet\CustomField;
use Pickware\InstallationLibrary\CustomFieldSet\CustomFieldSet;
use Pickware\PickwareErpStarter\Picking\BatchAwarePickingDevFeatureFlag;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class BatchCustomFieldSet extends CustomFieldSet
{
    public const TECHNICAL_NAME = 'pickware_erp_batches_and_bbds';
    public const CUSTOM_FIELD_MINIMUM_REMAINING_SHELF_LIFE_IN_DAYS = 'pickware_erp_minimum_remaining_shelf_life_in_days';

    public function __construct()
    {
        parent::__construct(
            technicalName: self::TECHNICAL_NAME,
            config: [
                'label' => [
                    'en-GB' => 'Batches and BBDs',
                    'de-DE' => 'Chargen und MHDs',
                ],
                'translated' => true,
            ],
            relations: [
                ProductDefinition::ENTITY_NAME,
                CustomerDefinition::ENTITY_NAME,
            ],
            fields: [
                new CustomField(
                    technicalName: self::CUSTOM_FIELD_MINIMUM_REMAINING_SHELF_LIFE_IN_DAYS,
                    type: CustomFieldTypes::INT,
                    config: [
                        'label' => [
                            'en-GB' => 'Minimum remaining shelf life in days',
                            'de-DE' => 'Mindestrestlaufzeit in Tagen',
                        ],
                        'allowEmpty' => true,
                    ],
                ),
            ],
            requiredFeatureFlags: [
                BatchAwarePickingDevFeatureFlag::NAME,
                BatchManagementProdFeatureFlag::NAME,
            ],
        );
    }
}
