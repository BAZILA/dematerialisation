@extends('layouts.app')
@section('page-title', 'Candidature — ' . $candidature->full_name)
@section('page-subtitle', $candidature->reference)

@section('content')
    <div class="mb-3 d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.candidatures.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
        <a href="{{ route('admin.candidatures.telecharger', $candidature) }}" class="btn btn-outline-primary btn-sm"
            title="Toutes les pièces de ce candidat, classées par parcours">
            <i class="fas fa-file-zipper"></i> Télécharger le dossier (ZIP)
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Identité --}}
            <div class="card mb-4"><div class="card-body">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="icon-box bg-primary text-white" style="width:55px;height:55px;font-size:1.3rem;"><i class="fas fa-user"></i></div>
                    <div class="flex-grow-1">
                        <h5 class="mb-0 fw-bold" style="color:var(--t1);">{{ $candidature->full_name }}</h5>
                        <small class="text-muted"><code>{{ $candidature->reference }}</code> · {{ $candidature->email }}@if ($candidature->telephone) · {{ $candidature->telephone }}@endif</small>
                    </div>
                    <span class="badge bg-{{ $candidature->status_badge }} fs-6 px-3 py-2">{{ $candidature->statut_label }}</span>
                </div>
                <hr>
                <div class="row g-2 small">
                    <div class="col-md-4"><span class="text-muted">Sexe :</span> {{ $candidature->sexe }}</div>
                    <div class="col-md-4"><span class="text-muted">Naissance :</span> {{ $candidature->date_naissance?->format('d/m/Y') }}</div>
                    <div class="col-md-4"><span class="text-muted">Lieu :</span> {{ $candidature->lieu_naissance }}</div>
                    <div class="col-md-4 mt-2"><span class="text-muted">Soumise le :</span> {{ $candidature->created_at->format('d/m/Y à H:i') }}</div>
                    <div class="col-md-4 mt-2"><span class="text-muted">Année :</span> {{ $candidature->anneeAcademique->libelle ?? '—' }}</div>
                </div>
            </div></div>

            {{-- ═══ Décision PARCOURS PAR PARCOURS ═══
                 Un candidat peut être admis sur plusieurs parcours : il
                 choisira lequel il suit au moment de l'inscription. --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="fas fa-graduation-cap"></i> Parcours demandés &amp; décisions</span>
                    @php $nbAdmis = $candidature->choix->where('statut', 'admis')->count(); @endphp
                    @if ($nbAdmis)
                        <span class="badge bg-success">Admis sur {{ $nbAdmis }} parcours</span>
                    @endif
                </div>
                <div class="card-body">
                    @php
                        // On ne montre ici QUE les parcours que l'agent peut traiter.
                        // La vue d'ensemble reste visible dans la synthèse, à droite.
                        $sectionsVisibles = $candidature->choix->sortBy('ordre')
                            ->filter(fn ($x) => auth()->user()->coversParcours($x->parcours_id));
                        $masquees = $candidature->choix->count() - $sectionsVisibles->count();
                    @endphp

                    <p class="small text-muted">
                        Chaque parcours se décide séparément. Un candidat admis sur plusieurs parcours
                        choisira celui où il s'inscrit sur la plateforme d'inscription.
                    </p>

                    @if ($masquees)
                        <div class="alert alert-secondary small py-2">
                            <i class="fas fa-user-shield"></i>
                            {{ $masquees }} autre(s) parcours de ce candidat ne relève(nt) pas de votre périmètre :
                            ils sont instruits par leurs agents respectifs. La liste complète figure dans la synthèse.
                        </div>
                    @endif

                    @forelse ($sectionsVisibles as $c)
                        @php
                            $pSec    = \App\Models\Paiement::moduleActif() ? $c->paiement : null;
                            $bordure = match ($c->statut) {
                                'admis'  => 'rgba(47,158,51,.55)',
                                'refuse' => 'rgba(220,38,38,.45)',
                                default  => 'var(--line)',
                            };
                        @endphp
                        <div class="rounded mb-3" style="border:1px solid {{ $bordure }};border-left-width:4px;">
                            <div class="d-flex align-items-center gap-3 p-3 flex-wrap">
                                <span class="badge bg-secondary">{{ $c->ordre }}</span>
                                <div class="flex-grow-1" style="min-width:200px;">
                                    <strong style="color:var(--t1);">{{ $c->parcours->nom ?? '—' }}</strong>
                                    <small class="text-muted d-block">
                                        {{ $c->parcours->departement->faculte->nom ?? '' }} · {{ $c->parcours->grade_label ?? '' }}
                                        @if ($c->soumis_at) · postulé le {{ $c->soumis_at->format('d/m/Y') }} @endif
                                    </small>
                                </div>
                                <span class="badge bg-{{ $c->statut_badge }}">{{ $c->statut_label }}</span>
                                @if ($pSec)
                                    <span class="badge bg-{{ $pSec->statut_badge }}" title="Frais de ce parcours">
                                        <i class="fas fa-money-bill"></i> {{ $pSec->montant_formate }}
                                    </span>
                                @endif
                            </div>

                            @if ($c->motif)
                                <div class="px-3 pb-2">
                                    <div class="alert alert-{{ $c->statut === 'refuse' ? 'danger' : 'warning' }} small mb-0 py-1 px-2">
                                        <i class="fas fa-comment-dots"></i> {{ $c->motif }}
                                    </div>
                                </div>
                            @endif

                            {{-- Pièces propres à ce parcours --}}
                            @if ($c->files->count())
                                <div class="px-3 pb-2">
                                    <div class="small text-muted mb-1"><i class="fas fa-code-branch"></i> Pièces propres :</div>
                                    @foreach ($c->files as $f)
                                        <div class="small d-flex align-items-center gap-2">
                                            <i class="fas fa-file-pdf text-danger"></i>
                                            <span style="color:var(--t1);">{{ $f->requestType->label ?? 'Document' }}</span>
                                            <span class="badge bg-{{ $f->statut_badge }}">
                                                {{ \App\Models\CandidatureFile::$statuts[$f->statut] ?? $f->statut }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Décision : seulement si le parcours est dans le périmètre --}}
                            <div class="px-3 pb-3">
                                    <form method="POST" action="{{ route('admin.candidatures.section.decision', $c) }}"
                                        class="row g-2 align-items-end">
                                        @csrf
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">Décision</label>
                                            <select name="statut" class="form-select form-select-sm">
                                                @foreach (\App\Models\CandidatureChoix::$statuts as $k => $l)
                                                    @if ($k !== 'soumise')
                                                        <option value="{{ $k }}" @selected($c->statut === $k)>{{ $l }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">
                                                Motif <span class="text-muted">(obligatoire si refus ou à compléter)</span>
                                            </label>
                                            <input name="motif" class="form-control form-control-sm"
                                                value="{{ $c->motif }}" maxlength="1000"
                                                placeholder="Vu par le candidat">
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-primary btn-sm w-100" data-loading-text="…">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </div>
                                    </form>
                                    @if ($c->decideur)
                                        <div class="small text-muted mt-1">
                                            Décidé par {{ $c->decideur->staff_name }}
                                            @if ($c->decide_at) le {{ $c->decide_at->format('d/m/Y à H:i') }} @endif
                                        </div>
                                    @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small py-3 text-center">
                            <i class="fas fa-user-shield d-block fs-4 mb-2"></i>
                            Aucun des parcours de ce candidat ne relève de votre périmètre.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Complétude du dossier : pièces communes + celles propres aux vœux --}}
            @php
                $parType = $candidature->files->keyBy('request_type_id');
                $manquants = $attendus->where('is_required', true)->reject(fn($t) => $parType->has($t->id));
                // (le décompte reste global : il renseigne sur l'état réel du dossier)
                $aCorriger = $candidature->files->where('statut', 'rejete');
            @endphp
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="fas fa-clipboard-check"></i> Pièces attendues &amp; revue</span>
                    <span>
                        @if ($aCorriger->count())
                            <span class="badge bg-danger">{{ $aCorriger->count() }} à corriger</span>
                        @endif
                        @if ($manquants->isEmpty())
                            <span class="badge bg-success">Toutes les pièces obligatoires sont là</span>
                        @else
                            <span class="badge bg-danger">{{ $manquants->count() }} manquante(s)</span>
                        @endif
                    </span>
                </div>
                <div class="list-group list-group-flush">
                    @php
                        // Une pièce propre à un parcours hors périmètre ne concerne
                        // pas cet agent : elle n'a pas à encombrer sa liste.
                        $attendusVisibles = $attendus->filter(fn ($x) => $x->parcours->isEmpty()
                            || $x->parcours->pluck('id')->contains(
                                fn ($id) => auth()->user()->coversParcours((int) $id)));
                    @endphp
                    @forelse ($attendusVisibles as $t)
                        @php $f = $parType->get($t->id); @endphp
                        <div class="list-group-item py-2" data-revue-ligne>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <i class="fas {{ !$f ? ($t->is_required ? 'fa-circle-xmark text-danger' : 'fa-circle-minus text-muted') : ($f->estValidee() ? 'fa-circle-check text-success' : ($f->estRejetee() ? 'fa-triangle-exclamation text-danger' : 'fa-circle-dot text-warning')) }}"></i>
                                <div class="flex-grow-1">
                                    <span style="color:var(--t1);">{{ $t->label }}</span>
                                    @if ($t->is_required)<span class="text-danger">*</span>@endif
                                    @if ($t->parcours->isEmpty())
                                        <span class="badge bg-info ms-1" style="font-size:10px;">Commune</span>
                                    @else
                                        <span class="badge bg-warning text-dark ms-1" style="font-size:10px;"
                                            title="Exigée par ce(s) parcours">{{ $t->parcours->pluck('nom')->implode(' · ') }}</span>
                                    @endif
                                    @if ($f)
                                        <br><small class="text-muted">
                                            <i class="fas {{ $f->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                                            {{ $f->original_filename }} ({{ $f->file_size_formatted }})
                                        </small>
                                        {{-- Le fichier a disparu du serveur : le dire tout de suite,
                                             plutôt que de laisser l'agent cliquer dans le vide. --}}
                                        @unless ($f->fichierPresent())
                                            <br><small class="text-danger fw-semibold">
                                                <i class="fas fa-triangle-exclamation"></i>
                                                Fichier introuvable sur le serveur — à faire redéposer par le candidat.
                                            </small>
                                        @endunless
                                        @if ($f->verificateur)
                                            <br><small style="font-size:11px;color:var(--t3);">
                                                <i class="fas fa-user-check"></i>
                                                Examinée par {{ $f->verificateur->staff_name }}
                                                @if ($f->verifie_at) le {{ $f->verifie_at->format('d/m/Y à H:i') }} @endif
                                                @if ($f->estCommune())
                                                    · <span class="text-warning">vaut pour tous les parcours</span>
                                                @endif
                                            </small>
                                        @endif
                                    @endif
                                </div>

                                @if ($f)
                                    <span class="badge bg-{{ $f->statut_badge }}" data-statut-badge>
                                        {{ \App\Models\CandidatureFile::$statuts[$f->statut] ?? $f->statut }}
                                    </span>
                                    {{-- Consulter, télécharger et statuer : tout au même endroit --}}
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#capercu{{ $f->id }}"
                                            title="Aperçu de la pièce">
                                            <i class="fas fa-eye"></i> Aperçu
                                        </button>
                                        <a href="{{ route('admin.candidatures.file.download', $f) }}"
                                            class="btn btn-outline-secondary" title="Télécharger">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                    {{-- Décision immédiate : aucun bouton, aucun rechargement --}}
                                    @php
                                        // Pièce commune déjà tranchée par quelqu'un d'autre :
                                        // on verrouille pour éviter l'écrasement silencieux.
                                        $verrou = $f->estCommune() && $f->statut !== 'fourni'
                                            && $f->verifie_par && $f->verifie_par !== auth()->id();

                                        // Une pièce propre ne s'examine que par l'agent du parcours
                                        $cibles = $t->parcours->pluck('id');
                                        $peutStatuer = $t->parcours->isEmpty()
                                            || $cibles->contains(fn ($id) => auth()->user()->coversParcours((int) $id));
                                    @endphp
                                    @if ($peutStatuer)
                                    <select class="form-select form-select-sm" data-revue
                                        data-url="{{ route('admin.candidatures.file.review', $f) }}"
                                        style="width:auto;min-width:150px;"
                                        @if ($verrou) disabled data-verrou @endif
                                        title="Statuer sur cette pièce">
                                        @php
                                            // Le refus d'une pièce commune rejaillit sur tous les
                                            // parcours : réservé à qui a la permission dédiée.
                                            $refusCommunePossible = !$f->estCommune()
                                                || auth()->user()->can('pieces.commune_refuser');
                                        @endphp
                                        @foreach (\App\Models\CandidatureFile::$statuts as $k => $l)
                                            @if ($k !== 'rejete' || $refusCommunePossible || $f->statut === 'rejete')
                                                <option value="{{ $k }}" @selected($f->statut === $k)>{{ $l }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @if ($f->estCommune() && !$refusCommunePossible)
                                        <i class="fas fa-circle-info text-muted" style="font-size:12px;"
                                            title="Pièce commune : son refus relève d'un superviseur"></i>
                                    @endif
                                    @if ($verrou)
                                        <button class="btn btn-sm btn-outline-warning" type="button" data-deverrouiller
                                            title="Déjà examinée par un autre agent — cliquez pour reprendre la main">
                                            <i class="fas fa-lock"></i>
                                            <i class="fas fa-pen ms-1" style="font-size:10px;"></i>
                                            <span class="d-none d-xl-inline ms-1">Modifier</span>
                                        </button>
                                    @endif
                                    @endif
                                @else
                                    <small class="text-muted">{{ $t->is_required ? 'Manquante' : 'Non fournie' }}</small>
                                @endif
                            </div>

                            @if ($f && $f->estRejetee() && $f->motif_rejet)
                                <div class="alert alert-danger py-1 px-2 mt-2 mb-0 small">
                                    <i class="fas fa-comment-dots"></i> {{ $f->motif_rejet }}
                                </div>
                            @endif

                            @if ($f)
                                {{-- Motif : réclamé seulement en cas de refus --}}
                                <div class="mt-2" data-motif-wrap style="display:{{ $f->estRejetee() ? '' : 'none' }};">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="fas fa-comment-dots"></i></span>
                                        <input class="form-control" data-motif maxlength="500"
                                            value="{{ $f->motif_rejet }}"
                                            placeholder="Motif vu par le candidat — Entrée pour enregistrer">
                                        <button class="btn btn-outline-danger" type="button" data-motif-ok>
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </div>
                                    <div class="small text-danger mt-1" data-motif-err style="display:none;"></div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Aucune pièce configurée pour les candidatures.</div>
                    @endforelse
                </div>
                <div class="card-footer small text-muted">
                    Une pièce <strong>validée</strong> est verrouillée côté candidat ; seules les pièces
                    <strong>à corriger</strong> (et les manquantes) pourront être redéposées.
                </div>
            </div>

        </div>

        {{-- ═══ Synthèse ═══ --}}
        <div class="col-lg-4">
            <div class="card sticky-top" style="top:80px;">
                <div class="card-header bg-primary text-white"><i class="fas fa-gavel"></i> Synthèse du dossier</div>
                <div class="card-body">
                    @php
                        $admis   = $candidature->choix->where('statut', 'admis');
                        $attente = $candidature->choix->whereIn('statut', ['soumise', 'en_instruction']);
                        $aMoi    = $candidature->choix->filter(fn ($c) => auth()->user()->coversParcours($c->parcours_id));
                    @endphp

                    <div class="text-center mb-3">
                        <span class="badge bg-{{ $candidature->status_badge }} px-3 py-2">{{ $candidature->statut_label }}</span>
                        <div class="small text-muted mt-1">Statut d'ensemble, calculé sur les parcours</div>
                    </div>

                    <div class="d-flex justify-content-between small py-2" style="border-bottom:1px dashed var(--line);">
                        <span class="text-muted">Parcours demandés</span>
                        <strong style="color:var(--t1);">{{ $candidature->choix->count() }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small py-2" style="border-bottom:1px dashed var(--line);">
                        <span class="text-muted">Admis</span>
                        <strong class="text-success">{{ $admis->count() }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small py-2" style="border-bottom:1px dashed var(--line);">
                        <span class="text-muted">En attente de décision</span>
                        <strong style="color:var(--t1);">{{ $attente->count() }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small py-2">
                        <span class="text-muted">Dans votre périmètre</span>
                        <strong style="color:var(--t1);">{{ $aMoi->count() }} / {{ $candidature->choix->count() }}</strong>
                    </div>

                    {{-- Vue d'ensemble : TOUS les parcours souhaités, même hors périmètre --}}
                    <div class="mt-3">
                        <div class="small fw-semibold mb-2" style="color:var(--t1);">
                            <i class="fas fa-graduation-cap"></i> Parcours souhaités
                        </div>
                        @foreach ($candidature->choix->sortBy('ordre') as $c)
                            @php $mien = auth()->user()->coversParcours($c->parcours_id); @endphp
                            <div class="d-flex align-items-start gap-2 py-1"
                                style="border-bottom:1px dashed var(--line);{{ $mien ? '' : 'opacity:.65;' }}">
                                <span class="badge bg-secondary" style="font-size:10px;">{{ $c->ordre }}</span>
                                <div class="flex-grow-1">
                                    <div class="small" style="color:var(--t1);line-height:1.3;">
                                        {{ $c->parcours->nom ?? '—' }}
                                        @if ($mien)
                                            <i class="fas fa-user-check text-primary ms-1" title="Dans votre périmètre"></i>
                                        @endif
                                    </div>
                                    <div style="font-size:10.5px;color:var(--t3);">
                                        {{ $c->parcours->departement->faculte->sigle ?? $c->parcours->departement->faculte->nom ?? '' }}
                                        · {{ $c->parcours->grade_label ?? '' }}
                                    </div>
                                </div>
                                <span class="badge bg-{{ $c->statut_badge }}" style="font-size:10px;">
                                    {{ $c->statut_label }}
                                </span>
                            </div>
                        @endforeach
                        <div class="small text-muted mt-2" style="font-size:10.5px;">
                            <i class="fas fa-user-check text-primary"></i> = parcours que vous pouvez traiter.
                        </div>
                    </div>

                    @if ($admis->count())
                        <div class="alert alert-success small mt-3 mb-0">
                            <i class="fas fa-circle-check"></i>
                            <strong>Admis sur {{ $admis->count() }} parcours.</strong>
                            Le candidat choisira lequel il suit lors de son inscription.
                            <div class="mt-1">
                                @foreach ($admis as $a)
                                    <div>· {{ $a->parcours->nom ?? '—' }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($attente->count())
                        <div class="alert alert-warning small mt-3 mb-0">
                            <i class="fas fa-hourglass-half"></i>
                            {{ $attente->count() }} parcours attend(ent) encore une décision.
                        </div>
                    @endif

                    <div class="small text-muted mt-3">
                        La décision se prend <strong>parcours par parcours</strong>, dans la liste de gauche.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        /* Revue des pièces à la volée : on choisit, c'est enregistré.
           Un refus exige un motif — le champ apparaît alors et c'est lui
           qui déclenche l'enregistrement. */
        (function () {
            const jeton = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

            function signaler(ligne, texte, erreur = false) {
                const z = ligne.querySelector('[data-motif-err]');
                if (!z) return;
                z.textContent = texte || '';
                z.style.display = texte ? '' : 'none';
                z.className = 'small mt-1 ' + (erreur ? 'text-danger' : 'text-success');
            }

            async function enregistrer(ligne) {
                const sel   = ligne.querySelector('[data-revue]');
                const motif = ligne.querySelector('[data-motif]');
                const badge = ligne.querySelector('[data-statut-badge]');

                sel.disabled = true;
                try {
                    const r = await fetch(sel.dataset.url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': jeton,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            statut: sel.value,
                            motif_rejet: motif ? motif.value : null,
                            confirmer: ligne.dataset.confirmer === '1',
                        }),
                    });
                    const d = await r.json();

                    if (!d.ok) {
                        // Pièce commune déjà tranchée : on demande confirmation avant d'écraser
                        if (d.deja_examinee && confirm(d.message + '\n\nRéviser cette décision ?')) {
                            ligne.dataset.confirmer = '1';
                            return enregistrer(ligne);
                        }
                        signaler(ligne, d.message || 'Enregistrement refusé.', true);
                        return;
                    }
                    ligne.dataset.confirmer = '';

                    badge.textContent = d.libelle;
                    badge.className = 'badge bg-' + d.badge;
                    badge.setAttribute('data-statut-badge', '');
                    signaler(ligne, 'Enregistré.', false);
                    setTimeout(() => signaler(ligne, ''), 1800);

                    const b = document.getElementById('badgeCompletude');
                    if (b) {
                        b.className = 'badge bg-' + (d.complet ? 'success' : 'danger');
                        b.textContent = d.complet
                            ? 'Toutes les pièces obligatoires sont là'
                            : d.manque + ' pièce(s) manquante(s)';
                    }
                } catch (e) {
                    signaler(ligne, 'Connexion interrompue, réessayez.', true);
                } finally {
                    sel.disabled = false;
                }
            }

            document.querySelectorAll('[data-revue-ligne]').forEach(ligne => {
                const sel = ligne.querySelector('[data-revue]');
                if (!sel) return;
                const wrap  = ligne.querySelector('[data-motif-wrap]');
                const motif = ligne.querySelector('[data-motif]');

                sel.addEventListener('change', () => {
                    const refus = sel.value === 'rejete';
                    if (wrap) wrap.style.display = refus ? '' : 'none';
                    // Un refus sans motif n'apprend rien au candidat : on attend le motif
                    if (refus) { motif?.focus(); signaler(ligne, 'Indiquez le motif puis validez.', true); return; }
                    enregistrer(ligne);
                });

                motif?.addEventListener('keydown', e => {
                    if (e.key === 'Enter') { e.preventDefault(); enregistrer(ligne); }
                });
                ligne.querySelector('[data-motif-ok]')?.addEventListener('click', () => enregistrer(ligne));

                // Réviser la décision d'un autre agent sur une pièce commune
                ligne.querySelector('[data-deverrouiller]')?.addEventListener('click', function () {
                    sel.disabled = false;
                    sel.focus();
                    this.remove();
                    signaler(ligne, "Pièce commune : votre décision remplacera celle de l'agent précédent.", true);
                });
            });
        })();
    </script>

    {{-- ═══ Modals d'aperçu inline ═══ --}}
    @foreach ($candidature->files as $f)
        <div class="modal fade" id="capercu{{ $f->id }}" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title" style="color:var(--t1);">
                            <i class="fas {{ $f->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                            {{ $f->requestType->label ?? 'Document' }} — {{ $f->original_filename }}
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0" style="height:80vh;">
                        @if ($f->isPdf())
                            <iframe src="{{ route('admin.candidatures.file.preview', $f) }}" style="width:100%;height:100%;border:none;"></iframe>
                        @else
                            <div class="d-flex align-items-center justify-content-center h-100" style="background:var(--surface-2);">
                                <img src="{{ route('admin.candidatures.file.preview', $f) }}" style="max-width:100%;max-height:100%;object-fit:contain;">
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer py-2">
                        <a href="{{ route('admin.candidatures.file.download', $f) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i> Télécharger</a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@section('js')
    <script>
        (function () {
            const sel = document.getElementById('candStatus');
            const box = document.getElementById('admisBox');
            const hint = document.getElementById('corrHint');
            const maj = () => {
                box.style.display = sel.value === 'admis' ? '' : 'none';
                hint.style.display = sel.value === 'en_correction' ? 'block' : 'none';
                const p = box.querySelector('select');
                if (p) p.required = sel.value === 'admis';
            };
            sel.addEventListener('change', maj);
            maj();
        })();
    </script>
@endsection
