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
    name: 'app:cleanup:delete-jva-covid-projects',
    description: 'Delete JeVeuxAider projects that reference COVID-19 in title/goal/keywords/description.'
)]
final class DeleteJeVeuxAiderCovidProjectsCommand extends Command
{
    /**
     * Detect common COVID references, including variants with/without separator.
     */
    private const COVID_REGEX = '/\b(covid(?:[-\s]?19)?|sars[\s-]?cov[\s-]?2)\b/i';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Preview impacted projects without deleting rows.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = (bool) $input->getOption('dry-run');

        $io->title('JeVeuxAider COVID cleanup');
        $io->text($isDryRun ? 'Mode: dry-run (no DB write)' : 'Mode: apply (hard delete)');
        $io->text('Scope: JeVeuxAider source URL only');

        $query = $this->em->createQueryBuilder()
            ->select('p')
            ->from(PPBase::class, 'p')
            ->where('p.ingestion.sourceUrl LIKE :host')
            ->setParameter('host', '%jeveuxaider.gouv.fr%')
            ->orderBy('p.id', 'ASC')
            ->getQuery();

        $processed = 0;
        $matched = 0;
        $deleted = 0;
        $samples = [];

        foreach ($query->toIterable() as $presentation) {
            if (!$presentation instanceof PPBase) {
                continue;
            }

            $processed++;
            if (!$this->containsCovidReference($presentation)) {
                continue;
            }

            $matched++;
            if (count($samples) < 10) {
                $samples[] = sprintf(
                    '#%d | %s',
                    (int) $presentation->getId(),
                    (string) ($presentation->getTitle() ?? $presentation->getGoal())
                );
            }

            if ($isDryRun) {
                continue;
            }

            $this->em->remove($presentation);
            $deleted++;

            if ($deleted % 100 === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        if (!$isDryRun) {
            $this->em->flush();
            $this->em->clear();
        }

        if ($samples !== []) {
            $io->section('Sample matches');
            $io->listing($samples);
        }

        $io->success(sprintf(
            'Done. Processed: %d, matched: %d, deleted: %d%s',
            $processed,
            $matched,
            $deleted,
            $isDryRun ? ' (dry-run)' : ''
        ));

        return Command::SUCCESS;
    }

    private function containsCovidReference(PPBase $presentation): bool
    {
        $fields = [
            (string) ($presentation->getTitle() ?? ''),
            (string) ($presentation->getGoal() ?? ''),
            (string) ($presentation->getKeywords() ?? ''),
            (string) ($presentation->getTextDescription() ?? ''),
        ];

        foreach ($fields as $value) {
            if ($value !== '' && preg_match(self::COVID_REGEX, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}

