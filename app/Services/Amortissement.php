<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Calcul du plan d'amortissement d'une immobilisation.
 *  - Linéaire : prorata temporis en jours la 1re année.
 *  - Dégressif : coefficient fiscal (1,25 / 1,75 / 2,25), prorata en mois,
 *    bascule en linéaire dès que l'annuité linéaire devient supérieure.
 */
final class Amortissement
{
    public static function coefficient(int $duree): float
    {
        return match (true) {
            $duree <= 4 => 1.25,
            $duree <= 6 => 1.75,
            default     => 2.25,
        };
    }

    /**
     * @return array{methode:string,taux:float,base:float,lignes:list<array{annee:int,vnc_debut:float,taux:float,dotation:float,cumul:float,vnc:float}>}
     */
    public static function plan(float $valeur, float $residuelle, int $duree, string $methode, string $dateDebut): array
    {
        $duree = max(1, $duree);
        $base  = max(0.0, $valeur - $residuelle);
        $start = new \DateTimeImmutable($dateDebut);
        $year  = (int) $start->format('Y');
        $lignes = [];

        if ($methode === 'degressif' && $duree >= 3) {
            $taux   = round(100 / $duree * self::coefficient($duree), 2);
            $months = 13 - (int) $start->format('n');          // mois restants (mois d'acquisition inclus)
            $prorata = $months / 12;
            $vnc = $base;
            $cumul = 0.0;
            $restant = (float) $duree;                          // durée restante (années)
            for ($i = 0; $vnc > 0.004 && $i < 100; $i++) {
                $p = $i === 0 ? $prorata : 1.0;
                $deg = $vnc * $taux / 100 * $p;
                $lin = $restant > 0 ? $vnc / $restant * $p : $vnc;
                $dot = ($restant <= $p || $lin >= $deg) ? min($vnc, $lin) : $deg;
                if ($restant <= 1 && $i > 0) {
                    $dot = $vnc;
                }
                $dot = round($dot, 2);
                $tauxLigne = $vnc > 0 ? round($dot / $vnc * 100, 2) : 0;
                $cumul += $dot;
                $lignes[] = self::ligne($year + $i, $vnc + $residuelle, $tauxLigne, $dot, $cumul, $base - $cumul + $residuelle);
                $vnc = round($base - $cumul, 2);
                $restant -= $p;
            }
            return ['methode' => 'degressif', 'taux' => $taux, 'base' => $base, 'lignes' => $lignes];
        }

        // Linéaire
        $taux = round(100 / $duree, 2);
        $annuite = $base / $duree;
        $endOfYear = new \DateTimeImmutable($year . '-12-31');
        $daysInYear = (int) $start->format('L') ? 366 : 365;
        $prorata = ((int) $start->diff($endOfYear)->days + 1) / $daysInYear;
        $cumul = 0.0;
        $n = $prorata < 0.9999 ? $duree + 1 : $duree;
        for ($i = 0; $i < $n; $i++) {
            $vncDebut = $base - $cumul;
            $dot = $i === 0 ? $annuite * $prorata : $annuite;
            if ($i === $n - 1 || $dot > $vncDebut) {
                $dot = $vncDebut;                               // solde la dernière année
            }
            $dot = round($dot, 2);
            $cumul = round($cumul + $dot, 2);
            $lignes[] = self::ligne($year + $i, $vncDebut + $residuelle, $taux, $dot, $cumul, $base - $cumul + $residuelle);
        }
        return ['methode' => 'lineaire', 'taux' => $taux, 'base' => $base, 'lignes' => $lignes];
    }

    /** Cumul des amortissements et VNC au 31/12 de l'année de référence. */
    public static function situation(array $plan, float $valeur, ?int $annee = null): array
    {
        $annee ??= (int) date('Y');
        $cumul = 0.0;
        $dotation = 0.0;
        foreach ($plan['lignes'] as $l) {
            if ($l['annee'] <= $annee) {
                $cumul = $l['cumul'];
            }
            if ($l['annee'] === $annee) {
                $dotation = $l['dotation'];
            }
        }
        return ['annee' => $annee, 'dotation' => $dotation, 'cumul' => $cumul, 'vnc' => round($valeur - $cumul, 2)];
    }

    private static function ligne(int $annee, float $vncDebut, float $taux, float $dot, float $cumul, float $vnc): array
    {
        return [
            'annee'     => $annee,
            'vnc_debut' => round($vncDebut, 2),
            'taux'      => $taux,
            'dotation'  => $dot,
            'cumul'     => round($cumul, 2),
            'vnc'       => round(max(0, $vnc), 2),
        ];
    }
}
