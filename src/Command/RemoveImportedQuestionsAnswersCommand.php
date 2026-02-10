<?php

namespace App\Command;

use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup:remove-imported-qa',
    description: 'Remove Q&A components from imported presentations (JeVeuxAider, Fondation du Patrimoine, Ulule).'
)]
final class RemoveImportedQuestionsAnswersCommand extends Command
{
    private const SOURCE_JEVEUXAIDER = 'jeveuxaider';
    private const SOURCE_FONDATION = 'fondation';
    private const SOURCE_ULULE = 'ulule';

    /**
     * @var list<string>
     */
    private const ALLOWED_SOURCES = [
        self::SOURCE_JEVEUXAIDER,
        self::SOURCE_FONDATION,
        self::SOURCE_ULULE,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'source',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Restrict to one or more sources: jeveuxaider, fondation, ulule. Defaults to all three.',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Preview impacted rows without writing to database.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sources = $this->normalizeSources($input->getOption('source'));
        if ($sources === []) {
            $io->error(sprintf(
                'No valid source selected. Allowed values: %s',
                implode(', ', self::ALLOWED_SOURCES)
            ));

            return Command::INVALID;
        }

        $isDryRun = (bool) $input->getOption('dry-run');
        $io->title('Q&A cleanup on imported presentations');
        $io->text(sprintf('Sources: %s', implode(', ', $sources)));
        $io->text($isDryRun ? 'Mode: dry-run (no DB write)' : 'Mode: apply');

        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(PPBase::class, 'p');

        $conditions = [];
        if (in_array(self::SOURCE_JEVEUXAIDER, $sources, true)) {
            $conditions[] = 'p.ingestion.sourceUrl LIKE :jvaHost';
            $qb->setParameter('jvaHost', '%jeveuxaider.gouv.fr%');
        }
        if (in_array(self::SOURCE_FONDATION, $sources, true)) {
            $conditions[] = 'p.ingestion.sourceUrl LIKE :fdpHost';
            $qb->setParameter('fdpHost', '%fondation-patrimoine.org%');
        }
        if (in_array(self::SOURCE_ULULE, $sources, true)) {
            $conditions[] = '(p.ingestion.sourceUrl LIKE :ululeHost OR LOWER(COALESCE(p.fundingPlatform, \'\')) = :ululePlatform)';
            $qb->setParameter('ululeHost', '%ulule.%');
            $qb->setParameter('ululePlatform', 'ulule');
        }

        $qb
            ->where(implode(' OR ', $conditions))
            ->orderBy('p.id', 'ASC');

        $processed = 0;
        $updatedPresentations = 0;
        $removedQaItems = 0;

        foreach ($qb->getQuery()->toIterable() as $presentation) {
            if (!$presentation instanceof PPBase) {
                continue;
            }

            $processed++;
            $otherComponents = $presentation->getOtherComponents();
            $raw = $otherComponents->getRaw();

            if (!array_key_exists('questions_answers', $raw)) {
                continue;
            }

            $qaItems = $raw['questions_answers'];
            if (is_array($qaItems)) {
                $removedQaItems += count($qaItems);
            } else {
                $removedQaItems++;
            }

            unset($raw['questions_answers']);
            $otherComponents->setRaw($raw);
            $presentation->setOtherComponents($otherComponents);
            $updatedPresentations++;

            if (!$isDryRun && $updatedPresentations % 250 === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        if (!$isDryRun) {
            $this->em->flush();
            $this->em->clear();
        }

        $io->success(sprintf(
            'Done. Processed: %d, updated presentations: %d, removed Q&A items: %d%s',
            $processed,
            $updatedPresentations,
            $removedQaItems,
            $isDryRun ? ' (dry-run)' : ''
        ));

        return Command::SUCCESS;
    }

    /**
     * @param mixed $sourcesOption
     *
     * @return list<string>
     */
    private function normalizeSources(mixed $sourcesOption): array
    {
        if (!is_array($sourcesOption) || $sourcesOption === []) {
            return self::ALLOWED_SOURCES;
        }

        $normalized = [];
        foreach ($sourcesOption as $value) {
            if (!is_string($value)) {
                continue;
            }
            $candidate = strtolower(trim($value));
            if (in_array($candidate, self::ALLOWED_SOURCES, true)) {
                $normalized[$candidate] = $candidate;
            }
        }

        return array_values($normalized);
    }
}

