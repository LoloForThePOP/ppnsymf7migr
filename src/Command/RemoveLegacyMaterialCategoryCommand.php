<?php

namespace App\Command;

use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup:material-category',
    description: 'Remove legacy "material" category when it is no longer used.'
)]
class RemoveLegacyMaterialCategoryCommand extends Command
{
    private const LEGACY_MATERIAL = 'material';

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

        $material = $this->categoryRepository->findOneBy(['uniqueName' => self::LEGACY_MATERIAL]);
        if (!$material) {
            $io->success('Legacy "material" category already removed.');
            return Command::SUCCESS;
        }

        $usageCount = $material->getProjectPresentation()->count();
        if ($usageCount > 0) {
            $io->error(sprintf(
                'Legacy "material" category is still linked to %d presentation(s).',
                $usageCount
            ));
            $io->text('Run app:migrate:material-categories first, then retry.');
            return Command::FAILURE;
        }

        if (!$dryRun) {
            $this->em->remove($material);
            $this->em->flush();
        }

        $io->success('Legacy "material" category removed.');

        return Command::SUCCESS;
    }
}
