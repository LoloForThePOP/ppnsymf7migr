<?php

namespace App\Command;

use App\Entity\Category;
use App\Enum\CategoryList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:categories',
    description: 'Synchronize the Category table with the CategoryList enum safely (creates/updates, never deletes).'
)]
class SeedCategoriesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repo = $this->em->getRepository(Category::class);

        $io->section('Starting category synchronization…');

        $created = 0;
        $updated = 0;

        foreach (CategoryList::cases() as $case) {
            $category = $repo->findOneBy(['uniqueName' => $case->value]);

            if (!$category) {
                $category = new Category();
                $category->setUniqueName($case->value);
                $this->em->persist($category);
                $created++;
            } else {
                $updated++;
            }

            $category
                ->setLabel($case->label())
                ->setPosition($case->position())
                ->setImage($case->icon());
        }

        $this->em->flush();

        $io->success(sprintf(
            '✅ %d categories synchronized (%d created, %d updated).',
            $created + $updated,
            $created,
            $updated
        ));

        return Command::SUCCESS;
    }
}
