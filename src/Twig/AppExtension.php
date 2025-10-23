<?php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('formattedElapsedTime', [$this, 'formatElapsedTime']),
            new TwigFilter('truncate', [$this, 'truncate']),
        ];
    }



    public function truncate(string $text, int $length = 15): string
    {
        // safely handle multibyte characters (UTF-8)
        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length) . '…'
            : $text;
    }



    public function formatElapsedTime($createdAt)
    {
        $currentDate = new \DateTime();
        $interval = $currentDate->diff($createdAt);
        $stringEnd=""; //french plurals management
        if ($interval->days < 1) {
            $hours = $interval->h + $interval->i / 60;

            if ($hours < 1) {
                return $interval->format('moins d\'une heure');
            } else {
                $stringEnd="s";
                return $interval->format('%h heure'.$stringEnd);
            }
        } elseif ($interval->days < 30) {
            if ($interval->days >1) {
                $stringEnd="s";
            }
            return $interval->format('%a jour'.$stringEnd);
        } elseif ($interval->days < 365) {
            return $interval->format('%m mois');
        } else {
            if ($interval->y > 1) {
                $stringEnd="s";
            }
            return $interval->format('%y an'.$stringEnd);
        }
    }
}