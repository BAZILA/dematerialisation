<?php

namespace App\Imports;

use App\Models\AncienEtudiant;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AnciensImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    /** Étudiants « nouveaux » MIGRÉS vers « ancien » via le numéro de suivi. */
    public int $migres = 0;
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $idx => $row) {
            $line = $idx + 2;

            $carte  = trim((string) ($row['numero_carte'] ?? ''));
            $nom    = trim((string) ($row['nom'] ?? ''));
            $prenom = trim((string) ($row['prenom'] ?? ''));
            $sexe   = strtoupper(trim((string) ($row['sexe'] ?? '')));
            $date   = $this->parseDate($row['date_naissance'] ?? null);
            $lieu   = trim((string) ($row['lieu_naissance'] ?? ''));

            if ($carte === '' && $nom === '' && $row->filter()->isEmpty()) {
                continue;
            }
            if ($carte === '' || $nom === '' || $prenom === '' || $lieu === '' || !$date || !in_array($sexe, ['M', 'F'])) {
                $this->skipped++;
                $this->errors[] = "Ligne $line : champs obligatoires manquants/invalides (numéro de carte, nom, prénom, sexe M/F, date de naissance, lieu de naissance).";
                continue;
            }

            $data = [
                'numero_suivi'        => $this->s($row['numero_suivi'] ?? $row['suivi'] ?? null),
                'nom'                 => $nom,
                'prenom'              => $prenom,
                'sexe'                => $sexe,
                'date_naissance'      => $date,
                'lieu_naissance'      => $lieu,
                'specialite'          => $this->s($row['specialite'] ?? null),
                'derniere_universite' => $this->s($row['derniere_universite'] ?? null),
                'telephone'           => $this->s($row['telephone'] ?? $row['tel'] ?? null),
                'email'               => $this->s($row['email'] ?? null),
                'adresse'             => $this->s($row['adresse'] ?? null),
            ];

            $a = AncienEtudiant::firstOrNew(['numero_carte' => $carte]);
            $existed = $a->exists;

            /* Une colonne absente du fichier ne doit rien effacer : « mettre à
               jour » n'est pas « remplacer ». Un second fichier plus court
               aurait sinon vidé téléphone, adresse et spécialité de fiches
               déjà complètes. */
            $a->fill($existed ? array_filter($data, fn ($v) => $v !== null) : $data);
            if (!$existed) {
                $a->is_registered = false;
            }
            $a->save();
            $existed ? $this->updated++ : $this->created++;

            // ── MIGRATION NOUVEAU → ANCIEN par numéro de suivi ──
            // Un bachelier de l'an passé qui apparaît maintenant dans la liste
            // des anciens (même numero_suivi) garde SON COMPTE : on le rattache
            // à sa fiche « ancien » et son profil bascule automatiquement.
            $this->migrerNouveauVersAncien($a);
        }
    }

    /**
     * Si un étudiant inscrit comme « nouveau » (via bachelier) porte le même
     * numéro de suivi que cette fiche d'ancien : rattachement + bascule du
     * type de compte. Le compte, ses dépôts et son historique sont conservés.
     */
    private function migrerNouveauVersAncien(AncienEtudiant $a): void
    {
        if (!$a->numero_suivi) {
            return;
        }

        $student = \App\Models\Student::whereNull('ancien_etudiant_id')
            ->whereHas('bachelier', fn ($b) => $b->where('numero_suivi', $a->numero_suivi))
            ->with('user')
            ->first();

        if (!$student || !$student->user) {
            return;
        }

        $student->ancien_etudiant_id = $a->id; // bachelier_id conservé (historique)
        $student->save();

        $student->user->type_etudiant = 'ancien';
        $student->user->save();

        // La fiche ancien est déjà « prise » par ce compte : personne d'autre
        // ne peut s'inscrire avec ce numéro de carte.
        if (!$a->is_registered) {
            $a->is_registered = true;
            $a->save();
        }

        $this->migres++;

        \App\Models\AuditLog::record('etudiant.migre_ancien', $student->user,
            "Étudiant migré nouveau → ancien : {$student->nom} {$student->prenom}"
                . " (suivi {$a->numero_suivi}, carte {$a->numero_carte})",
            ['numero_suivi' => $a->numero_suivi, 'numero_carte' => $a->numero_carte]);
    }

    private function s($v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    private function parseDate($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }
        $v = trim((string) $v);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $v);
            if ($d && $d->format($fmt) === $v) {
                return $d->format('Y-m-d');
            }
        }
        try {
            return \Carbon\Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
