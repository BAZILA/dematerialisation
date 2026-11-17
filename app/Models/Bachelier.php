<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bachelier extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_source',        // identifiant dans le système d'origine — PAS le n° de suivi
        'numero_suivi',
        'nom',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'sexe',
        'serie_bac',
        'mention_bac',
        'annee_bac',
        'numero_releve',
        'numero_table',
        'academie',
        'telephone',
        'email',
        'adresse',
        'is_registered',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'is_registered'  => 'boolean',
    ];

    public function student(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Retrouve un bachelier par son identité civile, telle qu'il la
     * saisit pour se connecter.
     *
     * La DATE DE NAISSANCE est le point d'entrée : elle est indexée, et
     * ramène la recherche à une poignée de fiches. Sans cela, chaque
     * tentative de connexion comparait nom, prénom et lieu sur les
     * 170 000 lignes de la table.
     *
     * Les deux écritures de la date sont cherchées : selon le moteur, la
     * colonne la range avec ou sans heure. Et si deux fiches portent la
     * même identité — quelqu'un qui a passé le bac deux fois — la plus
     * récente l'emporte.
     */
    public function scopeParIdentite($query, ?string $nom, ?string $prenom, ?string $date, ?string $lieu, ?string $sexe)
    {
        try {
            $jour = \Carbon\Carbon::parse((string) $date)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn('date_naissance', [$jour, $jour . ' 00:00:00'])
            ->where('sexe', $sexe)
            ->whereRaw('LOWER(nom) = ?', [mb_strtolower(trim((string) $nom))])
            ->whereRaw('LOWER(prenom) = ?', [mb_strtolower(trim((string) $prenom))])
            ->whereRaw('LOWER(lieu_naissance) = ?', [mb_strtolower(trim((string) $lieu))])
            ->orderByDesc('annee_bac')
            ->orderByDesc('id');
    }

    public function getFullNameAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }
}
