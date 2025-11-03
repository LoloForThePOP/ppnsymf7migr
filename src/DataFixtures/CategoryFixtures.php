<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Enum\CategoryList;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach (CategoryList::cases() as $case) {

            // Try to find existing Category by its unique name
            $category = $manager->getRepository(Category::class)
                ->findOneBy(['uniqueName' => $case->value]);

            if (!$category) {
                $category = new Category();
            }

            $category
                ->setUniqueName($case->value)
                ->setLabel($case->label())
                ->setPosition($case->position())
                ->setImage($case->icon());

            $manager->persist($category);
        }

        $manager->flush();
    }
}
