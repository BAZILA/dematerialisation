<?php

use App\Models\Parcours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Passage « semestre » → « niveau » (année d'études) : les dépôts se font
 * désormais par année (A1 = S1 & S2, A2 = S3 & S4, …), pas par semestre.
 * Convertit les valeurs héritées des dépôts et des périmètres agents.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Dépôts : S1/S2 → A1, S3/S4 → A2, « Année 2 » → A2 ──
        foreach (DB::table('student_requests')->whereNotNull('semestre')->get(['id', 'semestre']) as $row) {
            $niveau = Parcours::niveauDepuisSemestre($row->semestre);
            if ($niveau && $niveau !== $row->semestre) {
                DB::table('student_requests')->where('id', $row->id)->update(['semestre' => $niveau]);
            }
        }

        // ── Périmètres agents : même conversion + dédoublonnage ──
        //    (S1 et S2 d'un même agent×parcours fusionnent en un seul A1)
        foreach (DB::table('agent_perimetres')->whereNotNull('semestre')->get() as $row) {
            $niveau = Parcours::niveauDepuisSemestre($row->semestre);
            if (!$niveau || $niveau === $row->semestre) {
                continue;
            }
            $doublon = DB::table('agent_perimetres')
                ->where('user_id', $row->user_id)
                ->where('parcours_id', $row->parcours_id)
                ->where('semestre', $niveau)
                ->where('id', '<>', $row->id)
                ->exists();
            if ($doublon) {
                DB::table('agent_perimetres')->where('id', $row->id)->delete();
            } else {
                DB::table('agent_perimetres')->where('id', $row->id)->update(['semestre' => $niveau]);
            }
        }
    }

    public function down(): void
    {
        // Conversion à sens unique (les A-codes restent valides à l'affichage).
    }
};
