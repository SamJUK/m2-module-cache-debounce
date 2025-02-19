<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SamJUK\CacheDebounce\Api\QueueInterface;

class Flush extends Command
{
    private $queue;

    /**
     * @param QueueInterface $queue
     * @param string|null $name
     */
    public function __construct(
        QueueInterface $queue,
        $name = null
    ) {
        $this->queue = $queue;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('samjuk:cache-debounce:flush');
        $this->setDescription('Flushes any debounced purge requests.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->queue->flush();
            $output->writeln('<info>Flushed Debounced Cache Purges.</info>');
            return 0;
        } catch (LocalizedException $e) {
            $output->writeln(sprintf(
                '<error>%s</error>',
                $e->getMessage()
            ));
            return 1;
        }
    }
}
