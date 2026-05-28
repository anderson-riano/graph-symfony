<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Command;

use App\Application\Messaging\Service\OutboxPublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:outbox:publish', description: 'Publishes pending outbox messages to Messenger transports.')]
final class OutboxPublishCommand extends Command
{
    public function __construct(
        private readonly OutboxPublisher $outboxPublisher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Batch size for pending outbox messages.', '50')
            ->addOption('loop', null, InputOption::VALUE_NONE, 'Keep polling and publishing in loop mode.')
            ->addOption('sleep-ms', null, InputOption::VALUE_REQUIRED, 'Sleep time in milliseconds between loop iterations.', '1000')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Stop loop mode after N seconds (0 = no limit).', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = max(1, (int) $input->getOption('limit'));
        $loop = (bool) $input->getOption('loop');
        $sleepMs = max(10, (int) $input->getOption('sleep-ms'));
        $timeLimit = max(0, (int) $input->getOption('time-limit'));
        $startedAt = time();

        $published = 0;
        do {
            $publishedInBatch = $this->outboxPublisher->publishPending($limit);
            $published += $publishedInBatch;

            if (!$loop) {
                break;
            }

            if ($timeLimit > 0 && (time() - $startedAt) >= $timeLimit) {
                break;
            }

            if ($publishedInBatch === 0) {
                usleep($sleepMs * 1000);
            }
        } while (true);

        $io->success(sprintf('Published %d outbox message(s).', $published));

        return Command::SUCCESS;
    }
}
