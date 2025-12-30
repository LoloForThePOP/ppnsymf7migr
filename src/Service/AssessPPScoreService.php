<?php

namespace App\Service;

use App\Entity\PPBase;

/**
 * Evaluates the content completeness (quality) of a project presentation (the score increases when the user adds meaningful content), among other signals (likes, followers, views...), in order to give project presentation a score, in order to compare project presentations.
 * 
 */
class AssessPPScoreService
{

    private const DESCRIPTION_TIERS = [
        80 => 2,
        200 => 2,
        500 => 2,
    ];

    private const GOAL_TIERS = [
        40 => 1,
        80 => 1,
    ];

    private const STATUS_REMARKS_MIN = 30;

    /**
     * Compute and assign a score (integer) to a PPBase presentation.
     */
    public function scoreUpdate(PPBase $presentation): void
    {
        $score = 0;

        $goalLength = $this->length($presentation->getGoal());
        if ($goalLength > 0) {
            $score += 2;
            $score += $this->pointsForTiers($goalLength, self::GOAL_TIERS);
        }

        if ($this->length($presentation->getTitle()) > 0) {
            $score += 3;
        }

        $descriptionLength = $this->length($presentation->getTextDescription());
        if ($descriptionLength > 0) {
            $score += 2;
            $score += $this->pointsForTiers($descriptionLength, self::DESCRIPTION_TIERS);
        }

        if ($presentation->getLogo()) {
            $score += 2;
        }

        if ($presentation->getCustomThumbnail()) {
            $score += 1;
        }

        if ($presentation->getCategories()->count() > 0) {
            $score += 2;
        }

        if ($this->length($presentation->getKeywords()) > 0) {
            $score += 1;
        }

        if (is_array($presentation->getStatuses()) && $presentation->getStatuses() !== []) {
            $score += 1;
        }

        if ($this->length($presentation->getStatusRemarks()) >= self::STATUS_REMARKS_MIN) {
            $score += 1;
        }

        $slidesCount = $presentation->getSlides()->count();
        if ($slidesCount > 0) {
            $score += 2;
            if ($slidesCount >= 3) {
                $score += 1;
            }
            if ($slidesCount >= 5) {
                $score += 1;
            }
        }

        $documentsCount = $presentation->getDocuments()->count();
        if ($documentsCount > 0) {
            $score += 1;
            if ($documentsCount >= 3) {
                $score += 1;
            }
        }

        $newsCount = $presentation->getNews()->count();
        if ($newsCount > 0) {
            $score += 1;
            if ($newsCount >= 3) {
                $score += 1;
            }
        }

        if ($presentation->getNeeds()->count() > 0) {
            $score += 1;
        }

        if ($presentation->getPlaces()->count() > 0) {
            $score += 1;
        }

        if (count($presentation->getOtherComponents()->getComponents('websites')) > 0) {
            $score += 1;
        }

        if (count($presentation->getOtherComponents()->getComponents('questions_answers')) > 0) {
            $score += 1;
        }

        if (count($presentation->getOtherComponents()->getComponents('business_cards')) > 0) {
            $score += 1;
        }

        $followersCount = $presentation->getFollowerCount();
        if ($followersCount >= 1) {
            $score += 1;
        }
        if ($followersCount >= 5) {
            $score += 1;
        }
        if ($followersCount >= 20) {
            $score += 1;
        }

        $likesCount = $presentation->getLikesCount();
        if ($likesCount >= 1) {
            $score += 1;
        }
        if ($likesCount >= 5) {
            $score += 1;
        }
        if ($likesCount >= 20) {
            $score += 1;
        }

        $presentation->setScore($score);
    }

    private function length(?string $value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    /**
     * @param array<int, int> $tiers
     */
    private function pointsForTiers(int $length, array $tiers): int
    {
        $points = 0;
        foreach ($tiers as $threshold => $bonus) {
            if ($length >= $threshold) {
                $points += $bonus;
            }
        }

        return $points;
    }
}
