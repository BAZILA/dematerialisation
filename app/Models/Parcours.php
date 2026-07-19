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
            $g['label'] = $g['annee'] . ' (' . implode(' & ', $g['semestres']) . ')';
            return $g;
        }, $groupes));
    }
}
