<?php

namespace Database\Seeders;

use App\Models\Procedure;
use Illuminate\Database\Seeder;

class ProcedureSeeder extends Seeder
{
    public function run(): void
    {
        // [code, nom, requires_auth, pour_nouveaux, pour_anciens, ordre]
        $procedures = [
            ['depot', 'Dépôt de dossier', true, true, true, 1],
            ['attestation', "Demande d'attestation", true, false, true, 2],
            ['diplome', 'Demande de diplôme', true, false, true, 3],
            ['releve', 'Demande de relevé de notes', true, false, true, 4],
            ['duplicat', 'Duplicata de carte étudiant', true, false, true, 5],
            ['homologation', "Demande d'homologation", true, false, true, 6],
            ['candidature', 'Candidature (admission)', false, false, false, 7],
        ];

        foreach ($procedures as [$code, $nom, $auth, $nouv, $anc, $ordre]) {
            Procedure::firstOrCreate(
                ['code' => $code],
                ['nom' => $nom, 'requires_auth' => $auth, 'pour_nouveaux' => $nouv, 'pour_anciens' => $anc, 'ordre' => $ordre]
            );
        }
    }
}
