<?php

namespace App\Service;

use App\Entity\PPBase;

/**
 * Evaluates the content completeness (quality) of a project presentation (the score increases when the user adds meaningful content), among other signals (likes, followers, views...), in order to give project presentation a score, in order to compare project presentations.
 * 
 */
class AssessPPScoreService
{

    /**
     * Compute and assign a score (integer) to a PPBase presentation.
     */
    public function scoreUpdate(PPBase $presentation): void
    {
        $score = 0;

        // Text description (or any written content)
        $text = trim((string) $presentation->getTextDescription());
        if ($text !== '') {
            $score++;
        }

        // Slides
        if (count($presentation->getSlides() ?? []) > 0) {
            $score++;
        }

        $presentation->setScore($score);
    }

}
