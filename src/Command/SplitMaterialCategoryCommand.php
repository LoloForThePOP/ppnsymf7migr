<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\PPBase;
use App\Enum\CategoryList;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:migrate:material-categories',
    description: 'Split legacy "material" category into fabrication/construction with a review list for ambiguous items.'
)]
class SplitMaterialCategoryCommand extends Command
{
    private const LEGACY_MATERIAL = 'material';
    private const FABRICATION_TERMS = [
        'fabrication', 'fabriquer', 'atelier', 'artisan', 'artisanat', 'prototype',
        'prototypage', 'objet', 'objets', 'produit', 'produits', 'impression 3d',
        'assemblage', 'conception', 'creation', 'maker', 'fablab', 'menuiserie',
        'ebenisterie', 'bijou', 'bijoux', 'ceramique', 'couture', 'textile',
        'tissage', 'moulage', 'soudure', 'forge', 'metal', 'metallerie', 'sculpture',
    ];

    private const CONSTRUCTION_TERMS = [
        'construction', 'construire', 'chantier', 'batiment', 'batiments', 'maconnerie',
        'toiture', 'charpente', 'plomberie', 'electricite', 'isolation', 'travaux',
        'amenagement', 'infrastructure', 'ouvrage', 'fondation', 'fondations',
        'terrassement', 'voirie', 'pont', 'route', 'reseau', 'habitat', 'logement',
        'immeuble', 'architecte', 'urbanisme', 'batir', 'gros oeuvre', 'second oeuvre',
    ];

    private const RESTORE_TERMS = [
        'restauration', 'restaurer', 'restaure', 'renovation', 'reparation',
        'rehabilitation', 'patrimoine', 'recycler', 'recyclage', 'remise en etat',
        'refection', 'conservation',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
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
            $io->warning('Aucune catégorie legacy "material" trouvée.');
            return Command::SUCCESS;
        }

        $fabrication = $this->ensureCategory(CategoryList::FABRICATION);
        $construction = $this->ensureCategory(CategoryList::CONSTRUCTION);
        $restore = $this->ensureCategory(CategoryList::RESTORE);

        $reportPath = $this->buildReportPath();
        $reportHandle = fopen($reportPath, 'wb');
        if ($reportHandle === false) {
            $io->error(sprintf('Impossible de créer le fichier de revue: %s', $reportPath));
            return Command::FAILURE;
        }

        fputcsv($reportHandle, [
            'id',
            'string_id',
            'title',
            'goal',
            'keywords',
            'score_fabrication',
            'score_construction',
            'score_restore',
            'reason',
        ]);

        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(PPBase::class, 'p')
            ->join('p.categories', 'c')
            ->andWhere('c.uniqueName = :material')
            ->setParameter('material', self::LEGACY_MATERIAL)
            ->distinct()
            ->orderBy('p.id', 'ASC');

        $total = 0;
        $migrated = 0;
        $alreadyClassified = 0;
        $ambiguous = 0;

        foreach ($qb->getQuery()->toIterable() as $presentation) {
            $total++;
            $existing = $this->indexCategories($presentation);

            if (isset($existing[$fabrication->getUniqueName()]) ||
                isset($existing[$construction->getUniqueName()]) ||
                isset($existing[$restore->getUniqueName()])) {
                if (isset($existing[$material->getUniqueName()]) && !$dryRun) {
                    $presentation->removeCategory($material);
                }
                $alreadyClassified++;
                continue;
            }

            $result = $this->classifyPresentation($presentation);
            if ($result['category'] === null) {
                $ambiguous++;
                $this->writeReviewRow($reportHandle, $presentation, $result);
                continue;
            }

            $migrated++;
            if (!$dryRun) {
                if ($result['category'] === $fabrication->getUniqueName()) {
                    $presentation->addCategory($fabrication);
                } elseif ($result['category'] === $construction->getUniqueName()) {
                    $presentation->addCategory($construction);
                } elseif ($result['category'] === $restore->getUniqueName()) {
                    $presentation->addCategory($restore);
                }

                $presentation->removeCategory($material);
            }

            if ($total % 50 === 0 && !$dryRun) {
                $this->em->flush();
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        fclose($reportHandle);

        $io->success(sprintf(
            'Traités: %d | Migrés: %d | Déjà classés: %d | Ambigus: %d',
            $total,
            $migrated,
            $alreadyClassified,
            $ambiguous
        ));
        $io->text(sprintf('Fichier de revue: %s', $reportPath));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, Category>
     */
    private function indexCategories(PPBase $presentation): array
    {
        $indexed = [];
        foreach ($presentation->getCategories() as $category) {
            $unique = $category->getUniqueName();
            if ($unique) {
                $indexed[$unique] = $category;
            }
        }

        return $indexed;
    }

    /**
     * @return array{category: string|null, scores: array<string,int>, reason: string}
     */
    private function classifyPresentation(PPBase $presentation): array
    {
        $text = $this->normalizeText(implode(' ', array_filter([
            (string) $presentation->getTitle(),
            (string) $presentation->getGoal(),
            (string) $presentation->getKeywords(),
            strip_tags((string) $presentation->getTextDescription()),
        ])));

        $scores = [
            CategoryList::FABRICATION->value => $this->scoreText($text, self::FABRICATION_TERMS),
            CategoryList::CONSTRUCTION->value => $this->scoreText($text, self::CONSTRUCTION_TERMS),
            CategoryList::RESTORE->value => $this->scoreText($text, self::RESTORE_TERMS),
        ];

        $max = max($scores);
        if ($max === 0) {
            return [
                'category' => CategoryList::FABRICATION->value,
                'scores' => $scores,
                'reason' => 'fallback_fabrication',
            ];
        }

        $top = array_keys(array_filter($scores, static fn (int $score) => $score === $max));
        if (count($top) !== 1) {
            if (in_array(CategoryList::FABRICATION->value, $top, true)) {
                return [
                    'category' => CategoryList::FABRICATION->value,
                    'scores' => $scores,
                    'reason' => 'tie_bias_fabrication:' . implode('|', $top),
                ];
            }

            return ['category' => null, 'scores' => $scores, 'reason' => 'tie:' . implode('|', $top)];
        }

        return ['category' => $top[0], 'scores' => $scores, 'reason' => 'ok'];
    }

    /**
     * @param resource $handle
     * @param array{scores: array<string,int>, reason: string} $result
     */
    private function writeReviewRow($handle, PPBase $presentation, array $result): void
    {
        fputcsv($handle, [
            $presentation->getId(),
            $presentation->getStringId(),
            $presentation->getTitle(),
            $presentation->getGoal(),
            $presentation->getKeywords(),
            $result['scores'][CategoryList::FABRICATION->value] ?? 0,
            $result['scores'][CategoryList::CONSTRUCTION->value] ?? 0,
            $result['scores'][CategoryList::RESTORE->value] ?? 0,
            $result['reason'],
        ]);
    }

    private function ensureCategory(CategoryList $case): Category
    {
        $category = $this->categoryRepository->findOneBy(['uniqueName' => $case->value]);

        if (!$category) {
            $category = new Category();
            $category->setUniqueName($case->value);
            $this->em->persist($category);
        }

        $category
            ->setLabel($case->label())
            ->setPosition($case->position())
            ->setImage($case->icon());

        return $category;
    }

    private function buildReportPath(): string
    {
        $dir = $this->projectDir . '/var/reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return sprintf('%s/material_category_review_%s.csv', $dir, date('Ymd_His'));
    }

    private function normalizeText(string $text): string
    {
        $lower = mb_strtolower($text);
        $normalized = strtr($lower, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'œ' => 'oe',
        ]);

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    /**
     * @param string[] $terms
     */
    private function scoreText(string $text, array $terms): int
    {
        $score = 0;

        foreach ($terms as $term) {
            $needle = $this->normalizeText($term);
            if ($needle === '') {
                continue;
            }
            if (str_contains($needle, ' ')) {
                $score += substr_count($text, $needle);
                continue;
            }

            $score += preg_match_all('/\\b' . preg_quote($needle, '/') . '\\b/u', $text);
        }

        return $score;
    }
}
