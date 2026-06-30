<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Extension Twig pour formater les dates avec le fuseau horaire de Paris
 */
class DateTimeExtension extends AbstractExtension
{
    private const TIMEZONE = 'Europe/Paris';

    public function getFilters(): array
    {
        return [
            new TwigFilter('paris_date', [$this, 'formatParisDate']),
            new TwigFilter('paris_datetime', [$this, 'formatParisDateTime']),
            new TwigFilter('paris_time', [$this, 'formatParisTime']),
            new TwigFilter('relative_time', [$this, 'formatRelativeTime']),
        ];
    }

    /**
     * Formate une date au format Paris (dd/mm/yyyy)
     */
    public function formatParisDate(\DateTimeInterface|string|null $date, string $format = 'd/m/Y'): string
    {
        if ($date === null) {
            return '';
        }

        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        $parisDate = clone $date;
        if ($parisDate instanceof \DateTime) {
            $parisDate->setTimezone(new \DateTimeZone(self::TIMEZONE));
        }

        return $parisDate->format($format);
    }

    /**
     * Formate une date et heure au format Paris (dd/mm/yyyy HH:mm)
     */
    public function formatParisDateTime(\DateTimeInterface|string|null $date, string $format = 'd/m/Y à H:i'): string
    {
        return $this->formatParisDate($date, $format);
    }

    /**
     * Formate une heure au format Paris (HH:mm)
     */
    public function formatParisTime(\DateTimeInterface|string|null $date, string $format = 'H:i'): string
    {
        return $this->formatParisDate($date, $format);
    }

    /**
     * Formate une date en temps relatif ("il y a 2 heures", etc.)
     */
    public function formatRelativeTime(\DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return '';
        }

        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        $now = new \DateTime('now', new \DateTimeZone(self::TIMEZONE));
        
        if ($date instanceof \DateTime) {
            $date = clone $date;
            $date->setTimezone(new \DateTimeZone(self::TIMEZONE));
        }

        $diff = $now->diff($date);

        if ($diff->invert === 0) {
            // Futur
            if ($diff->y > 0) {
                return 'dans ' . $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
            }
            if ($diff->m > 0) {
                return 'dans ' . $diff->m . ' mois';
            }
            if ($diff->d > 0) {
                return 'dans ' . $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
            }
            if ($diff->h > 0) {
                return 'dans ' . $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
            }
            if ($diff->i > 0) {
                return 'dans ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
            }
            return 'dans quelques secondes';
        }

        // Passé
        if ($diff->y > 0) {
            return 'il y a ' . $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            return 'il y a ' . $diff->m . ' mois';
        }
        if ($diff->d > 0) {
            if ($diff->d === 1) {
                return 'hier';
            }
            return 'il y a ' . $diff->d . ' jours';
        }
        if ($diff->h > 0) {
            return 'il y a ' . $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            return 'il y a ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
        return 'à l\'instant';
    }
}

