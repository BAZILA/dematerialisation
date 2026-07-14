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
            $sexe   = strtoupper(trim((string) ($row['sexe'] ?? '')));
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
                'nom'            => $nom,
                'prenom'         => $prenom,
                'sexe'           => $sexe,
                'date_naissance' => $date,
                'lieu_naissance' => $lieu,
                'serie_bac'      => $this->s($row['serie_bac'] ?? null),
                'annee_bac'      => $this->s($row['annee_bac'] ?? null),
                'numero_releve'  => $this->s($row['numero_releve'] ?? null),
                'academie'       => $this->s($row['academie'] ?? null),
            ];

            if ($data['numero_releve']) {
                $b = Bachelier::firstOrNew(['numero_releve' => $data['numero_releve']]);
                $existed = $b->exists;
                $b->fill($data);
                if (!$existed) {
                    $b->is_registered = false;
                }
                $b->save();
                $existed ? $this->updated++ : $this->created++;
            } else {
                Bachelier::create($data + ['is_registered' => false]);
                $this->created++;
            }
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
