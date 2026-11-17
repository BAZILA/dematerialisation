<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parcours extends Model
{
    protected $table = 'parcours';

    protected $fillable = ['departement_id', 'domaine_id', 'nom', 'sigle', 'grade', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Grades → semestres (paramétrable ultérieurement). */
    public static array $grades = [
        'licence'        => ['label' => 'Licence',        'semestres' => ['S1', 'S2', 'S3', 'S4', 'S5', 'S6']],
        'master'         => ['label' => 'Master',         'semestres' => ['S1', 'S2', 'S3', 'S4']],
        'doctorat'       => ['label' => 'Doctorat',       'semestres' => ['Année 1', 'Année 2', 'Année 3']],
        'specialisation' => ['label' => 'Spécialisation', 'semestres' => ['S1', 'S2']],
        'certificat'     => ['label' => 'Certificat',     'semestres' => ['S1', 'S2']],
    ];

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }

    public function domaine()
    {
        return $this->belongsTo(Domaine::class);
    }

    public function getGradeLabelAttribute(): string
    {
        return self::$grades[$this->grade]['label'] ?? $this->grade;
    }

    /** Semestres du grade de ce parcours. */
    public function semestres(): array
    {
        return self::$grades[$this->grade]['semestres'] ?? [];
    }

    /** Ordinal français court d'une année d'études (1 → « 1ère année »). */
    public static function ordinalAnnee(int $n): string
    {
        return $n === 1 ? '1ère année' : $n . 'e année';
    }

    /**
     * Année d'études correspondant à un semestre.
     *   S1/S2 → « 1ère année », S3/S4 → « 2e année », …
     *   « Année 2 » (doctorat) reste tel quel.
     */
    public static function anneeDuSemestre(?string $semestre): ?string
    {
        if (!$semestre) {
            return null;
        }
        if (preg_match('/S\s*(\d+)/i', $semestre, $m)) {
            return self::ordinalAnnee((int) ceil(((int) $m[1]) / 2));
        }
        return $semestre; // ex. « Année 1 »
    }

    /**
     * Regroupe les semestres d'un grade par année d'études.
     * Retourne : [['annee' => '1ère année', 'label' => '1ère année (S1 & S2)', 'semestres' => ['S1','S2']], …]
     */
    public static function semestresParAnnee(string $grade): array
    {
        $sems = self::$grades[$grade]['semestres'] ?? [];
        $groupes = [];
        foreach ($sems as $s) {
            $annee = self::anneeDuSemestre($s) ?? $s;
            $groupes[$annee]['annee'] = $annee;
            $groupes[$annee]['semestres'][] = $s;
        }
        return array_values(array_map(function ($g) {
            // « Année 2 (Année 2) » serait redondant (doctorat) → libellé simple
            $g['label'] = (count($g['semestres']) === 1 && $g['semestres'][0] === $g['annee'])
                ? $g['annee']
                : $g['annee'] . ' (' . implode(' & ', $g['semestres']) . ')';
            return $g;
        }, $groupes));
    }

    /* ══════════════════════════════════════════════════════════
     |  NIVEAUX (années d'études) — l'étudiant dépose pour UNE
     |  année, pas pour un semestre. Codes canoniques A1, A2, …
     ══════════════════════════════════════════════════════════ */

    /** Nombre d'années DÉROGATOIRES autorisées au-delà du grade (paramétrable). */
    public static function anneesDerogatoires(): int
    {
        return max(0, min(6, (int) Setting::get('depot.annees_derogatoires', 2)));
    }

    /** Nombre d'années normales d'un grade (licence = 3, master = 2…). */
    public static function nbAnneesNormales(string $grade): int
    {
        return count(self::semestresParAnnee($grade));
    }

    /**
     * Niveaux d'un grade : ['A1' => ['annee','label','semestres','derogatoire'], …]
     * Ex. licence → A1 = « 1ère année (S1 & S2) », A2 = « 2e année (S3 & S4) »…
     * Avec $avecDerogatoires : ajoute A4, A5… (redoublement / retard) jusqu'au
     * seuil paramétré — libellées « 4e année — dérogatoire ».
     */
    public static function niveauxParGrade(string $grade, bool $avecDerogatoires = false): array
    {
        $out = [];
        foreach (self::semestresParAnnee($grade) as $i => $g) {
            $g['derogatoire'] = false;
            $out['A' . ($i + 1)] = $g;
        }

        if ($avecDerogatoires && $out) {
            $n = count($out);
            for ($d = 1; $d <= self::anneesDerogatoires(); $d++) {
                $num = $n + $d;
                $out['A' . $num] = [
                    'annee'       => self::ordinalAnnee($num),
                    'semestres'   => [],
                    'label'       => self::ordinalAnnee($num) . ' — dérogatoire',
                    'derogatoire' => true,
                ];
            }
        }

        return $out;
    }

    /** Niveaux du grade de ce parcours. */
    public function niveaux(bool $avecDerogatoires = false): array
    {
        return self::niveauxParGrade($this->grade, $avecDerogatoires);
    }

    /** Un code niveau est-il dérogatoire pour un grade donné ? */
    public static function estDerogatoire(?string $code, ?string $grade): bool
    {
        if (!$code || !$grade || !preg_match('/^A(\d+)$/', $code, $m)) {
            return false;
        }

        return (int) $m[1] > self::nbAnneesNormales($grade);
    }

    /**
     * Liste GÉNÉRIQUE de tous les niveaux possibles (filtres archives/demandes) :
     * A1..Amax tous grades confondus, dérogatoires marqués.
     */
    public static function tousLesNiveaux(): array
    {
        $maxNormal = max(array_map(
            fn ($g) => count(self::semestresParAnnee($g)),
            array_keys(self::$grades)
        ) ?: [3]);
        $out = [];
        for ($i = 1; $i <= $maxNormal + self::anneesDerogatoires(); $i++) {
            $out['A' . $i] = self::ordinalAnnee($i) . ($i > $maxNormal ? ' (dérogatoire)' : '');
        }

        return $out;
    }

    /**
     * Libellé d'un niveau stocké — gère aussi les ANCIENNES valeurs
     * (« S3 » → « 2e année (S3) », « Année 2 » → « Année 2 »).
     */
    public static function libelleNiveau(?string $code, ?string $grade = null): ?string
    {
        if (!$code) {
            return null;
        }
        if (preg_match('/^A(\d+)$/', $code, $m)) {
            $n = (int) $m[1];
            if ($grade && ($niv = self::niveauxParGrade($grade)['A' . $n] ?? null)) {
                return $niv['label'];
            }
            if ($grade && self::estDerogatoire($code, $grade)) {
                return self::ordinalAnnee($n) . ' — dérogatoire';
            }
            return self::ordinalAnnee($n);
        }
        // Valeur héritée (semestre brut ou « Année N »)
        $annee = self::anneeDuSemestre($code);

        return $annee === $code ? $code : $annee . ' (' . $code . ')';
    }

    /** Convertit une ancienne valeur semestre en code niveau (S3 → A2, Année 2 → A2). */
    public static function niveauDepuisSemestre(?string $semestre): ?string
    {
        if (!$semestre) {
            return null;
        }
        if (preg_match('/^A\d+$/', $semestre)) {
            return $semestre; // déjà un code niveau
        }
        if (preg_match('/S\s*(\d+)/i', $semestre, $m)) {
            return 'A' . (int) ceil(((int) $m[1]) / 2);
        }
        if (preg_match('/(\d+)/', $semestre, $m)) {
            return 'A' . (int) $m[1]; // « Année 2 » → A2
        }
        return null;
    }
}
