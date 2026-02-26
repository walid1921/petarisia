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

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Exception;
use Pickware\DalBundle\EntityManager;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationDefinition;
use Pickware\PickwareErpStarter\Warehouse\Model\BinLocationEntity;
use Pickware\PickwareErpStarter\Warehouse\Model\WarehouseEntity;
use Pickware\PickwareWms\PickingProcess\Model\PickingProcessDefinition;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileDefinition;
use Pickware\PickwareWms\PickingProfile\Model\PickingProfileEntity;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventDefinition;
use Pickware\PickwareWms\Statistic\Model\DeliveryLifecycleEventType;
use Pickware\PickwareWms\Statistic\Model\PickEventDefinition;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventDefinition;
use Pickware\PickwareWms\Statistic\Model\PickingProcessLifecycleEventType;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pickware-wms:demodata:generate-picking-statistic-logs',
    description: 'Generates picking statistics demodata',
)]
class GeneratePickingStatisticLogsCommand extends Command
{
    private DateTime $startDate;
    private DateTime $endDate;
    private int $workingDayStartHour;
    private int $workingDayEndHour;
    private int $weekdayStart;
    private int $weekdayEnd;

    private const NUM_ORDERS_TO_USE = 20;
    private const NUM_PRODUCTS_TO_USE = 20;

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManager $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('users', 'u', InputOption::VALUE_REQUIRED, 'Total number of users to use (creates missing users in DB)', 5)
            ->addOption('picking-processes', null, InputOption::VALUE_REQUIRED, 'Total number of picking processes to create', 100)
            ->addOption('total-picks', null, InputOption::VALUE_REQUIRED, 'Total number of picks to create', 5000)
            ->addOption('picking-profiles', 'p', InputOption::VALUE_REQUIRED, 'Number of picking profiles', 10)
            ->addOption('defer-chance', null, InputOption::VALUE_REQUIRED, 'Chance of deferring after a pick (0-100)', 1)
            ->addOption('resume-chance', null, InputOption::VALUE_REQUIRED, 'Chance of resuming after deferment (0-100)', 50)
            ->addOption(
                'start-date',
                null,
                InputOption::VALUE_REQUIRED,
                'Start date for random timestamp generation (e.g., "1 month ago", "2024-01-01")',
                '2 months ago',
            )
            ->addOption(
                'end-date',
                null,
                InputOption::VALUE_REQUIRED,
                'End date for random timestamp generation (e.g., "now", "2024-12-31")',
                'now',
            )
            ->addOption(
                'working-day-start',
                null,
                InputOption::VALUE_REQUIRED,
                'Start hour of working day (0-23, e.g., 8 for 08:00)',
                7,
            )
            ->addOption(
                'working-day-end',
                null,
                InputOption::VALUE_REQUIRED,
                'End hour of working day (0-23, e.g., 17 for 17:00)',
                16,
            )
            ->addOption(
                'weekday-start',
                null,
                InputOption::VALUE_REQUIRED,
                'Start day of week (0=Monday, 6=Sunday)',
                0,
            )
            ->addOption(
                'weekday-end',
                null,
                InputOption::VALUE_REQUIRED,
                'End day of week (0=Monday, 6=Sunday)',
                4,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = min(50, max(1, (int) $input->getOption('users')));
        $pickingProcessesCount = max(1, (int) $input->getOption('picking-processes'));
        $totalPicks = max(1, (int) $input->getOption('total-picks'));
        $picksPerProcess = (int) ceil($totalPicks / $pickingProcessesCount);
        $pickingProfiles = max(1, (int) $input->getOption('picking-profiles'));
        $deferChance = min(100, max(0, (int) $input->getOption('defer-chance')));
        $resumeChance = min(100, max(0, (int) $input->getOption('resume-chance')));

        $this->startDate = new DateTime($input->getOption('start-date'));
        $this->endDate = new DateTime($input->getOption('end-date'));

        $workingDayStartHour = (int) $input->getOption('working-day-start');
        $workingDayEndHour = (int) $input->getOption('working-day-end');
        $weekdayStart = (int) $input->getOption('weekday-start');
        $weekdayEnd = (int) $input->getOption('weekday-end');

        if ($this->startDate > $this->endDate) {
            $io->error('Start date must be before end date.');

            return Command::FAILURE;
        }

        if ($workingDayStartHour < 0 || $workingDayStartHour > 23) {
            $io->error('Invalid working-day-start. Must be between 0 and 23.');

            return Command::FAILURE;
        }

        if ($workingDayEndHour < 0 || $workingDayEndHour > 23) {
            $io->error('Invalid working-day-end. Must be between 0 and 23.');

            return Command::FAILURE;
        }

        if ($workingDayStartHour > $workingDayEndHour) {
            $io->error('working-day-start must be less than or equal to working-day-end.');

            return Command::FAILURE;
        }

        if ($weekdayStart < 0 || $weekdayStart > 6) {
            $io->error('Invalid weekday-start. Must be between 0 (Monday) and 6 (Sunday).');

            return Command::FAILURE;
        }

        if ($weekdayEnd < 0 || $weekdayEnd > 6) {
            $io->error('Invalid weekday-end. Must be between 0 (Monday) and 6 (Sunday).');

            return Command::FAILURE;
        }

        if ($weekdayStart > $weekdayEnd) {
            $io->error('weekday-start must be less than or equal to weekday-end.');

            return Command::FAILURE;
        }

        $this->workingDayStartHour = $workingDayStartHour;
        $this->workingDayEndHour = $workingDayEndHour;
        $this->weekdayStart = $weekdayStart;
        $this->weekdayEnd = $weekdayEnd;

        $existingUsersCount = $this->entityManager->count(UserDefinition::class, 'id', [], Context::createDefaultContext());
        $existingUsersToUse = min($users, $existingUsersCount);
        $numUsersToCreate = max(0, $users - $existingUsersCount);

        $io->title('Picking Statistic Demodata Generator');
        $io->table(
            [
                'Configuration',
                'Value',
            ],
            [
                [
                    'Total users',
                    $users,
                ],
                [
                    'Existing users in DB',
                    $existingUsersCount,
                ],
                [
                    'Existing users to use',
                    $existingUsersToUse,
                ],
                [
                    'Users to create',
                    $numUsersToCreate,
                ],
                [
                    'Picking processes',
                    $pickingProcessesCount,
                ],
                [
                    'Total picks',
                    $totalPicks,
                ],
                [
                    'Picks per process',
                    $picksPerProcess,
                ],
                [
                    'Picking profiles',
                    $pickingProfiles,
                ],
                [
                    'Defer chance',
                    $deferChance . '%',
                ],
                [
                    'Resume chance',
                    $resumeChance . '%',
                ],
                [
                    'Start date',
                    $this->startDate->format(DateTimeInterface::RFC3339),
                ],
                [
                    'End date',
                    $this->endDate->format(DateTimeInterface::RFC3339),
                ],
                [
                    'Working day start',
                    sprintf('%02d:00', $this->workingDayStartHour),
                ],
                [
                    'Working day end',
                    sprintf('%02d:59', $this->workingDayEndHour),
                ],
                [
                    'Weekday start',
                    $this->getWeekdayName($this->weekdayStart),
                ],
                [
                    'Weekday end',
                    $this->getWeekdayName($this->weekdayEnd),
                ],
            ],
        );

        $io->warning('WARNING: This command will create demodata in your database. Do NOT run this on production systems!');
        if ($input->isInteractive() && !$io->confirm('Do you want to continue?', false)) {
            $io->info('Command cancelled by user.');

            return Command::SUCCESS;
        }

        $entitiesNeeded = [
            [
                'name' => 'bin locations',
                'definitionClass' => BinLocationDefinition::class,
                'count' => 1,
            ],
            [
                'name' => 'orders',
                'definitionClass' => OrderDefinition::class,
                'count' => self::NUM_ORDERS_TO_USE,
            ],
            [
                'name' => 'products',
                'definitionClass' => ProductDefinition::class,
                'count' => self::NUM_PRODUCTS_TO_USE,
            ],
        ];
        foreach ($entitiesNeeded as $entity) {
            $entityCount = $this->entityManager->count($entity['definitionClass'], 'id', [], Context::createDefaultContext());
            if ($entityCount < $entity['count']) {
                $io->error(sprintf('Not enough %s found (found %s, needed %s). Please create them first.', $entity['name'], $entityCount, $entity['count']));

                return Command::FAILURE;
            }
        }

        try {
            $userIds = $this->generateUsers($users);
            /** @var ?BinLocationEntity $binLocation */
            $binLocation = $this->entityManager->findBy(
                BinLocationDefinition::class,
                (new Criteria())->setLimit(1),
                Context::createDefaultContext(),
                ['warehouse'],
            )->first();
            $productsData = $this->getProductsData();
            $pickingProfilesData = $this->ensurePickingProfiles($pickingProfiles);
            $pickingProcesses = $this->generatePickingProcesses($pickingProcessesCount, $pickingProfilesData);

            $io->section('Generating event demodata');
            $eventCounts = $this->generateEventDemodata(
                $userIds,
                $binLocation->getWarehouse(),
                $binLocation,
                $productsData,
                $pickingProcesses,
                $picksPerProcess,
                $deferChance,
                $resumeChance,
                $io,
            );

            $io->success([
                sprintf(
                    'Using %d total users (%d existing, %d newly created)',
                    $users,
                    $existingUsersToUse,
                    $numUsersToCreate,
                ),
                sprintf('Successfully created %d picking processes', $pickingProcessesCount),
                sprintf('Successfully created %d pick events', $eventCounts['pickEvents']),
                sprintf('Successfully created %d picking process lifecycle events', $eventCounts['pickingProcessLifecycleEvents']),
                sprintf('Successfully created %d delivery lifecycle events', $eventCounts['deliveryLifecycleEvents']),
            ]);

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return list<string>
     */
    private function generateUsers(int $count): array
    {
        $existingUsers = $this->entityManager->findBy(
            UserDefinition::class,
            (new Criteria())->setLimit($count),
            Context::createDefaultContext(),
        );
        $userIds = [];

        /** @var UserEntity $existingUser */
        foreach ($existingUsers as $existingUser) {
            $userIds[] = $existingUser->getId();
        }

        $newUsersToCreate = [];
        $localeId = $this->entityManager->findIdsBy(LocaleDefinition::class, ['code' => 'de-DE'], Context::createDefaultContext())[0];
        for ($i = 0; $i < ($count - $existingUsers->count()); $i++) {
            $newUserId = Uuid::randomHex();
            $userIds[] = $newUserId;

            $newUsersToCreate[] = [
                'id' => $newUserId,
                'username' => 'picker_' . bin2hex(random_bytes(4)),
                'password' => password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT),
                'firstName' => 'User ' . ($existingUsers->count() + $i + 1),
                'lastName' => 'Test',
                'email' => 'picker_' . bin2hex(random_bytes(4)) . '@example.com',
                'active' => true,
                'localeId' => $localeId,
            ];
        }

        if (!empty($newUsersToCreate)) {
            $this->entityManager->create(
                UserDefinition::class,
                $newUsersToCreate,
                Context::createDefaultContext(),
            );
        }

        return $userIds;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getProductsData(): array
    {
        /** @var EntityCollection<ProductEntity> $products */
        $products = $this->entityManager->findBy(ProductDefinition::class, (new Criteria())->setLimit(self::NUM_PRODUCTS_TO_USE), Context::createDefaultContext());

        $productsData = [];
        foreach ($products as $product) {
            $productsData[] = [
                'id' => $product->getId(),
                'snapshot' => [
                    'productNumber' => $product->getProductNumber(),
                    'name' => $product->getName() ?? 'Product ' . $product->getProductNumber(),
                    'ean' => $product->getEan(),
                ],
                'weight' => round(random_int(100, 5000) / 100, 2),
            ];
        }

        return $productsData;
    }

    /**
     * @param list<array<string, mixed>> $pickingProfilesData
     * @return list<array<string, mixed>>
     */
    private function generatePickingProcesses(
        int $processCount,
        array $pickingProfilesData,
    ): array {
        /** @var EntityCollection<OrderEntity> $orders */
        $orders = $this->entityManager->findBy(OrderDefinition::class, (new Criteria())->setLimit(self::NUM_ORDERS_TO_USE), Context::createDefaultContext());

        $pickingProcesses = [];
        for ($i = 0; $i < $processCount; $i++) {
            $pickingProfile = $pickingProfilesData[array_rand($pickingProfilesData)];
            $pickingMode = PickingProcessDefinition::PICKING_MODES[array_rand(PickingProcessDefinition::PICKING_MODES)];
            $order = $orders->getElements()[array_rand($orders->getElements())];

            $pickingProcesses[] = [
                'id' => Uuid::randomHex(),
                'deliveryId' => Uuid::randomHex(),
                'snapshot' => [
                    'number' => 'PP-' . str_pad((string)($i + 1), 5, '0', STR_PAD_LEFT),
                ],
                'pickingMode' => $pickingMode,
                'pickingProfileId' => $pickingProfile['id'],
                'pickingProfileSnapshot' => [
                    'name' => $pickingProfile['name'],
                ],
                'orderId' => $order->getId(),
                'orderVersionId' => $order->getVersionId(),
                'orderSnapshot' => [
                    'number' => $order->getOrderNumber(),
                ],
                'state' => 'new',
            ];
        }

        return $pickingProcesses;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ensurePickingProfiles(int $count): array
    {
        /** @var EntityCollection<PickingProfileEntity> $existingProfiles */
        $existingProfiles = $this->entityManager->findBy(
            PickingProfileDefinition::class,
            (new Criteria())->setLimit($count),
            Context::createDefaultContext(),
        );

        $pickingProfilesData = $existingProfiles->map(fn(PickingProfileEntity $existingProfile) => [
            'id' => $existingProfile->getId(),
            'name' => $existingProfile->getName(),
        ]);

        $missingProfilesCount = $count - $existingProfiles->count();
        if ($missingProfilesCount > 0) {
            $newProfiles = $this->generatePickingProfiles($missingProfilesCount);
            $this->entityManager->create(
                PickingProfileDefinition::class,
                $newProfiles,
                Context::createDefaultContext(),
            );
            $pickingProfilesData = array_merge($pickingProfilesData, $newProfiles);
        }

        return $pickingProfilesData;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function generatePickingProfiles(int $count): array
    {
        $pickingProfiles = [];
        for ($i = 0; $i < $count; $i++) {
            $id = Uuid::randomHex();
            $pickingProfiles[] = [
                'id' => $id,
                'name' => 'Profile ' . mb_substr($id, -8),
                'position' => $i + 1,
                'isPartialDeliveryAllowed' => (bool) random_int(0, 1),
            ];
        }

        return $pickingProfiles;
    }

    /**
     * @param list<string> $userIds
     * @param list<array<string, mixed>> $productsData
     * @param list<array<string, mixed>> $pickingProcesses
     * @return array{pickEvents: int, pickingProcessLifecycleEvents: int, deliveryLifecycleEvents: int}
     */
    private function generateEventDemodata(
        array $userIds,
        WarehouseEntity $warehouse,
        BinLocationEntity $binLocation,
        array $productsData,
        array $pickingProcesses,
        int $picksPerProcess,
        int $deferChance,
        int $resumeChance,
        SymfonyStyle $io,
    ): array {
        $users = $this->entityManager->findBy(
            UserDefinition::class,
            ['id' => $userIds],
            Context::createDefaultContext(),
            ['aclRoles'],
        );

        $batchSize = 1000;
        $pickEvents = [];
        $pickingProcessLifecycleEvents = [];
        $deliveryLifecycleEvents = [];
        $totalPicks = $picksPerProcess * count($pickingProcesses);
        $actualPicks = 0;
        $actualPickingProcessLifecycleEvents = 0;
        $actualDeliveryLifecycleEvents = 0;

        $this->connection->beginTransaction();

        $io->progressStart($totalPicks);

        try {
            foreach ($pickingProcesses as $currentProcess) {
                // Randomly assign a user to this process
                /** @var UserEntity $currentProcessUser */
                $currentProcessUser = $users->getAt(random_int(0, $users->count() - 1));
                $currentProcessTimestamp = $this->generateRandomWorkingDateTime();
                // Create and start the picking process and create a delivery for it
                $pickingProcessLifecycleEvents[] = $this->generatePickingProcessLifecycleEventPayload(
                    PickingProcessLifecycleEventType::Create,
                    $currentProcess,
                    $currentProcessUser,
                    $warehouse,
                    $currentProcessTimestamp,
                );
                $actualPickingProcessLifecycleEvents++;
                $pickingProcessLifecycleEvents[] = $this->generatePickingProcessLifecycleEventPayload(
                    PickingProcessLifecycleEventType::Continue,
                    $currentProcess,
                    $currentProcessUser,
                    $warehouse,
                    $currentProcessTimestamp,
                );
                $actualPickingProcessLifecycleEvents++;
                $deliveryLifecycleEvents[] = $this->generateDeliveryLifecycleEventPayload(
                    DeliveryLifecycleEventType::Create,
                    $currentProcess,
                    $currentProcessUser,
                    $warehouse,
                    $currentProcessTimestamp,
                );
                $actualDeliveryLifecycleEvents++;

                $processAbandoned = false;
                // Generate picks for this process
                for ($pickIndex = 0; $pickIndex < $picksPerProcess; $pickIndex++) {
                    // Check if the picking process should be deferred
                    if (random_int(1, 100) <= $deferChance) {
                        $currentProcessTimestamp = $this->addRandomTime($currentProcessTimestamp, 5, 30);
                        $pickingProcessLifecycleEvents[] = $this->generatePickingProcessLifecycleEventPayload(
                            PickingProcessLifecycleEventType::Defer,
                            $currentProcess,
                            $currentProcessUser,
                            $warehouse,
                            $currentProcessTimestamp,
                        );
                        $actualPickingProcessLifecycleEvents++;

                        // Check if process should resume or be abandoned
                        if (random_int(1, 100) <= $resumeChance) {
                            $currentProcessTimestamp = $this->addRandomTime($currentProcessTimestamp, 300, 7200);
                            $pickingProcessLifecycleEvents[] = $this->generatePickingProcessLifecycleEventPayload(
                                PickingProcessLifecycleEventType::Continue,
                                $currentProcess,
                                $currentProcessUser,
                                $warehouse,
                                $currentProcessTimestamp,
                            );
                            $actualPickingProcessLifecycleEvents++;
                        } else {
                            // Abandon the process
                            $processAbandoned = true;
                            $io->progressAdvance($picksPerProcess - $pickIndex);
                            break;
                        }
                    }

                    $currentProcessTimestamp = $this->addRandomTime($currentProcessTimestamp);
                    $product = $productsData[array_rand($productsData)];
                    $pickEvents[] = $this->generatePickEventPayload(
                        $product,
                        $currentProcessUser,
                        $warehouse,
                        $binLocation,
                        $currentProcess,
                        $currentProcessTimestamp,
                    );
                    $actualPicks++;

                    $io->progressAdvance();
                }

                // Complete the process if not abandoned
                if (!$processAbandoned) {
                    $pickingProcessLifecycleEvents[] = $this->generatePickingProcessLifecycleEventPayload(
                        PickingProcessLifecycleEventType::Complete,
                        $currentProcess,
                        $currentProcessUser,
                        $warehouse,
                        $currentProcessTimestamp,
                    );
                    $actualPickingProcessLifecycleEvents++;
                    $deliveryLifecycleEvents[] = $this->generateDeliveryLifecycleEventPayload(
                        DeliveryLifecycleEventType::Complete,
                        $currentProcess,
                        $currentProcessUser,
                        $warehouse,
                        $currentProcessTimestamp,
                    );
                    $deliveryLifecycleEvents[] = $this->generateDeliveryLifecycleEventPayload(
                        DeliveryLifecycleEventType::Ship,
                        $currentProcess,
                        $currentProcessUser,
                        $warehouse,
                        $currentProcessTimestamp,
                    );
                    $actualDeliveryLifecycleEvents++;
                }

                // Insert batch if we've reached the batch size
                if (count($pickEvents) >= $batchSize) {
                    $this->insertBatch(
                        $pickEvents,
                        $pickingProcessLifecycleEvents,
                        $deliveryLifecycleEvents,
                    );
                    $pickEvents = [];
                    $pickingProcessLifecycleEvents = [];
                    $deliveryLifecycleEvents = [];
                }
            }

            // Insert any remaining events
            if (!empty($pickEvents) || !empty($pickingProcessLifecycleEvents) || !empty($deliveryLifecycleEvents)) {
                $this->insertBatch(
                    $pickEvents,
                    $pickingProcessLifecycleEvents,
                    $deliveryLifecycleEvents,
                );
            }

            $this->connection->commit();
            $io->progressFinish();

            return [
                'pickEvents' => $actualPicks,
                'pickingProcessLifecycleEvents' => $actualPickingProcessLifecycleEvents,
                'deliveryLifecycleEvents' => $actualDeliveryLifecycleEvents,
            ];
        } catch (Exception $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function generatePickingProcessLifecycleEventPayload(
        PickingProcessLifecycleEventType $eventTechnicalName,
        mixed $currentProcess,
        UserEntity $currentProcessUser,
        WarehouseEntity $warehouse,
        string $eventCreatedAt,
    ): array {
        $lifecycleEventId = Uuid::randomHex();

        return [
            'id' => $lifecycleEventId,
            'eventTechnicalName' => $eventTechnicalName,
            'pickingProcessReferenceId' => $currentProcess['id'],
            'pickingProcessSnapshot' => $currentProcess['snapshot'],
            'userReferenceId' => $currentProcessUser->getId(),
            'userSnapshot' => [
                'id' => $currentProcessUser->getId(),
                'firstName' => $currentProcessUser->getFirstName(),
                'lastName' => $currentProcessUser->getLastName(),
                'email' => $currentProcessUser->getEmail(),
                'username' => $currentProcessUser->getUsername(),
            ],
            'warehouseReferenceId' => $warehouse->getId(),
            'warehouseSnapshot' => [
                'name' => $warehouse->getName(),
                'code' => $warehouse->getCode(),
            ],
            'pickingMode' => $currentProcess['pickingMode'],
            'pickingProfileReferenceId' => $currentProcess['pickingProfileId'],
            'pickingProfileSnapshot' => $currentProcess['pickingProfileSnapshot'],
            'deviceReferenceId' => null,
            'deviceSnapshot' => null,
            'eventCreatedAt' => $eventCreatedAt,
            'eventCreatedAtLocaltime' => (new DateTimeImmutable($eventCreatedAt))->setTimezone(new DateTimeZone('Europe/Berlin'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'eventCreatedAtLocaltimeTimezone' => 'Europe/Berlin',
            'userRoles' => $currentProcessUser->getAclRoles()->map(fn(AclRoleEntity $aclRole) => [
                'id' => Uuid::randomHex(),
                'pickingProcessLifecycleEventId' => $lifecycleEventId,
                'userRoleReferenceId' => $aclRole->getId(),
                'userRoleSnapshot' => [
                    'id' => $aclRole->getId(),
                    'name' => $aclRole->getName(),
                ],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateDeliveryLifecycleEventPayload(
        DeliveryLifecycleEventType $eventTechnicalName,
        mixed $currentProcess,
        UserEntity $currentProcessUser,
        WarehouseEntity $warehouse,
        string $currentProcessTimestamp,
    ): array {
        $lifecycleEventId = Uuid::randomHex();

        return [
            'id' => $lifecycleEventId,
            'eventTechnicalName' => $eventTechnicalName,
            'deliveryReferenceId' => $currentProcess['deliveryId'],
            'userReferenceId' => $currentProcessUser->getId(),
            'userSnapshot' => [
                'id' => $currentProcessUser->getId(),
                'firstName' => $currentProcessUser->getFirstName(),
                'lastName' => $currentProcessUser->getLastName(),
                'email' => $currentProcessUser->getEmail(),
                'username' => $currentProcessUser->getUsername(),
            ],
            'orderReferenceId' => $currentProcess['orderId'],
            'orderVersionId' => $currentProcess['orderVersionId'],
            'orderSnapshot' => $currentProcess['orderSnapshot'],
            'pickingProcessReferenceId' => $currentProcess['id'],
            'pickingProcessSnapshot' => $currentProcess['snapshot'],
            'warehouseReferenceId' => $warehouse->getId(),
            'warehouseSnapshot' => [
                'name' => $warehouse->getName(),
                'code' => $warehouse->getCode(),
            ],
            'pickingMode' => $currentProcess['pickingMode'],
            'pickingProfileReferenceId' => $currentProcess['pickingProfileId'],
            'pickingProfileSnapshot' => $currentProcess['pickingProfileSnapshot'],
            'salesChannelReferenceId' => Uuid::randomHex(),
            'salesChannelSnapshot' => [],
            'deviceReferenceId' => null,
            'deviceSnapshot' => null,
            'eventCreatedAt' => $currentProcessTimestamp,
            'eventCreatedAtLocaltime' => (new DateTimeImmutable($currentProcessTimestamp))->setTimezone(new DateTimeZone('Europe/Berlin'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'eventCreatedAtLocaltimeTimezone' => 'Europe/Berlin',
            'userRoles' => $currentProcessUser->getAclRoles()->map(fn(AclRoleEntity $aclRole) => [
                'id' => Uuid::randomHex(),
                'deliveryLifecycleEventId' => $lifecycleEventId,
                'userRoleReferenceId' => $aclRole->getId(),
                'userRoleSnapshot' => [
                    'id' => $aclRole->getId(),
                    'name' => $aclRole->getName(),
                ],
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function generatePickEventPayload(
        array $product,
        UserEntity $currentProcessUser,
        WarehouseEntity $warehouse,
        BinLocationEntity $binLocation,
        mixed $currentProcess,
        string $currentProcessTimestamp,
    ): array {
        $pickEventId = Uuid::randomHex();

        return [
            'id' => $pickEventId,
            'productReferenceId' => $product['id'],
            'productSnapshot' => $product['snapshot'],
            'productWeight' => $product['weight'],
            'userReferenceId' => $currentProcessUser->getId(),
            'userSnapshot' => [
                'id' => $currentProcessUser->getId(),
                'firstName' => $currentProcessUser->getFirstName(),
                'lastName' => $currentProcessUser->getLastName(),
                'email' => $currentProcessUser->getEmail(),
                'username' => $currentProcessUser->getUsername(),
            ],
            'warehouseReferenceId' => $warehouse->getId(),
            'warehouseSnapshot' => [
                'name' => $warehouse->getName(),
                'code' => $warehouse->getCode(),
            ],
            'binLocationReferenceId' => $binLocation->getId(),
            'binLocationSnapshot' => [
                'code' => $binLocation->getCode(),
            ],
            'pickingProcessReferenceId' => $currentProcess['id'],
            'pickingProcessSnapshot' => $currentProcess['snapshot'],
            'pickingMode' => $currentProcess['pickingMode'],
            'pickingProfileReferenceId' => $currentProcess['pickingProfileId'],
            'pickingProfileSnapshot' => $currentProcess['pickingProfileSnapshot'],
            'pickedQuantity' => random_int(1, 3),
            'pickCreatedAt' => $currentProcessTimestamp,
            'pickCreatedAtLocaltime' => (new DateTimeImmutable($currentProcessTimestamp))->setTimezone(new DateTimeZone('Europe/Berlin'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'pickCreatedAtLocaltimeTimezone' => 'Europe/Berlin',
            'userRoles' => $currentProcessUser->getAclRoles()->map(fn(AclRoleEntity $aclRole) => [
                'id' => Uuid::randomHex(),
                'pickEventId' => $pickEventId,
                'userRoleReferenceId' => $aclRole->getId(),
                'userRoleSnapshot' => [
                    'id' => $aclRole->getId(),
                    'name' => $aclRole->getName(),
                ],
            ]),
        ];
    }

    private function generateRandomWorkingDateTime(): string
    {
        $numValidDates = $this->countValidWeekdays($this->startDate, $this->endDate);

        if ($numValidDates === 0) {
            throw new Exception('No valid weekdays in the date range');
        }

        $dateOffset = $this->selectWeightedDateOffset($numValidDates);
        $randomDate = $this->advanceByValidDays($this->startDate, $dateOffset);

        $randomHour = $this->selectWeightedHour();
        $randomMinute = random_int(0, 59);
        $randomSecond = random_int(0, 59);

        $randomDate->setTime($randomHour, $randomMinute, $randomSecond);

        return $randomDate->format('Y-m-d H:i:s.000');
    }

    /**
     * Counts the number of valid weekdays in the date range using range overlap calculation (O(1)).
     */
    private function countValidWeekdays(DateTime $start, DateTime $end): int
    {
        $totalDays = $start->diff($end)->days + 1;
        $startWeekday = (int) $start->format('N') - 1;

        $completeWeeks = (int) floor($totalDays / 7);
        $remainingDays = $totalDays % 7;
        $validDaysPerWeek = $this->weekdayEnd - $this->weekdayStart + 1;

        $validCount = $completeWeeks * $validDaysPerWeek;

        if ($remainingDays > 0) {
            $endWeekday = $startWeekday + $remainingDays - 1;

            if ($endWeekday < 7) {
                $overlapStart = max($startWeekday, $this->weekdayStart);
                $overlapEnd = min($endWeekday, $this->weekdayEnd);
                $validCount += max(0, $overlapEnd - $overlapStart + 1);
            } else {
                $overlapStart1 = max($startWeekday, $this->weekdayStart);
                $overlapEnd1 = min(6, $this->weekdayEnd);
                $validCount += max(0, $overlapEnd1 - $overlapStart1 + 1);

                $endWeekdayWrapped = $endWeekday % 7;
                $overlapStart2 = max(0, $this->weekdayStart);
                $overlapEnd2 = min($endWeekdayWrapped, $this->weekdayEnd);
                $validCount += max(0, $overlapEnd2 - $overlapStart2 + 1);
            }
        }

        return $validCount;
    }

    /**
     * Advances from the start date by a given number of valid weekdays, skipping invalid days.
     */
    private function advanceByValidDays(DateTime $start, int $validDaysOffset): DateTime
    {
        $result = clone $start;
        $currentWeekday = (int) $result->format('N') - 1;

        if ($currentWeekday < $this->weekdayStart) {
            $daysToAdd = $this->weekdayStart - $currentWeekday;
            $result->modify("+{$daysToAdd} days");
            $currentWeekday = $this->weekdayStart;
        } elseif ($currentWeekday > $this->weekdayEnd) {
            $daysToAdd = (7 - $currentWeekday) + $this->weekdayStart;
            $result->modify("+{$daysToAdd} days");
            $currentWeekday = $this->weekdayStart;
        }

        if ($validDaysOffset === 0) {
            return $result;
        }

        $validDaysPerWeek = $this->weekdayEnd - $this->weekdayStart + 1;
        $currentPosition = $currentWeekday - $this->weekdayStart;
        $targetPosition = $currentPosition + $validDaysOffset;
        $fullWeeksCrossed = (int) floor($targetPosition / $validDaysPerWeek);
        $finalPosition = $targetPosition % $validDaysPerWeek;
        $calendarDays = $fullWeeksCrossed * 7 + ($finalPosition - $currentPosition);

        $result->modify("+{$calendarDays} days");

        return $result;
    }

    /**
     * Selects a valid date offset with weekday-based weighting (peak at weekdayStart, falling to weekdayEnd).
     */
    private function selectWeightedDateOffset(int $numValidDates): int
    {
        $startWeekday = (int) $this->startDate->format('N') - 1;
        $validDaysPerWeek = $this->weekdayEnd - $this->weekdayStart + 1;

        $firstValidWeekday = $startWeekday;
        if ($startWeekday < $this->weekdayStart) {
            $firstValidWeekday = $this->weekdayStart;
        } elseif ($startWeekday > $this->weekdayEnd) {
            $firstValidWeekday = $this->weekdayStart;
        }
        $startValidPosition = $firstValidWeekday - $this->weekdayStart;

        return $this->selectWeightedIndexWithCycle($numValidDates, $validDaysPerWeek, $startValidPosition);
    }

    /**
     * Selects a working hour with position-based weighting (peak at workingDayStartHour, falling to workingDayEndHour).
     */
    private function selectWeightedHour(): int
    {
        $hoursInRange = $this->workingDayEndHour - $this->workingDayStartHour + 1;

        return $this->workingDayStartHour + $this->selectWeightedIndexWithCycle($hoursInRange, $hoursInRange, 0);
    }

    /**
     * Selects an index using weighted random selection with a repeating cycle pattern.
     * When cycleLength equals count, this becomes simple position-based weighting.
     */
    private function selectWeightedIndexWithCycle(int $count, int $cycleLength, int $startPosition): int
    {
        $weights = [];
        for ($i = 0; $i < $count; $i++) {
            $positionInCycle = ($startPosition + $i) % $cycleLength;
            $relativePosition = $positionInCycle / max(1, $cycleLength - 1);
            $weight = 100 - (int) round($relativePosition * 70);
            $weights[] = max(30, $weight);
        }

        $totalWeight = array_sum($weights);
        $random = random_int(1, $totalWeight);

        $cumulativeWeight = 0;
        for ($i = 0; $i < $count; $i++) {
            $cumulativeWeight += $weights[$i];
            if ($random <= $cumulativeWeight) {
                return $i;
            }
        }

        return $count - 1;
    }

    private function getWeekdayName(int $weekday): string
    {
        $weekdays = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ];

        return $weekdays[$weekday] ?? 'Unknown';
    }

    private function addRandomTime(string $currentTimestamp, int $minSeconds = 30, int $maxSeconds = 300): string
    {
        $dateTime = new DateTime($currentTimestamp);
        $secondsToAdd = random_int($minSeconds, $maxSeconds);
        $dateTime->modify("+{$secondsToAdd} seconds");

        $currentHour = (int) $dateTime->format('H');

        if ($currentHour > $this->workingDayEndHour) {
            $dateTime->modify('+1 day');
            $dateTime->setTime($this->workingDayStartHour, 0, 0);

            $dayOfWeek = (int) $dateTime->format('N') - 1;
            if ($dayOfWeek < $this->weekdayStart) {
                $daysToAdd = $this->weekdayStart - $dayOfWeek;
                $dateTime->modify("+{$daysToAdd} days");
            } elseif ($dayOfWeek > $this->weekdayEnd) {
                $daysToAdd = (7 - $dayOfWeek) + $this->weekdayStart;
                $dateTime->modify("+{$daysToAdd} days");
            }
        }

        return $dateTime->format('Y-m-d H:i:s.000');
    }

    /**
     * @param list<array<string, mixed>> $pickEvents
     * @param list<array<string, mixed>> $pickingProcessLifecycleEvents
     * @param list<array<string, mixed>> $deliveryLifecycleEvents
     */
    private function insertBatch(
        array $pickEvents,
        array $pickingProcessLifecycleEvents = [],
        array $deliveryLifecycleEvents = [],
    ): void {
        $this->entityManager->create(
            PickingProcessLifecycleEventDefinition::class,
            $pickingProcessLifecycleEvents,
            Context::createDefaultContext(),
        );
        $this->entityManager->create(
            DeliveryLifecycleEventDefinition::class,
            $deliveryLifecycleEvents,
            Context::createDefaultContext(),
        );
        $this->entityManager->create(
            PickEventDefinition::class,
            $pickEvents,
            Context::createDefaultContext(),
        );
    }
}
