<?php

namespace App\Imports;

use App\Models\Bachelier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BacheliersImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $idx => $row) {
            $line = $idx + 2; // +1 en-tête, +1 index base 1

            $nom    = trim((string) ($row['nom'] ?? ''));
            $prenom = trim((string) ($row['prenom'] ?? ''));
            // Les deux formats : celui du modèle, et celui de l'extraction
            // de l'autre système (« code_sexe », « code_serie »…).
            $sexe   = strtoupper(trim((string) ($row['sexe'] ?? $row['code_sexe'] ?? '')));
            $date   = $this->parseDate($row['date_naissance'] ?? null);
            $lieu   = trim((string) ($row['lieu_naissance'] ?? ''));

            if ($nom === '' && $prenom === '' && $row->filter()->isEmpty()) {
                continue; // ligne vide
            }
            if ($nom === '' || $prenom === '' || $lieu === '' || !$date || !in_array($sexe, ['M', 'F'])) {
                $this->skipped++;
                $this->errors[] = "Ligne $line : champs obligatoires manquants/invalides (nom, prénom, sexe M/F, date de naissance, lieu de naissance).";
                continue;
            }

            $data = [
                'id_source'      => $this->s($row['id_source'] ?? $row['id'] ?? null),
                'numero_suivi'   => $this->s($row['numero_suivi'] ?? $row['suivi'] ?? null),
                'nom'            => $nom,
                'prenom'         => $prenom,
                'sexe'           => $sexe,
                'date_naissance' => $date,
                'lieu_naissance' => $lieu,
                'serie_bac'      => $this->s($row['serie_bac'] ?? $row['code_serie'] ?? null),
                'mention_bac'    => $this->s($row['mention_bac'] ?? $row['code_mention_bac'] ?? null),
                'annee_bac'      => $this->s($row['annee_bac'] ?? $row['annee'] ?? null),
                'numero_releve'  => $this->s($row['numero_releve'] ?? null),
                'numero_table'   => $this->s($row['numero_table'] ?? $row['n_table'] ?? null),
                'academie'       => $this->s($row['academie'] ?? null),
                'telephone'      => $this->s($row['telephone'] ?? $row['tel'] ?? null),
                'email'          => $this->s($row['email'] ?? null),
                'adresse'        => $this->s($row['adresse'] ?? null),
            ];
            foreach ($data as $k => $v) {
                if ($v !== null && strtoupper($v) === 'NULL') {
                    $data[$k] = null;   // « NULL » écrit en toutes lettres par l'extraction
                }
            }

            /* Dédoublonnage.
               On interroge TOUS les identifiants présents, pas seulement le
               premier trouvé : un second fichier qui ajoute le n° de suivi à
               une fiche déjà importée par n° de relevé aurait sinon créé un
               doublon, la recherche portant sur une clé encore vide en base. */
            $b = null;
            foreach (['id_source', 'numero_suivi', 'numero_releve'] as $k) {
                if ($data[$k]) {
                    $b = Bachelier::where($k, $data[$k])->first();
                    if ($b) {
                        break;
                    }
                }
            }

            /* Le n° de TABLE n'identifie personne à lui seul : il est
               réattribué à chaque session du bac. Depuis que le référentiel
               porte toutes les promotions, le chercher seul retrouvait le
               bachelier d'une autre année — et l'écrasait. Il ne sert
               qu'accompagné de l'année. */
            if (!$b && $data['numero_table'] && $data['annee_bac']) {
                $b = Bachelier::where('numero_table', $data['numero_table'])
                    ->where('annee_bac', $data['annee_bac'])->first();
            }

            /* Aucun identifiant sur la ligne : sans repli, réimporter le même
               fichier dupliquerait toute la promotion. L'identité civile fait
               alors office de clé. */
            if (!$b) {
                // whereDate : la colonne peut porter une heure selon le
                // moteur, une comparaison brute manquerait alors la fiche.
                $b = Bachelier::where('nom', $nom)->where('prenom', $prenom)
                    ->whereDate('date_naissance', $date)->first();
            }

            $existed = (bool) $b;
            $b = $b ?: new Bachelier();

            /* Une colonne absente du fichier ne doit rien effacer : « mettre à
               jour » n'est pas « remplacer ». Sur une fiche existante on ne
               reprend que les valeurs réellement fournies. */
            $b->fill($existed ? array_filter($data, fn ($v) => $v !== null) : $data);

            if (!$existed) {
                $b->is_registered = false;
            }
            $b->save();
            $existed ? $this->updated++ : $this->created++;
        }
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
