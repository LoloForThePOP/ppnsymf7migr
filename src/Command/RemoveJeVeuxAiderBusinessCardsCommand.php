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
    name: 'app:cleanup:remove-jva-business-cards',
    description: 'Remove business cards from JeVeuxAider imported presentations (privacy cleanup).'
)]
final class RemoveJeVeuxAiderBusinessCardsCommand extends Command
{
    private const JVA_WEBSITE_TITLE = 'Page JeVeuxAider.gouv';
    private const JVA_WEBSITE_TITLE_WITH_CONTACT = 'Page JeVeuxAider.gouv (infos de contact)';

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
                'Preview impacted rows without writing to database.'
            )
            ->addOption(
                'annotate-website',
                null,
                InputOption::VALUE_NEGATABLE,
                'Annotate "Page JeVeuxAider.gouv" as "(infos de contact)" when a removed business card had phone/email.',
                true
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = (bool) $input->getOption('dry-run');
        $annotateWebsite = (bool) $input->getOption('annotate-website');

        $io->title('JeVeuxAider business cards cleanup');
        $io->text($isDryRun ? 'Mode: dry-run (no DB write)' : 'Mode: apply');
        $io->text(sprintf('Annotate website: %s', $annotateWebsite ? 'yes' : 'no'));

        $query = $this->em->createQueryBuilder()
            ->select('p')
            ->from(PPBase::class, 'p')
            ->where('p.ingestion.sourceUrl LIKE :host')
            ->setParameter('host', '%jeveuxaider.gouv.fr%')
            ->orderBy('p.id', 'ASC')
            ->getQuery();

        $processed = 0;
        $updatedPresentations = 0;
        $removedCards = 0;
        $annotatedWebsites = 0;

        foreach ($query->toIterable() as $presentation) {
            if (!$presentation instanceof PPBase) {
                continue;
            }

            $processed++;
            $otherComponents = $presentation->getOtherComponents();
            $raw = $otherComponents->getRaw();

            $businessCards = $raw['business_cards'] ?? null;
            if (!is_array($businessCards) || $businessCards === []) {
                continue;
            }

            $hadContactInfos = $this->businessCardsHavePhoneOrEmail($businessCards);
            $removedCards += count($businessCards);
            unset($raw['business_cards']);
            $wasChanged = true;

            if ($annotateWebsite && $hadContactInfos && isset($raw['websites']) && is_array($raw['websites'])) {
                $websiteWasAnnotated = $this->annotateJeVeuxAiderWebsite($raw['websites']);
                if ($websiteWasAnnotated) {
                    $annotatedWebsites++;
                }
            }

            if ($wasChanged) {
                $otherComponents->setRaw($raw);
                $presentation->setOtherComponents($otherComponents);
                $updatedPresentations++;
            }

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
            'Done. Processed: %d, updated presentations: %d, removed business cards: %d, annotated websites: %d%s',
            $processed,
            $updatedPresentations,
            $removedCards,
            $annotatedWebsites,
            $isDryRun ? ' (dry-run)' : ''
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, mixed> $businessCards
     */
    private function businessCardsHavePhoneOrEmail(array $businessCards): bool
    {
        foreach ($businessCards as $card) {
            if (!is_array($card)) {
                continue;
            }

            $tel = is_string($card['tel1'] ?? null) ? trim((string) $card['tel1']) : '';
            $email = is_string($card['email1'] ?? null) ? trim((string) $card['email1']) : '';
            if ($tel !== '' || $email !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $websites
     */
    private function annotateJeVeuxAiderWebsite(array &$websites): bool
    {
        $updated = false;
        foreach ($websites as $index => $website) {
            if (!is_array($website)) {
                continue;
            }

            $title = is_string($website['title'] ?? null) ? trim((string) $website['title']) : '';
            if ($title === '') {
                continue;
            }

            if ($title === self::JVA_WEBSITE_TITLE_WITH_CONTACT) {
                continue;
            }

            if ($title === self::JVA_WEBSITE_TITLE) {
                $websites[$index]['title'] = self::JVA_WEBSITE_TITLE_WITH_CONTACT;
                $updated = true;
            }
        }

        return $updated;
    }
}

