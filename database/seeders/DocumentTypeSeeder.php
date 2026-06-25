<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            /* ── Documents communs aux deux types ──────────────────── */
            [
                'code'          => 'acte_naissance',
                'label'         => 'Acte de Naissance',
                'type_etudiant' => 'both',
                'is_required'   => true,
                'has_stamp'     => false,
                'order'         => 1,
                'description'   => 'Acte de naissance original ou copie certifiée conforme',
            ],
            [
                'code'          => 'nationalite',
                'label'         => 'Certificat de Nationalité',
                'type_etudiant' => 'both',
                'is_required'   => true,
                'has_stamp'     => false,
                'order'         => 2,
                'description'   => 'Document officiel attestant de la nationalité',
            ],
            [
                'code'          => 'photo_identite',
                'label'         => 'Photo d\'Identité',
                'type_etudiant' => 'both',
                'is_required'   => true,
                'has_stamp'     => false,
                'order'         => 3,
                'description'   => 'Photo d\'identité récente (format passeport)',
            ],

            /* ── Nouveaux bacheliers uniquement ─────────────────────── */
            [
                'code'          => 'releve_bac',
                'label'         => 'Relevé de Notes du Baccalauréat',
                'type_etudiant' => 'nouveau',
                'is_required'   => true,
                'has_stamp'     => true,   // ← tamponné à la validation
                'order'         => 4,
                'description'   => 'Relevé de notes officiel du baccalauréat',
            ],
            [
                'code'          => 'fiche_inscription_nouveau',
                'label'         => 'Fiche d\'Inscription',
                'type_etudiant' => 'nouveau',
                'is_required'   => true,
                'has_stamp'     => true,   // ← tamponné à la validation
                'order'         => 5,
                'description'   => 'Fiche d\'inscription universitaire remplie et signée',
            ],
            [
                'code'          => 'fiche_paiement_nouveau',
                'label'         => 'Fiche de Paiement',
                'type_etudiant' => 'nouveau',
                'is_required'   => true,
                'has_stamp'     => false,
                'order'         => 6,
                'description'   => 'Reçu de paiement des frais de dossier',
            ],

            /* ── Anciens étudiants uniquement ───────────────────────── */
            [
                'code'          => 'carte_etudiant',
                'label'         => 'Carte Étudiant',
                'type_etudiant' => 'ancien',
                'is_required'   => true,
                'has_stamp'     => false,
                'order'         => 4,
                'description'   => 'Carte étudiant de la dernière année d\'inscription',
            ],
            [
                'code'          => 'releve_notes',
                'label'         => 'Relevé de Notes',
                'type_etudiant' => 'ancien',
                'is_required'   => true,
                'has_stamp'     => true,   // ← tamponné à la validation
                'order'         => 5,
                'description'   => 'Relevé de notes officiel de la dernière année',
            ],
            [
                'code'          => 'attestation_reussite',
                'label'         => 'Attestation de Réussite / Inscription',
                'type_etudiant' => 'ancien',
                'is_required'   => true,
                'has_stamp'     => true,   // ← tamponné à la validation
                'order'         => 6,
                'description'   => 'Attestation officielle de réussite ou d\'inscription',
            ],
            [
                'code'          => 'fiche_paiement_ancien',
                'label'         => 'Fiche de Paiement',
                'type_etudiant' => 'ancien',
                'is_required'   => true,
                'has_stamp'     => false,
                'order'         => 7,
                'description'   => 'Reçu de paiement des frais de dossier',
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
