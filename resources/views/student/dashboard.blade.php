@extends('layouts.app')
@section('page-title', 'Mon Espace')
@section('page-subtitle', 'Dépôts et demandes')

@section('content')
    @php
        $cats = \App\Models\RequestType::categoriesFor($typeEtudiant);
        $catIcons = [
            'depot' => 'fa-folder-open',
            'attestation' => 'fa-certificate',
            'diplome' => 'fa-graduation-cap',
            'releve' => 'fa-list-alt',
            'duplicat' => 'fa-id-card',
        ];
        $catColors = [
            'depot' => 'primary',
            'attestation' => 'success',
            'diplome' => 'warning',
            'releve' => 'info',
            'duplicat' => 'danger',
        ];
        $sbadge = [
            'brouillon' => 'secondary',
            'soumis' => 'warning',
            'en_correction' => 'info',
            'validé' => 'success',
            'rejeté' => 'danger',
        ];
        $slabel = [
            'brouillon' => 'Brouillon',
            'soumis' => 'En attente',
            'en_correction' => 'À corriger',
            'validé' => 'Validé',
            'rejeté' => 'Rejeté',
        ];
    @endphp

    {{-- En-tête --}}
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="d-flex align-items-center gap-3">
                <div class="icon-box bg-primary text-white" style="width:50px;height:50px;font-size:1.3rem;">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold" style="color:var(--t1);">
                        {{ $student->prenom }} {{ $student->nom }}
                    </h5>
                    <small style="color:var(--t2);">
                        <code>{{ Auth::user()->numero_identifiant }}</code> &nbsp;·&nbsp;
                        <span class="badge bg-{{ $typeEtudiant === 'nouveau' ? 'primary' : 'secondary' }}">
                            {{ $typeEtudiant === 'nouveau' ? 'Nouveau Bachelier' : 'Ancien Étudiant' }}
                        </span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- Onglets --}}
    <ul class="nav nav-tabs mb-0" id="stTabs">
        @foreach ($cats as $key => $label)
            @php
                $all = $requestsByCategory[$key] ?? collect();
                $pending = $all->whereIn('status', ['soumis', 'en_correction'])->count();
            @endphp
            <li class="nav-item">
                <a class="nav-link {{ $key === $activeTab ? 'active' : '' }}" data-bs-toggle="tab"
                    href="#st-{{ $key }}" data-tab="{{ $key }}">
                    <i class="fas {{ $catIcons[$key] ?? 'fa-file' }}"></i>
                    <span class="d-none d-md-inline ms-1">{{ $label }}</span>
                    @if ($pending > 0)
                        <span class="badge bg-warning ms-1">{{ $pending }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach ($cats as $key => $label)
            @php
                $all = $requestsByCategory[$key] ?? collect();
                // ← CORRECTION : on prend la demande la plus récente TOUS STATUTS CONFONDUS
                $req = $all->sortByDesc('created_at')->first();
                $types = \App\Models\RequestType::forCategory($key, $typeEtudiant);
                $isDepot = $key === 'depot';
            @endphp
            <div class="tab-pane fade {{ $key === $activeTab ? 'show active' : '' }}" id="st-{{ $key }}">
                <div class="row g-4 mt-0">

                    {{-- ═══ Colonne principale ═══ --}}
                    <div class="col-lg-8">

                        {{-- ══════════════════════════════════════════
     CAS DÉPÔT
══════════════════════════════════════════ --}}
                        @if ($isDepot)

                            @if (!$req)
                                {{-- Aucun dossier — invitation à créer --}}
                                <div class="card mb-4"
                                    style="border:2px dashed rgba(59,130,246,.3);background:rgba(59,130,246,.04);">
                                    <div class="card-body text-center py-5">
                                        <i class="fas fa-folder-plus" style="font-size:3rem;color:rgba(59,130,246,.4);"></i>
                                        <h5 class="fw-bold mt-3" style="color:var(--t1);">Commencer mon dépôt de dossier
                                        </h5>
                                        <p style="color:var(--t2);max-width:400px;margin:0 auto 20px;">
                                            Uploadez les pièces requises, puis soumettez. L'administration validera
                                            et vos documents tamponnés seront disponibles en téléchargement.
                                        </p>
                                        <form method="POST"
                                            action="{{ route('student.requests.create') }}?tab={{ $key }}">
                                            @csrf
                                            <input type="hidden" name="category" value="depot">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-folder-plus"></i> Créer mon dossier
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                {{-- ─── Bandeau de statut global ─── --}}
                                @if ($req->isValidated())
                                    <div class="mb-4 p-4 rounded"
                                        style="background:rgba(16,201,122,.08);border:1px solid rgba(16,201,122,.25);">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fas fa-check-circle"
                                                style="font-size:2rem;color:var(--em);flex-shrink:0;"></i>
                                            <div>
                                                <h5 class="fw-bold mb-1" style="color:var(--em);">Dossier validé !</h5>
                                                <p class="mb-0 small" style="color:var(--t2);">
                                                    Validé le {{ $req->validated_at?->format('d/m/Y à H:i') }}.
                                                    @if ($req->validator)
                                                        Par <strong
                                                            style="color:var(--t1);">{{ $req->validator->numero_identifiant }}</strong>.
                                                    @endif
                                                    @php $nbStamped = $req->files->where('is_stamped', true)->count(); @endphp
                                                    @if ($nbStamped > 0)
                                                        &nbsp;·&nbsp;
                                                        <strong style="color:var(--em);">
                                                            {{ $nbStamped }} document(s) tamponné(s)
                                                        </strong>
                                                        disponibles en téléchargement ci-dessous.
                                                    @endif
                                                </p>
                                                @if ($req->admin_comment)
                                                    <p class="mb-0 mt-1 small" style="color:var(--t1);">
                                                        <i class="fas fa-comment"></i> {{ $req->admin_comment }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @elseif($req->isInCorrection())
                                    <div class="alert alert-warning mb-4">
                                        <div class="d-flex align-items-start gap-3">
                                            <i class="fas fa-rotate-left mt-1"
                                                style="font-size:1.3rem;color:var(--amber);flex-shrink:0;"></i>
                                            <div>
                                                <h6 class="fw-bold mb-1" style="color:var(--amber);">Corrections demandées
                                                </h6>
                                                <p class="mb-0 small" style="color:var(--t1);">
                                                    {{ $req->rejection_message }}</p>
                                                <p class="mb-0 mt-1 small" style="color:var(--t2);">
                                                    Corrigez les documents signalés ci-dessous, puis resoumettez.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($req->isSubmitted())
                                    <div class="mb-4 p-4 rounded"
                                        style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fas fa-hourglass-half"
                                                style="font-size:1.8rem;color:var(--amber);flex-shrink:0;"></i>
                                            <div>
                                                <h6 class="fw-bold mb-0" style="color:var(--amber);">En attente de
                                                    traitement</h6>
                                                <p class="mb-0 small" style="color:var(--t2);">
                                                    Soumis le {{ $req->submitted_at?->format('d/m/Y à H:i') }}.
                                                    L'administration examine votre dossier.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @elseif($req->isRejected())
                                    <div class="mb-4 p-4 rounded"
                                        style="background:rgba(244,63,94,.06);border:1px solid rgba(244,63,94,.25);">
                                        <div class="d-flex align-items-start gap-3">
                                            <i class="fas fa-ban"
                                                style="font-size:1.8rem;color:var(--red);flex-shrink:0;margin-top:4px;"></i>
                                            <div>
                                                <h6 class="fw-bold mb-1" style="color:var(--red);">Dossier rejeté</h6>
                                                <p class="mb-0 small" style="color:var(--t1);">
                                                    {{ $req->rejection_message }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- ─── Liste des documents ─── --}}
                                <div class="card mb-0">
                                    <div
                                        class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <span style="color:var(--t1);font-weight:600;">
                                            <i class="fas fa-paperclip"></i> Documents
                                        </span>
                                        @if ($types->where('has_stamp', true)->isNotEmpty() && !$req->isValidated())
                                            <span style="font-size:11.5px;color:var(--blue);">
                                                <i class="fas fa-stamp"></i> Les documents marqués seront tamponnés à la
                                                validation
                                            </span>
                                        @endif
                                    </div>
                                    <div class="list-group list-group-flush">
                                        @foreach ($types as $type)
                                            @php
                                                $file = $req->fileForType($type);
                                                $isLocked = $file?->is_locked ?? false;
                                                $isInvalid = $file && $file->is_valid === false;
                                                $isStamped = $file?->is_stamped ?? false;
                                                $canUpload = $req->canEdit() && !$isLocked;
                                                $canDl = $req->isValidated() && $isStamped && $file !== null;
                                            @endphp
                                            <div class="list-group-item px-4 py-3"
                                                style="
                    @if ($isStamped) background:rgba(59,130,246,.06);
                    @elseif($isLocked && !$isInvalid) background:rgba(16,201,122,.05);
                    @elseif($isInvalid) background:rgba(244,63,94,.06); @endif
                 ">
                                                <div class="d-flex align-items-start gap-3">

                                                    {{-- Icône état --}}
                                                    <div class="mt-1" style="flex-shrink:0;width:22px;text-align:center;">
                                                        @if ($isStamped)
                                                            <i class="fas fa-stamp fs-5" style="color:var(--blue);"
                                                                title="Tamponné — disponible en téléchargement"></i>
                                                        @elseif($isLocked && !$isInvalid)
                                                            <i class="fas fa-lock text-success fs-5"
                                                                title="Validé — non modifiable"></i>
                                                        @elseif($file && !$isInvalid)
                                                            <i class="fas fa-check-circle text-success fs-5"></i>
                                                        @elseif($isInvalid)
                                                            <i class="fas fa-exclamation-circle text-danger fs-5"></i>
                                                        @else
                                                            <i class="fas fa-circle fs-5"
                                                                style="color:var(--t3);opacity:.25;"></i>
                                                        @endif
                                                    </div>

                                                    <div class="flex-grow-1 min-w-0">
                                                        {{-- Intitulé + badges --}}
                                                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                            <strong style="color:var(--t1);">{{ $type->label }}</strong>
                                                            @if ($type->is_required)
                                                                <span class="badge bg-danger"
                                                                    style="font-size:10px;">Obligatoire</span>
                                                            @else
                                                                <span class="badge bg-secondary"
                                                                    style="font-size:10px;">Optionnel</span>
                                                            @endif
                                                            @if ($type->has_stamp)
                                                                @if ($isStamped)
                                                                    <span class="badge"
                                                                        style="font-size:10px;background:rgba(59,130,246,.2);color:#93c5fd;border:1px solid rgba(59,130,246,.35);">
                                                                        <i class="fas fa-stamp"></i> Tamponné ✓
                                                                    </span>
                                                                @elseif(!$req->isValidated())
                                                                    <span class="badge"
                                                                        style="font-size:10px;background:transparent;color:var(--t2);border:1px dashed var(--t3);">
                                                                        <i class="fas fa-stamp"></i> Sera tamponné
                                                                    </span>
                                                                @endif
                                                            @endif
                                                            @if ($isLocked && !$isStamped)
                                                                <span class="badge bg-success" style="font-size:10px;">
                                                                    <i class="fas fa-lock"></i> Verrouillé
                                                                </span>
                                                            @endif
                                                        </div>

                                                        @if ($type->description)
                                                            <small style="color:var(--t2);"
                                                                class="d-block mb-1">{{ $type->description }}</small>
                                                        @endif

                                                        {{-- Fichier --}}
                                                        @if ($file)
                                                            <div class="d-flex align-items-center gap-2 p-2 rounded mt-1"
                                                                style="border:1px solid var(--line-mid);background:var(--surface-2);">
                                                                <i
                                                                    class="fas {{ $file->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                                                                <span class="small flex-grow-1 text-truncate"
                                                                    style="color:var(--t1);">
                                                                    {{ $file->original_filename }}
                                                                </span>
                                                                <span class="small"
                                                                    style="color:var(--t3);white-space:nowrap;">
                                                                    {{ $file->file_size_formatted }}
                                                                </span>

                                                                {{-- ✅ TÉLÉCHARGER — uniquement fichier is_stamped=true --}}
                                                                @if ($canDl)
                                                                    <a href="{{ route('student.requests.file.download', $file) }}"
                                                                        class="btn btn-sm"
                                                                        style="background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.35);color:#93c5fd;white-space:nowrap;"
                                                                        title="Télécharger la version officielle avec cachet">
                                                                        <i class="fas fa-stamp"></i>
                                                                        <i class="fas fa-download ms-1"></i>
                                                                        Télécharger
                                                                    </a>
                                                                @endif

                                                                {{-- Supprimer --}}
                                                                @if ($canUpload)
                                                                    <form method="POST"
                                                                        action="{{ route('student.requests.file.delete', $file) }}?tab={{ $key }}"
                                                                        class="d-inline">
                                                                        @csrf @method('DELETE')
                                                                        <button type="submit"
                                                                            class="btn btn-sm btn-outline-danger"
                                                                            onclick="return confirm('Supprimer ce fichier ?')">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="mt-1">
                                                                @if ($canUpload)
                                                                    <span style="color:var(--t3);font-size:12px;">
                                                                        <i class="fas fa-cloud-upload-alt"></i> Aucun
                                                                        fichier — cliquez
                                                                        <i class="fas fa-upload ms-1"></i> pour uploader
                                                                    </span>
                                                                @else
                                                                    <span class="badge bg-secondary"
                                                                        style="font-size:10px;">
                                                                        <i class="fas fa-ban"></i> Non fourni
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @endif

                                                        {{-- Remarque admin --}}
                                                        @if ($file?->admin_comment)
                                                            <div class="mt-2 p-2 rounded small d-flex align-items-start gap-2"
                                                                style="background:var(--amber-dim);border:1px solid rgba(245,158,11,.25);color:var(--amber);">
                                                                <i class="fas fa-comment-alt mt-1"
                                                                    style="flex-shrink:0;"></i>
                                                                <span>{{ $file->admin_comment }}</span>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    {{-- Bouton upload --}}
                                                    @if ($canUpload && (!$file || $isInvalid))
                                                        <div class="flex-shrink-0">
                                                            <button type="button" class="btn btn-sm btn-primary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#up-{{ $key }}-{{ $type->id }}">
                                                                <i class="fas fa-upload"></i>
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif {{-- fin $req existant --}}


                            {{-- ══════════════════════════════════════════
     CAS DEMANDES (attestation, diplôme, etc.)
══════════════════════════════════════════ --}}
                        @else
                            @if (!$req)
                                <div class="card mb-4"
                                    style="border:2px dashed rgba(16,201,122,.25);background:rgba(16,201,122,.03);">
                                    <div class="card-body text-center py-5">
                                        <i class="fas {{ $catIcons[$key] }}"
                                            style="font-size:3rem;color:rgba(16,201,122,.3);"></i>
                                        <h5 class="fw-bold mt-3" style="color:var(--t1);">{{ $label }}</h5>
                                        <p style="color:var(--t2);max-width:400px;margin:0 auto 20px;">
                                            Soumettez votre demande en fournissant les documents requis.
                                            Vous recevrez une date de rendez-vous pour le retrait.
                                        </p>
                                        <form method="POST"
                                            action="{{ route('student.requests.create') }}?tab={{ $key }}">
                                            @csrf
                                            <input type="hidden" name="category" value="{{ $key }}">
                                            <button type="submit" class="btn btn-{{ $catColors[$key] }} btn-lg">
                                                <i class="fas fa-plus"></i> Créer une demande
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @elseif($req->isSubmitted())
                                <div class="card mb-4">
                                    <div class="card-body text-center py-5">
                                        <i class="fas fa-hourglass-half" style="font-size:3rem;color:var(--amber);"></i>
                                        <h5 class="fw-bold mt-3" style="color:var(--t1);">Demande en cours de traitement
                                        </h5>
                                        <p style="color:var(--t2);">
                                            Votre demande a été reçue et est examinée par l'administration.<br>
                                            Vous serez informé dès qu'elle sera traitée.
                                        </p>
                                        <div class="p-3 rounded mt-2 d-inline-block"
                                            style="background:var(--surface-2);border:1px solid var(--line);">
                                            <small style="color:var(--t3);">
                                                <i class="fas fa-clock"></i>
                                                Soumis le {{ $req->submitted_at?->format('d/m/Y à H:i') }}
                                                · Version #{{ $req->version }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @elseif($req->isValidated())
                                <div class="card mb-4"
                                    style="border:1px solid rgba(16,201,122,.3);background:rgba(16,201,122,.05);">
                                    <div class="card-body py-4">
                                        <div class="d-flex align-items-start gap-4">
                                            <div style="flex-shrink:0;">
                                                <i class="fas fa-calendar-check"
                                                    style="font-size:2.5rem;color:var(--em);"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="fw-bold mb-2" style="color:var(--em);">
                                                    Demande traitée — Rendez-vous fixé
                                                </h5>
                                                @if ($req->rendez_vous_at)
                                                    <div class="mb-3 p-3 rounded"
                                                        style="background:rgba(16,201,122,.1);border:1px solid rgba(16,201,122,.2);">
                                                        <div
                                                            style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);">
                                                            Date de retrait
                                                        </div>
                                                        <div style="font-size:1.3em;font-weight:700;color:var(--em);">
                                                            {{ $req->rendez_vous_at->translatedFormat('l d F Y') }}
                                                        </div>
                                                        <div style="color:var(--t1);">
                                                            à {{ $req->rendez_vous_at->format('H:i') }}
                                                        </div>
                                                    </div>
                                                @endif
                                                @if ($req->rendez_vous_note)
                                                    <div class="mb-2 d-flex align-items-start gap-2">
                                                        <i class="fas fa-map-marker-alt mt-1"
                                                            style="color:var(--t2);flex-shrink:0;"></i>
                                                        <span style="color:var(--t1);">{{ $req->rendez_vous_note }}</span>
                                                    </div>
                                                @endif
                                                @if ($req->admin_comment)
                                                    <div class="mt-2 small" style="color:var(--t2);">
                                                        <i class="fas fa-comment"></i> {{ $req->admin_comment }}
                                                    </div>
                                                @endif
                                                <div class="mt-3 p-2 rounded small d-flex align-items-center gap-2"
                                                    style="background:var(--surface-2);border:1px solid var(--line);">
                                                    <i class="fas fa-info-circle"
                                                        style="color:var(--blue);flex-shrink:0;"></i>
                                                    <span style="color:var(--t2);">
                                                        Présentez-vous muni de votre carte étudiant à la date indiquée.
                                                    </span>
                                                </div>
                                                @if ($req->validator)
                                                    <div class="mt-2 small" style="color:var(--t3);">
                                                        Validé par : <strong
                                                            style="color:var(--t2);">{{ $req->validator->numero_identifiant }}</strong>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @elseif($req->isInCorrection())
                                <div class="alert alert-warning mb-4">
                                    <div class="d-flex align-items-start gap-3">
                                        <i class="fas fa-rotate-left mt-1"
                                            style="font-size:1.3rem;color:var(--amber);flex-shrink:0;"></i>
                                        <div>
                                            <h6 class="fw-bold mb-1" style="color:var(--amber);">
                                                Votre dossier nécessite des corrections
                                            </h6>
                                            <p class="mb-0 small" style="color:var(--t1);">{{ $req->rejection_message }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4">
                                    <div class="card-header" style="color:var(--t1);font-weight:600;">
                                        <i class="fas fa-paperclip"></i> Documents à corriger
                                    </div>
                                    <div class="list-group list-group-flush">
                                        @foreach ($types as $type)
                                            @php
                                                $file = $req->fileForType($type);
                                                $isLocked = $file?->is_locked ?? false;
                                                $isInvalid = $file && $file->is_valid === false;
                                                $canUpload = $req->canEdit() && !$isLocked;
                                            @endphp
                                            <div class="list-group-item px-4 py-3"
                                                style="{{ $isInvalid ? 'background:rgba(244,63,94,.06);' : ($isLocked ? 'background:rgba(16,201,122,.05);' : '') }}">
                                                <div class="d-flex align-items-center gap-3">
                                                    <i class="fas {{ $isLocked ? 'fa-lock text-success' : ($isInvalid ? 'fa-exclamation-circle text-danger' : ($file ? 'fa-check-circle text-success' : 'fa-circle')) }} fs-5"
                                                        style="{{ !$file && !$isLocked && !$isInvalid ? 'color:var(--t3);opacity:.25;' : '' }}"></i>
                                                    <div class="flex-grow-1">
                                                        <strong style="color:var(--t1);">{{ $type->label }}</strong>
                                                        @if ($file?->admin_comment)
                                                            <div class="mt-1 small" style="color:var(--amber);">
                                                                <i class="fas fa-comment-alt"></i>
                                                                {{ $file->admin_comment }}
                                                            </div>
                                                        @endif
                                                        @if ($file)
                                                            <div class="small mt-1" style="color:var(--t2);">
                                                                {{ $file->original_filename }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    @if ($canUpload && (!$file || $isInvalid))
                                                        <button type="button"
                                                            class="btn btn-sm btn-{{ $catColors[$key] }}"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#up-{{ $key }}-{{ $type->id }}">
                                                            <i class="fas fa-upload"></i> Remplacer
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif($req->isRejected())
                                <div class="card mb-4"
                                    style="border:1px solid rgba(244,63,94,.3);background:rgba(244,63,94,.05);">
                                    <div class="card-body py-4 text-center">
                                        <i class="fas fa-ban" style="font-size:2.5rem;color:var(--red);"></i>
                                        <h5 class="fw-bold mt-3" style="color:var(--red);">Demande rejetée</h5>
                                        <p style="color:var(--t1);">{{ $req->rejection_message }}</p>
                                    </div>
                                </div>
                            @elseif($req->isDraft())
                                <div class="card mb-4">
                                    <div class="card-header" style="color:var(--t1);font-weight:600;">
                                        <i class="fas fa-paperclip"></i> Documents à fournir
                                    </div>
                                    <div class="list-group list-group-flush">
                                        @foreach ($types as $type)
                                            @php
                                                $file = $req->fileForType($type);
                                                $canUpload = $req->canEdit() && !($file?->is_locked ?? false);
                                            @endphp
                                            <div class="list-group-item px-4 py-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <i class="fas {{ $file ? 'fa-check-circle text-success' : 'fa-circle' }} fs-5"
                                                        style="{{ !$file ? 'color:var(--t3);opacity:.25;' : '' }}"></i>
                                                    <div class="flex-grow-1">
                                                        <strong style="color:var(--t1);">{{ $type->label }}</strong>
                                                        @if ($type->is_required)
                                                            <span class="badge bg-danger ms-1"
                                                                style="font-size:10px;">Obligatoire</span>
                                                        @endif
                                                        @if ($file)
                                                            <div class="small mt-1" style="color:var(--t2);">
                                                                {{ $file->original_filename }}
                                                                ({{ $file->file_size_formatted }})
                                                            </div>
                                                        @endif
                                                    </div>
                                                    @if ($canUpload && !$file)
                                                        <button type="button"
                                                            class="btn btn-sm btn-{{ $catColors[$key] }}"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#up-{{ $key }}-{{ $type->id }}">
                                                            <i class="fas fa-upload"></i>
                                                        </button>
                                                    @elseif($canUpload && $file)
                                                        <form method="POST"
                                                            action="{{ route('student.requests.file.delete', $file) }}?tab={{ $key }}"
                                                            class="d-inline">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Supprimer ?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                        @endif {{-- fin isDepot --}}

                    </div>{{-- /col principale --}}

                    {{-- ═══ Colonne droite ═══ --}}
                    <div class="col-lg-4">
                        @if ($req && $req->canEdit())
                            <div class="card sticky-top" style="top:80px;">
                                <div class="card-header bg-{{ $catColors[$key] }} text-white">
                                    <h6 class="mb-0"><i class="fas fa-paper-plane"></i> Soumettre</h6>
                                </div>
                                <div class="card-body">
                                    @php $allOk = $req->allRequiredFilesUploaded(); @endphp
                                    <ul class="list-unstyled mb-3">
                                        @foreach ($types->where('is_required', true) as $dt)
                                            @php $f = $req->fileForType($dt); @endphp
                                            <li class="d-flex align-items-center gap-2 mb-2">
                                                <i class="fas {{ $f ? 'fa-check-circle text-success' : 'fa-circle' }} small"
                                                    style="{{ !$f ? 'color:var(--t3);opacity:.4;' : '' }}"></i>
                                                <span class="small" style="color:var(--t1);">{{ $dt->label }}</span>
                                                @if ($isDepot && $dt->has_stamp)
                                                    <i class="fas fa-stamp ms-auto"
                                                        style="color:var(--blue);font-size:10px;"
                                                        title="Sera tamponné"></i>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if (!$allOk)
                                        <p class="small mb-2" style="color:var(--amber);">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Uploadez tous les documents obligatoires.
                                        </p>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-lock"></i> Soumettre
                                        </button>
                                    @else
                                        <form method="POST"
                                            action="{{ route('student.requests.submit') }}?tab={{ $key }}">
                                            @csrf
                                            <input type="hidden" name="student_request_id" value="{{ $req->id }}">
                                            <button type="submit" class="btn btn-{{ $catColors[$key] }} w-100"
                                                onclick="return confirm('Confirmer la soumission ?')">
                                                <i class="fas fa-check"></i> Soumettre
                                            </button>
                                        </form>
                                    @endif
                                    @if ($isDepot && $types->where('has_stamp', true)->isNotEmpty())
                                        <div class="mt-3 pt-3" style="border-top:1px solid var(--line);">
                                            <small class="d-flex align-items-start gap-2" style="color:var(--t2);">
                                                <i class="fas fa-stamp mt-1" style="color:var(--blue);flex-shrink:0;"></i>
                                                Les documents <i class="fas fa-stamp mx-1"
                                                    style="color:var(--blue);font-size:10px;"></i>
                                                reçoivent un <strong style="color:var(--t1);">cachet officiel</strong>.
                                                Téléchargeables après validation.
                                            </small>
                                        </div>
                                    @endif
                                    @if (!$isDepot)
                                        <div class="mt-3 pt-3" style="border-top:1px solid var(--line);">
                                            <small class="d-flex align-items-start gap-2" style="color:var(--t2);">
                                                <i class="fas fa-calendar-check mt-1"
                                                    style="color:var(--em);flex-shrink:0;"></i>
                                                Après validation, vous recevrez une
                                                <strong style="color:var(--t1);">date de rendez-vous</strong>
                                                pour le retrait physique.
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                </div>{{-- /row --}}
            </div>{{-- /tab-pane --}}
        @endforeach
    </div>

    {{-- Modals upload --}}
    @foreach ($cats as $key => $label)
        @php
            $ar = ($requestsByCategory[$key] ?? collect())->whereIn('status', ['brouillon', 'en_correction'])->first();
            $types = \App\Models\RequestType::forCategory($key, $typeEtudiant);
        @endphp
        @if ($ar)
            @foreach ($types as $type)
                @php $f = $ar->fileForType($type); @endphp
                @if (!$f || ($f->is_valid === false && !$f->is_locked))
                    <div class="modal fade" id="up-{{ $key }}-{{ $type->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h6 class="modal-title" style="color:var(--t1);">
                                        <i class="fas fa-upload"></i> {{ $type->label }}
                                        @if ($type->has_stamp && $key === 'depot')
                                            <span class="ms-2 small" style="color:var(--blue);">
                                                <i class="fas fa-stamp"></i> Sera tamponné
                                            </span>
                                        @endif
                                    </h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST"
                                    action="{{ route('student.requests.file.upload') }}?tab={{ $key }}"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="student_request_id" value="{{ $ar->id }}">
                                    <input type="hidden" name="request_type_id" value="{{ $type->id }}">
                                    <div class="modal-body">
                                        @if ($type->has_stamp && $key === 'depot')
                                            <div class="p-3 rounded mb-3"
                                                style="background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.25);">
                                                <p class="mb-0 small" style="color:#93c5fd;">
                                                    <i class="fas fa-stamp"></i>
                                                    <strong>Ce document recevra un cachet officiel</strong>
                                                    lors de la validation. Fournissez un <strong>PDF</strong>.
                                                </p>
                                            </div>
                                        @endif
                                        @if ($type->description)
                                            <div class="alert alert-info small py-2 mb-3">
                                                <i class="fas fa-info-circle"></i> {{ $type->description }}
                                            </div>
                                        @endif
                                        <label class="form-label" style="color:var(--t2);">Sélectionner le fichier</label>
                                        <input type="file" class="form-control" name="file"
                                            accept="{{ $type->has_stamp && $key === 'depot' ? '.pdf' : '.pdf,.jpg,.jpeg,.png' }}"
                                            required>
                                        <small style="color:var(--t2);" class="mt-1 d-block">
                                            {{ $type->has_stamp && $key === 'depot' ? 'PDF uniquement (requis pour le tamponnage)' : 'PDF, JPG, PNG — Max 5 Mo' }}
                                        </small>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                            data-bs-dismiss="modal">Annuler</button>
                                        <button type="submit" class="btn btn-{{ $catColors[$key] ?? 'primary' }} btn-sm">
                                            <i class="fas fa-cloud-upload-alt"></i> Uploader
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    @endforeach

@endsection

@section('js')
    <script>
        (function() {
            document.querySelectorAll('#stTabs .nav-link').forEach(link => {
                link.addEventListener('shown.bs.tab', function() {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', this.dataset.tab);
                    history.replaceState(null, '', url);
                });
            });
        })();
    </script>
@endsection
