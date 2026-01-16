<?php

namespace App\Command;

use App\Entity\Category;
use App\Enum\CategoryList;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate:technology-category',
    description: 'Rename "technology" category to "electronics" and merge relations if needed.'
)]
class RenameTechnologyCategoryCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without persisting changes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $technology = $this->categoryRepository->findOneBy(['uniqueName' => 'technology']);
        $electronics = $this->categoryRepository->findOneBy(['uniqueName' => CategoryList::ELECTRONICS->value]);

        if (!$technology && !$electronics) {
            $io->warning('Aucune catégorie "technology" ou "electronics" trouvée.');
            return Command::SUCCESS;
        }

        if ($technology && !$electronics) {
            $io->info('Renommage direct de "technology" en "electronics".');
            if (!$dryRun) {
                $technology
                    ->setUniqueName(CategoryList::ELECTRONICS->value)
                    ->setLabel(CategoryList::ELECTRONICS->label())
                    ->setPosition(CategoryList::ELECTRONICS->position())
                    ->setImage(CategoryList::ELECTRONICS->icon());
                $this->em->flush();
            }

            $io->success('Renommage terminé.');
            return Command::SUCCESS;
        }

        if ($technology && $electronics) {
            $io->info('Fusion des relations de "technology" vers "electronics".');
            $moved = 0;

            foreach ($technology->getProjectPresentation()->toArray() as $presentation) {
                $presentation->addCategory($electronics);
                $presentation->removeCategory($technology);
                $moved++;
            }

            if (!$dryRun) {
                $electronics
                    ->setLabel(CategoryList::ELECTRONICS->label())
                    ->setPosition(CategoryList::ELECTRONICS->position())
                    ->setImage(CategoryList::ELECTRONICS->icon());
                $this->em->remove($technology);
                $this->em->flush();
            }

            $io->success(sprintf('Fusion terminée (%d présentations migrées).', $moved));
            return Command::SUCCESS;
        }

        if (!$technology && $electronics) {
            $io->info('Catégorie "electronics" déjà présente.');
            if (!$dryRun) {
                $electronics
                    ->setLabel(CategoryList::ELECTRONICS->label())
                    ->setPosition(CategoryList::ELECTRONICS->position())
                    ->setImage(CategoryList::ELECTRONICS->icon());
                $this->em->flush();
            }

            $io->success('Aucun renommage nécessaire.');
        }

        return Command::SUCCESS;
    }
}
