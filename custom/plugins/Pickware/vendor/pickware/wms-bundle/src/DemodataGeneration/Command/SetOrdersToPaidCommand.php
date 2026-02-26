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
use Pickware\ShopwareExtensionsBundle\StateTransitioning\EntityStateDefinition;
use Pickware\ShopwareExtensionsBundle\StateTransitioning\StateTransitionBatchService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pickware-wms:demodata:set-orders-to-paid',
    description: 'Sets all unpaid orders to paid state.',
)]
class SetOrdersToPaidCommand extends Command
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly StateTransitionBatchService $stateTransitionBatchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Setting all orders to paid');

        $context = Context::createCLIContext();

        $transactionIds = $this->entityManager->findIdsBy(
            OrderTransactionDefinition::class,
            [
                'stateMachineState.technicalName' => OrderTransactionStates::STATE_OPEN,
            ],
            $context,
        );

        if (count($transactionIds) === 0) {
            $io->info('No unpaid order found.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Setting %d order transactions to paid...', count($transactionIds)));

        $this->stateTransitionBatchService->ensureTargetStateForEntities(
            EntityStateDefinition::orderTransaction(),
            $transactionIds,
            OrderTransactionStates::STATE_PAID,
            $context,
        );

        $io->success(sprintf('Successfully set %d order transactions to paid.', count($transactionIds)));

        return Command::SUCCESS;
    }
}
