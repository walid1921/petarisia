<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareErpStarter\Batch\OrderDocument;

use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;

/**
 * Configuration keys and defaults for batch information display on order documents.
 *
 * These configuration values are stored in the document_base_config.config JSON field
 * for document types like invoice, delivery_note, and storno.
 */
class DocumentBatchConfiguration
{
    public const DISPLAY_BATCH_TRACKING_INFO = 'displayBatchTrackingInfo';
    public const DEFAULTS = [
        self::DISPLAY_BATCH_TRACKING_INFO => true,
    ];

    /**
     * Document types that support batch configuration.
     */
    public const SUPPORTED_DOCUMENT_TYPES = [
        InvoiceRenderer::TYPE,
        StornoRenderer::TYPE,
        DeliveryNoteRenderer::TYPE,
    ];
}
