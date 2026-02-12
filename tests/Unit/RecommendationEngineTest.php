<?php

namespace App\Tests\Unit;

use App\Entity\Category;
use App\Entity\Embeddables\PPBase\Extra;
use App\Entity\PPBase;
use App\Entity\User;
use App\Repository\BookmarkRepository;
use App\Repository\FollowRepository;
use App\Repository\PPBaseRepository;
use App\Service\Recommendation\RecommendationEngine;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class RecommendationEngineTest extends TestCase
{
    public function testAnonymousRecommendationsAreNonPersonalizedAndRespectLimit(): void
    {
        $ppBaseRepository = $this->createMock(PPBaseRepository::class);
        $followRepository = $this->createMock(FollowRepository::class);
        $bookmarkRepository = $this->createMock(BookmarkRepository::class);

        $p1 = $this->buildProject(1, ['health'], 'care, aid');
        $p2 = $this->buildProject(2, ['tech'], 'future, mobility');
        $p3 = $this->buildProject(3, ['nature'], 'eco');

        $ppBaseRepository->expects(self::once())
            ->method('findLatestPublished')
            ->with(180)
            ->willReturn([$p1, $p2, $p3]);
        $ppBaseRepository->expects(self::never())
            ->method('findLatestByCreator');
        $ppBaseRepository->expects(self::once())
            ->method('getEngagementCountsForIds')
            ->with([1, 2, 3])
            ->willReturn([
                1 => ['likes' => 11, 'comments' => 5],
                2 => ['likes' => 6, 'comments' => 2],
                3 => ['likes' => 1, 'comments' => 0],
            ]);
        $followRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([1, 2, 3])
            ->willReturn([]);
        $bookmarkRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([1, 2, 3])
            ->willReturn([]);

        $engine = new RecommendationEngine($ppBaseRepository, $followRepository, $bookmarkRepository);
        $result = $engine->recommendHomepage(null, 2);

        self::assertFalse($result->isPersonalized());
        self::assertSame([1, 2], $this->extractIds($result->getItems()));
    }

    public function testPersonalizedRecommendationsUseSeedProfileSignals(): void
    {
        $viewer = new User();

        $ppBaseRepository = $this->createMock(PPBaseRepository::class);
        $followRepository = $this->createMock(FollowRepository::class);
        $bookmarkRepository = $this->createMock(BookmarkRepository::class);

        $seed = $this->buildProject(91, ['health'], 'care, solidarité');

        $matchingContent = $this->buildProject(10, ['health'], 'accompagnement');
        $highEngagementNoMatch = $this->buildProject(11, ['tech'], 'hardware');

        $ppBaseRepository->expects(self::once())
            ->method('findLatestPublishedExcludingCreator')
            ->with($viewer, 180)
            ->willReturn([$matchingContent, $highEngagementNoMatch]);
        $ppBaseRepository->expects(self::once())
            ->method('findLatestByCreator')
            ->with($viewer, 12)
            ->willReturn([$seed]);
        $ppBaseRepository->expects(self::once())
            ->method('getEngagementCountsForIds')
            ->with([10, 11])
            ->willReturn([
                10 => ['likes' => 0, 'comments' => 0],
                11 => ['likes' => 120, 'comments' => 30],
            ]);
        $followRepository->expects(self::once())
            ->method('findLatestFollowedPresentations')
            ->with($viewer, 24)
            ->willReturn([]);
        $followRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([10, 11])
            ->willReturn([]);
        $bookmarkRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([10, 11])
            ->willReturn([]);

        $engine = new RecommendationEngine($ppBaseRepository, $followRepository, $bookmarkRepository);
        $result = $engine->recommendHomepage($viewer, 2);

        self::assertTrue($result->isPersonalized());
        self::assertSame([10, 11], $this->extractIds($result->getItems()));
    }

    public function testExcludedProjectIdsAreNotReturnedNorCounted(): void
    {
        $ppBaseRepository = $this->createMock(PPBaseRepository::class);
        $followRepository = $this->createMock(FollowRepository::class);
        $bookmarkRepository = $this->createMock(BookmarkRepository::class);

        $p1 = $this->buildProject(1, ['health']);
        $p2 = $this->buildProject(2, ['health']);
        $p3 = $this->buildProject(3, ['nature']);

        $ppBaseRepository->expects(self::once())
            ->method('findLatestPublished')
            ->with(180)
            ->willReturn([$p1, $p2, $p3]);
        $ppBaseRepository->expects(self::once())
            ->method('getEngagementCountsForIds')
            ->with([1, 3])
            ->willReturn([
                1 => ['likes' => 8, 'comments' => 1],
                3 => ['likes' => 4, 'comments' => 0],
            ]);
        $followRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([1, 3])
            ->willReturn([]);
        $bookmarkRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([1, 3])
            ->willReturn([]);

        $engine = new RecommendationEngine($ppBaseRepository, $followRepository, $bookmarkRepository);
        $result = $engine->recommendHomepage(null, 6, [2]);

        self::assertSame([1, 3], $this->extractIds($result->getItems()));
        self::assertArrayNotHasKey(2, $result->getStats());
    }

    public function testDiversityCapAvoidsMonocultureWhenAlternativesExist(): void
    {
        $ppBaseRepository = $this->createMock(PPBaseRepository::class);
        $followRepository = $this->createMock(FollowRepository::class);
        $bookmarkRepository = $this->createMock(BookmarkRepository::class);

        $p1 = $this->buildProject(1, ['a']);
        $p2 = $this->buildProject(2, ['a']);
        $p3 = $this->buildProject(3, ['a']);
        $p4 = $this->buildProject(4, ['b']);

        $ppBaseRepository->expects(self::once())
            ->method('findLatestPublished')
            ->with(180)
            ->willReturn([$p1, $p2, $p3, $p4]);
        $ppBaseRepository->expects(self::once())
            ->method('getEngagementCountsForIds')
            ->with([1, 2, 3, 4])
            ->willReturn([
                1 => ['likes' => 100, 'comments' => 2],
                2 => ['likes' => 90, 'comments' => 2],
                3 => ['likes' => 80, 'comments' => 2],
                4 => ['likes' => 1, 'comments' => 0],
            ]);
        $followRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([1, 2, 3, 4])
            ->willReturn([]);
        $bookmarkRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([1, 2, 3, 4])
            ->willReturn([]);

        $engine = new RecommendationEngine($ppBaseRepository, $followRepository, $bookmarkRepository);
        $result = $engine->recommendHomepage(null, 3);

        self::assertSame([1, 2, 4], $this->extractIds($result->getItems()));
    }

    public function testConfigOverrideIsAppliedForPoolSizeAndFreshnessWeighting(): void
    {
        $ppBaseRepository = $this->createMock(PPBaseRepository::class);
        $followRepository = $this->createMock(FollowRepository::class);
        $bookmarkRepository = $this->createMock(BookmarkRepository::class);

        $oldHighlyEngaged = $this->buildProject(21, ['health'], null, 0, new \DateTimeImmutable('-200 days'));
        $newLowEngagement = $this->buildProject(22, ['health'], null, 0, new \DateTimeImmutable('-1 day'));

        $ppBaseRepository->expects(self::once())
            ->method('findLatestPublished')
            ->with(5)
            ->willReturn([$oldHighlyEngaged, $newLowEngagement]);
        $ppBaseRepository->expects(self::once())
            ->method('getEngagementCountsForIds')
            ->with([21, 22])
            ->willReturn([
                21 => ['likes' => 999, 'comments' => 10],
                22 => ['likes' => 1, 'comments' => 0],
            ]);
        $followRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([21, 22])
            ->willReturn([]);
        $bookmarkRepository->expects(self::once())
            ->method('countByPresentationIds')
            ->with([21, 22])
            ->willReturn([]);

        $engine = new RecommendationEngine(
            $ppBaseRepository,
            $followRepository,
            $bookmarkRepository,
            [
                'candidate_pool_limit' => 5,
                'weights' => [
                    'non_personalized' => [
                        'engagement' => 0.0,
                        'freshness' => 1.0,
                    ],
                ],
            ]
        );

        $result = $engine->recommendHomepage(null, 1);

        self::assertSame([22], $this->extractIds($result->getItems()));
    }

    /**
     * @param PPBase[] $items
     *
     * @return int[]
     */
    private function extractIds(array $items): array
    {
        return array_map(
            static fn (PPBase $presentation): int => (int) $presentation->getId(),
            $items
        );
    }

    /**
     * @param string[] $categories
     */
    private function buildProject(
        int $id,
        array $categories,
        ?string $keywords = null,
        int $views = 0,
        ?\DateTimeImmutable $createdAt = null
    ): PPBase {
        $project = $this->createMock(PPBase::class);
        $project->method('getId')->willReturn($id);
        $project->method('getCategories')->willReturn($this->buildCategoryCollection($categories));
        $project->method('getKeywords')->willReturn($keywords);
        $project->method('getCreatedAt')->willReturn($createdAt ?? new \DateTimeImmutable());

        $extra = new Extra();
        if ($views > 0) {
            $extra->incrementViews($views);
        }
        $project->method('getExtra')->willReturn($extra);

        return $project;
    }

    /**
     * @param string[] $uniqueNames
     */
    private function buildCategoryCollection(array $uniqueNames): ArrayCollection
    {
        $categories = new ArrayCollection();
        foreach ($uniqueNames as $uniqueName) {
            $categories->add((new Category())->setUniqueName($uniqueName));
        }

        return $categories;
    }
}
