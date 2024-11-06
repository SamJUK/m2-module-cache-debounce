<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SamJUK\CacheDebounce\Model\Entries as CacheDebouncedEntries;

class Flush extends Command
{
    private $cacheDebouncedEntries;

    /**
     * @param CacheDebouncedEntries $cacheDebouncedEntries
     * @param string|null $name
     */
    public function __construct(
        CacheDebouncedEntries $cacheDebouncedEntries,
        $name = null
    ) {
        $this->cacheDebouncedEntries = $cacheDebouncedEntries;
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
            $this->cacheDebouncedEntries->flush();
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
