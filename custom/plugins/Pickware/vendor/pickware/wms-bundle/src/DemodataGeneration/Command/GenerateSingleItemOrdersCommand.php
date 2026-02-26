<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Pickware\PickwareWms\DemodataGeneration\Command;

use Pickware\DalBundle\EntityManager;
use Pickware\ShopwareExtensionsBundle\Product\ProductNameFormatterService;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\EntityStateDefinition;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionBatchService;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'pickware-wms:demodata:generate-single-item-orders',
    description: 'Generates single item orders (exactly one product with quantity 1, Einpöster) in status paid.',
)]
class GenerateSingleItemOrdersCommand extends Command
{
    public function __construct(
        private readonly DemodataService $demodataService,
        private readonly EntityManager $entityManager,
        private readonly StateTransitionBatchService $stateTransitionBatchService,
        private readonly ProductNameFormatterService $productNameFormatterService,
        private readonly RecalculationService $recalculationService,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'count',
            'c',
            InputOption::VALUE_REQUIRED,
            'Number of orders to generate',
            50,
        );
        $this->addOption(
            'product-count',
            'p',
            InputOption::VALUE_REQUIRED,
            'Number of different products to pick from',
            5,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generating Single Item Orders');

        $count = (int) $input->getOption('count');
        if ($count <= 0) {
            $io->error('Count must be a positive integer.');

            return Command::FAILURE;
        }

        $productCount = (int) $input->getOption('product-count');
        if ($productCount <= 0) {
            $io->error('Product count must be a positive integer.');

            return Command::FAILURE;
        }

        $context = Context::createCLIContext();

        // Generate orders using Shopware's DemodataService. Line items will be modified later on.
        $orderRequest = new DemodataRequest();
        $orderRequest->add(OrderDefinition::class, $count);
        $io->info(sprintf('Generating %d base orders...', $count));
        $orderIds = $this->generateOrders($orderRequest, $context, $io);

        $io->info('Adjusting orders to have exactly one item with quantity 1...');

        // 1. Remove all existing line items for these orders
        $this->entityManager->deleteByCriteria(
            OrderLineItemDefinition::class,
            ['orderId' => $orderIds],
            $context,
        );

        // 2. Add exactly one line item with quantity 1 per order
        $criteria = new Criteria();
        $criteria->setLimit($productCount);
        $queryBuilder = $this->entityManager->createQueryBuilder(ProductDefinition::class, $criteria, $context);
        $queryBuilder->addOrderBy('RAND()');
        $productIds = $queryBuilder->executeQuery()->fetchFirstColumn();
        $productIds = array_map(fn($id) => mb_strtolower((string) $id), $productIds);

        if (count($productIds) === 0) {
            $io->error('No products found to create line items. Ensure that there are products in your shop.');

            return Command::FAILURE;
        }

        $productNamesById = $this->productNameFormatterService->getFormattedProductNames($productIds, [], $context);

        $lineItemPayloads = [];
        foreach ($orderIds as $orderId) {
            $randomIndex = (int) array_rand($productIds);
            $productId = $productIds[$randomIndex];
            $id = Uuid::randomHex();

            $lineItemPayloads[] = [
                'id' => $id,
                'identifier' => $id,
                'orderId' => $orderId,
                'productId' => $productId,
                'referencedId' => $productId,
                'quantity' => 1,
                'label' => $productNamesById[$productId],
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'position' => 1,
                'price' => new CalculatedPrice(
                    10.0,
                    10.0,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection(),
                    1,
                ),
                'priceDefinition' => new QuantityPriceDefinition(
                    10.0,
                    new TaxRuleCollection([
                        new TaxRule(19, 100),
                    ]),
                    1,
                ),
                'payload' => [
                    'productNumber' => 'DEMO-' . Uuid::randomHex(),
                ],
                'good' => true,
                'removable' => true,
                'stackable' => true,
            ];
        }

        $this->entityManager->create(OrderLineItemDefinition::class, $lineItemPayloads, $context);

        $io->info('Recalculating orders...');
        $io->progressStart(count($orderIds));
        foreach ($orderIds as $orderId) {
            $versionId = $this->entityManager->createVersion(OrderDefinition::class, $orderId, $context);
            $versionContext = $context->createWithVersionId($versionId);
            $this->recalculationService->recalculate($orderId, $versionContext);
            $this->entityManager->merge(OrderDefinition::class, $versionId, $context);
            $io->progressAdvance();
        }
        $io->progressFinish();

        $io->info('Marking orders as paid...');

        $transactionIds = $this->entityManager->findIdsBy(
            OrderTransactionDefinition::class,
            ['orderId' => $orderIds],
            $context,
        );

        $this->stateTransitionBatchService->ensureTargetStateForEntities(
            EntityStateDefinition::orderTransaction(),
            $transactionIds,
            OrderTransactionStates::STATE_PAID,
            $context,
        );

        $io->success(sprintf('Successfully generated %d single line item orders.', $count));

        return Command::SUCCESS;
    }

    /**
     * The demodata generator of Shopware does not return any information about the generated orders. We therefore
     * need to retrieve the IDs of the generated orders in a "creative" way. A subscriber is registered temporarily,
     * which then collects the generated IDs.
     *
     * @return array<string>
     */
    private function generateOrders(DemodataRequest $orderRequest, Context $context, SymfonyStyle $io): array
    {
        $generatedOrderIds = [];
        $listener = function(EntityWriteEvent $event) use (&$generatedOrderIds): void {
            $generatedOrderIds = [
                ...$generatedOrderIds,
                ...array_map(
                    fn(WriteCommand $command) => bin2hex($command->getPrimaryKey()['id']),
                    $event->getCommandsForEntity(OrderDefinition::ENTITY_NAME),
                ),
            ];
        };
        $this->eventDispatcher->addListener(
            EntityWriteEvent::class,
            $listener,
        );

        $this->demodataService->generate($orderRequest, $context, $io);

        $this->eventDispatcher->removeListener(EntityWriteEvent::class, $listener);

        return $generatedOrderIds;
    }
}
