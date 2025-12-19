<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:notifications:send-weekly-digest',
    description: 'Send weekly notification digest emails.'
)]
final class SendWeeklyNotificationDigestCommand extends Command
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->notificationService->sendWeeklyDigest();

        $output->writeln(sprintf(
            'Digests sent: %d; skipped: %d; empty: %d.',
            $result['sent'] ?? 0,
            $result['skipped'] ?? 0,
            $result['empty'] ?? 0
        ));

        return Command::SUCCESS;
    }
}
