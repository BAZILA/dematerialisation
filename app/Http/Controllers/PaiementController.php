<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Paiement;
use App\Models\Procedure;
use App\Services\Paiement\PaiementService;
use Illuminate\Http\Request;

/**
 * Suivi et rapprochement des frais de dossier (back-office).
 */
class PaiementController extends Controller
{
    public function __construct(private PaiementService $service)
    {
    }

    public function index(Request $request)
    {
        $paiements = $this->base($request)
            ->with('payable', 'anneeAcademique', 'validateur')
            ->orderByRaw("CASE statut WHEN 'soumis' THEN 0 WHEN 'en_attente' THEN 1 WHEN 'rejete' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->paginate(\App\Models\Setting::perPage())->withQueryString();

        $base = fn () => $this->base($request);

        return view('admin.paiements.index', [
            'paiements'    => $paiements,
            'annees'       => AnneeAcademique::orderByDesc('libelle')->get(),
            'anneeContext' => AnneeAcademique::context(),
            'procedures'   => Procedure::orderBy('ordre')->pluck('nom', 'code'),
            'totaux'       => [
                'encaisse' => (clone $base())->where('statut', 'valide')->sum('montant'),
                'attente'  => (clone $base())->whereIn('statut', ['en_attente', 'soumis'])->sum('montant'),
                'a_verifier' => (clone $base())->where('statut', 'soumis')->count(),
            ],
        ]);
    }

    private function base(Request $request)
    {
        $query = Paiement::query();

        // Un agent restreint ne voit que la caisse de son périmètre
        $request->user()->applyPaiementPerimeter($query);

        $anneeId = $request->input('annee_id', AnneeAcademique::context()?->id);
        if (filled($anneeId) && $anneeId !== 'all') {
            $query->where('annee_academique_id', (int) $anneeId);
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('procedure')) {
            $query->where('procedure_code', $request->procedure);
        }
        if ($request->filled('operateur')) {
            $query->where('operateur', $request->operateur);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('reference', 'LIKE', "%{$s}%")
                  ->orWhere('transaction_id', 'LIKE', "%{$s}%")
                  ->orWhere('numero_payeur', 'LIKE', "%{$s}%");
            });
        }

        return $query;
    }

    public function show(Paiement $paiement)
    {
        $paiement->load('payable', 'anneeAcademique', 'validateur');
        abort_unless(request()->user()->coversPaiement($paiement), 403,
            "Ce paiement est hors de votre périmètre d'affectation.");

        return view('admin.paiements.show', compact('paiement'));
    }

    /** Rapprochement : le versement a bien été reçu. */
    public function valider(Request $request, Paiement $paiement)
    {
        abort_unless($request->user()->coversPaiement($paiement), 403,
            "Ce paiement est hors de votre périmètre d'affectation.");

        if ($paiement->estPaye()) {
            return back()->withErrors('Ce paiement est déjà validé.');
        }
        if ($paiement->statut === 'en_attente') {
            return back()->withErrors("Aucune transaction n'a encore été déclarée pour ce paiement.");
        }

        $this->service->valider($paiement, $request->user());

        return back()->with('success', "Paiement {$paiement->reference} validé.");
    }

    /** Rapprochement : versement introuvable ou incorrect. */
    public function rejeter(Request $request, Paiement $paiement)
    {
        $data = $request->validate([
            'motif_rejet' => 'required|string|max:500',
        ], [], ['motif_rejet' => 'motif']);

        abort_unless($request->user()->coversPaiement($paiement), 403,
            "Ce paiement est hors de votre périmètre d'affectation.");

        if ($paiement->estPaye()) {
            return back()->withErrors('Un paiement déjà validé ne peut pas être rejeté ici.');
        }

        $this->service->rejeter($paiement, $request->user(), $data['motif_rejet']);

        return back()->with('success', "Paiement {$paiement->reference} rejeté — l'usager peut déclarer à nouveau.");
    }

    /** Reçu PDF (uniquement pour un paiement validé). */
    public function recu(Request $request, Paiement $paiement)
    {
        abort_unless($paiement->estPaye(), 403, "Le reçu n'est disponible qu'une fois le paiement validé.");

        $paiement->load('payable', 'anneeAcademique', 'validateur');
        abort_unless($request->user()->coversPaiement($paiement), 403,
            "Ce paiement est hors de votre périmètre d'affectation.");

        \App\Models\AuditLog::record('paiement.recu', $paiement,
            "Reçu du paiement {$paiement->reference} téléchargé");

        $pdf = app(\App\Services\RecuPdfService::class)->build($paiement, $request->user()->staff_name);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="recu-' . $paiement->reference . '.pdf"',
        ]);
    }

    /** Export de la caisse, filtres compris. */
    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['excel', 'csv', 'pdf'], true), 404);

        $rows = $this->base($request)->with('payable', 'anneeAcademique', 'validateur')
            ->orderByDesc('created_at')->get();

        \App\Models\AuditLog::record('paiement.export', null,
            "Export des paiements ({$format}) — {$rows->count()} ligne(s)");

        // Nom de fichier lisible : année + filtres actifs
        $anneeId  = $request->input('annee_id');
        $anneeCtx = $anneeId === 'all'
            ? 'toutes-annees'
            : (AnneeAcademique::find((int) ($anneeId ?? AnneeAcademique::context()?->id))?->libelle);
        $contexte = [
            $anneeCtx,
            $request->filled('statut') ? \App\Models\Paiement::$statuts[$request->statut] ?? $request->statut : null,
            $request->filled('operateur') ? \App\Models\Paiement::$operateurs[$request->operateur] ?? $request->operateur : null,
        ];

        if ($format === 'pdf') {
            $entetes = ['Référence', 'Date', 'Payeur', 'Procédure', 'Montant',
                        'Opérateur', 'Transaction', 'Statut', 'Vérifié par'];
            $lignesPdf = $rows->map(function ($p) {
                $payable = $p->payable;
                $payeur = match (true) {
                    $payable instanceof \App\Models\CandidatureChoix => $payable->candidature?->full_name ?? '—',
                    $payable instanceof \App\Models\Candidature      => $payable->full_name,
                    $payable instanceof \App\Models\StudentRequest   => $payable->student?->full_name ?? '—',
                    default => '—',
                };

                return [
                    $p->reference,
                    $p->created_at?->format('d/m/Y'),
                    $payeur,
                    \App\Models\Procedure::labels()[$p->procedure_code] ?? $p->procedure_code,
                    $p->montant_formate,
                    $p->operateur_label,
                    $p->transaction_id ?: '—',
                    $p->statut_label,
                    $p->validateur?->staff_name ?? ($p->verifie_auto ? 'Opérateur (auto)' : '—'),
                ];
            });

            $pdf = app(\App\Services\TableauPdfService::class)->build(
                'État de caisse — frais de dossier',
                $entetes, $lignesPdf, $request->user()->staff_name,
                'Total encaissé : ' . number_format(
                    $this->base($request)->where('statut', 'valide')->sum('montant'), 0, ',', ' ') . ' FCFA',
                [1.1, .7, 1.5, 1.2, .9, .9, 1.3, 1, 1.2]
            );

            $nom = \App\Support\NomExport::build('paiements', $contexte, 'pdf');

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $nom . '"',
            ]);
        }

        $ext    = $format === 'csv' ? 'csv' : 'xlsx';
        $writer = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PaiementsExport($rows),
            \App\Support\NomExport::build('paiements', $contexte, $ext),
            $writer
        );
    }
}
