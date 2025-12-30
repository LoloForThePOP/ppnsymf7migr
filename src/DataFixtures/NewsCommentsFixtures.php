<?php

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\News;
use App\Entity\PPBase;
use App\Entity\User;
use App\Enum\CommentStatus;
use App\Factory\PPBaseFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class NewsCommentsFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');
        $projectRoot = \dirname(__DIR__, 2);
        $imageSourceDir = $projectRoot . '/public/media/static/images/larger/test';
        $imageUploadDir = $projectRoot . '/public/media/uploads/news';
        $availableImages = [];

        if (is_dir($imageSourceDir)) {
            $availableImages = glob($imageSourceDir . '/*.{jpg,jpeg,png,webp,avif}', GLOB_BRACE) ?: [];
        }
        if ($availableImages && !is_dir($imageUploadDir)) {
            mkdir($imageUploadDir, 0775, true);
        }

        $ppRepo = $manager->getRepository(PPBase::class);
        $userRepo = $manager->getRepository(User::class);

        $projects = $ppRepo->findBy([], null, 6);
        if (!$projects) {
            PPBaseFactory::createMany(3);
            $manager->flush();
            $projects = $ppRepo->findBy([], null, 3);
        }

        $users = $userRepo->findAll();
        if (!$users) {
            UserFactory::createMany(8);
            $manager->flush();
            $users = $userRepo->findAll();
        }

        foreach ($projects as $project) {
            $newsCount = random_int(2, 4);

            for ($i = 0; $i < $newsCount; $i++) {
                $news = (new News())
                    ->setProject($project)
                    ->setTextContent($faker->paragraphs(random_int(1, 3), true));

                if ($availableImages) {
                    shuffle($availableImages);
                    $imageCount = min(random_int(1, 3), count($availableImages));
                    $selectedImages = array_slice($availableImages, 0, $imageCount);

                    foreach ($selectedImages as $index => $sourcePath) {
                        $fileName = basename($sourcePath);
                        $targetPath = $imageUploadDir . '/' . $fileName;

                        if (!is_file($targetPath)) {
                            copy($sourcePath, $targetPath);
                        }

                        if ($index === 0) {
                            $news->setImage1($fileName);
                        } elseif ($index === 1) {
                            $news->setImage2($fileName);
                        } else {
                            $news->setImage3($fileName);
                        }
                    }
                }

                $manager->persist($news);

                $commentCount = random_int(2, 5);
                for ($j = 0; $j < $commentCount; $j++) {
                    $author = $users[array_rand($users)];

                    $comment = (new Comment())
                        ->setNews($news)
                        ->setCreator($author)
                        ->setStatus(CommentStatus::Approved)
                        ->setContent($faker->sentence(random_int(8, 14)));

                    $manager->persist($comment);

                    if (random_int(0, 1) === 1) {
                        $replyAuthor = $users[array_rand($users)];
                        $reply = (new Comment())
                            ->setNews($news)
                            ->setCreator($replyAuthor)
                            ->setStatus(CommentStatus::Approved)
                            ->setContent($faker->sentence(random_int(6, 12)))
                            ->setRepliedUser($author);

                        $comment->addReply($reply);
                        $manager->persist($reply);
                    }
                }
            }
        }

        $manager->flush();
    }
}
