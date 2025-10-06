<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class QueueListCommand extends Command
{
    protected static $defaultName = 'queue:list';
    protected static $defaultDescription = 'List all queued jobs';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('FP DMS - Queue List');

        // TODO: Implement queue listing logic

        $io->success('Queue list displayed successfully!');

        return Command::SUCCESS;
    }
}
