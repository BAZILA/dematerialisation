@extends('layouts.student')
@section('page-title', 'Mon Espace')
@section('page-subtitle', 'Dépôts et demandes')

@section('css')
    <style>
        /* « min-w-0 » etait utilisee sans jamais avoir ete definie : c'est une
           classe Tailwind, Bootstrap 5 ne la connait pas. Sans elle, une
           colonne flexible ne retrecit pas sous la longueur de son contenu :
           un nom de fichier long poussait la taille et le bouton de depot
           hors de l'ecran sur mobile. */
        .min-w-0 { min-width: 0 !important; }

        /* Pastille du fichier : le nom se tronque avec des points de
           suspension, la taille et les actions restent toujours visibles. */
        .piece-fichier { min-width: 0; flex-wrap: wrap; }
        .piece-fichier .piece-nom {
            min-width: 0; flex: 1 1 120px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        @media (max-width: 575.98px) {
            /* Sur un telephone, 1,5 rem de marge de chaque cote mangeait la
               place du nom de fichier. */
            #dashRoot .list-group-item.px-4 { padding-left: 12px !important; padding-right: 12px !important; }
            #dashRoot .list-group-item .d-flex.gap-3 { gap: 10px !important; }
            /* La taille passe sous le nom plutot que de le compresser a rien. */
            .piece-fichier .piece-nom { flex-basis: 100%; }
        }

        .dash-hero {
            background: linear-gradient(120deg, var(--brand) 0%, #1f7a48 100%);
            border-radius: var(--r-lg); padding: 26px 30px; color: #fff;
            position: relative; overflow: hidden; box-shadow: var(--shadow);
        }
        .dash-hero::after { content:''; position:absolute; right:-50px; top:-60px; width:230px; height:230px;
            background: radial-gradient(circle, rgba(255,255,255,.16), transparent 70%); }
        .dash-hero h1 { font-family: var(--font-head); font-size: 25px; font-weight: 800; letter-spacing:-.02em; position:relative; }
        .dash-hero p { opacity: .92; margin-top: 4px; font-size: 14px; position:relative; }
        .hero-badges { margin-top: 15px; display:flex; gap:9px; flex-wrap:wrap; position:relative; }
        .hero-chip { background: rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.28);
            padding: 6px 12px; border-radius: 10px; font-size: 12px; font-weight:600; }

        .stat-grid { display:grid; grid-template-columns: repeat(4,1fr); gap:14px; }
        .stat-tile { background: var(--surface); border:1px solid var(--line); border-radius: var(--r-lg);
            padding: 16px 18px; box-shadow: var(--shadow-sm); display:flex; align-items:center; gap:13px; transition: all var(--dur) var(--ease); }
        .stat-tile:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .stat-ic { width:46px; height:46px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:1.15rem; flex-shrink:0;
            background:var(--surface-2); color:var(--t2); border:1px solid var(--line); }
        /* Mise en avant discrète du compteur « En attente » (sans rainbow) */
        .stat-attn { border-color:rgba(245,158,11,.4); }
        .stat-attn .stat-ic { background:rgba(245,158,11,.10); color:var(--amber,#b45309); border-color:rgba(245,158,11,.3); }
        .stat-attn .stat-n { color:var(--amber,#b45309); }
        .stat-n { font-family: var(--font-head); font-size:24px; font-weight:800; line-height:1; color:var(--t1); }
        .stat-l { font-size:12px; color:var(--t2); margin-top:4px; }

        .qa-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(158px,1fr)); gap:14px; }
        .qa-tile { background: var(--surface); border:1px solid var(--line); border-radius: var(--r-lg);
            padding: 17px; text-decoration:none; display:block; box-shadow: var(--shadow-sm); transition: all var(--dur) var(--ease); }
        .qa-tile:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
        .qa-ic { width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:11px;
            background:var(--surface-2); color:var(--t2); border:1px solid var(--line); transition:all var(--dur) var(--ease); }
        .qa-tile:hover .qa-ic { background:var(--em-dim); color:var(--brand); border-color:transparent; }
        .qa-title { font-weight:700; color:var(--t1); font-size:14px; }
        .qa-sub { font-size:11.5px; color:var(--t3); margin-top:2px; }
        .sect-label { font-family:var(--font-head); font-weight:700; font-size:13px; letter-spacing:.04em;
            text-transform:uppercase; color:var(--t3); margin:26px 0 12px; }

        /* Sélecteur d'année dans le hero — TOUTE la zone est le select (100 % cliquable) */
        .year-switch { position:relative; flex-shrink:0; }
        .year-label {
            position:absolute; top:9px; left:15px; z-index:2; pointer-events:none;
            font-size:10.5px; font-weight:600; opacity:.85; letter-spacing:.04em; text-transform:uppercase;
        }
        .year-select {
            background: rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.35);
            color:#fff; font-weight:700; font-size:14px; border-radius:12px;
            padding:26px 40px 10px 14px; cursor:pointer; outline:none; min-width:210px; width:100%;
            -webkit-appearance:none; appearance:none;
            background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2.5'%3e%3cpolyline points='6 9 12 15 18 9'/%3e%3c/svg%3e");
            background-repeat:no-repeat; background-position:right 13px center; background-size:15px;
        }
        .year-select:hover { background-color:rgba(255,255,255,.24); border-color:rgba(255,255,255,.6); }
        .year-select:focus { border-color:#fff; box-shadow:0 0 0 3px rgba(255,255,255,.18); }
        .year-select option { color:#14243B; background:#fff; font-weight:600; }

        @media(max-width:720px){
            .stat-grid{grid-template-columns:repeat(2,1fr);}
            .dash-hero{padding:20px 18px;}
            .dash-hero h1{font-size:21px;}
            .dash-hero .d-flex.justify-content-between{flex-direction:column;align-items:stretch !important;gap:14px;}
            .year-switch{width:100%;}
            .year-select{width:100%;min-width:0;}
            .stat-tile{padding:13px 14px;gap:10px;}
            .stat-ic{width:40px;height:40px;font-size:1rem;}
            .stat-n{font-size:20px;}
        }

        /* Études antérieures — cartes responsives */
        .etu-card { border:1px solid var(--line); border-radius:14px; padding:14px 14px 6px; margin-bottom:12px;
            background:var(--surface-2); position:relative; }
        .etu-card .etu-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px; }
        .etu-num { font-size:12px; font-weight:700; color:var(--t2); font-family:var(--font-head); }
        .etu-grid { display:grid; grid-template-columns:repeat(12,1fr); gap:10px; }
        .etu-grid .fld { display:flex; flex-direction:column; }
        .etu-grid label { font-size:11px; color:var(--t3); margin-bottom:3px; font-weight:600; }
        .c-2 { grid-column:span 2; } .c-3 { grid-column:span 3; } .c-4 { grid-column:span 4; } .c-6 { grid-column:span 6; }
        .etu-card .form-control, .etu-card .form-select { padding:8px 10px !important; font-size:13px; }
        .etu-hors { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--t2); white-space:nowrap; }
        @media(max-width:640px){ .c-2,.c-3{ grid-column:span 6; } .c-4,.c-6{ grid-column:span 12; } }

        /* Toasts (feedback AJAX sans rechargement) */
        #toastShelf { position:fixed; right:18px; bottom:18px; z-index:1080; display:flex; flex-direction:column; gap:10px; max-width:min(360px,calc(100vw - 36px)); }
        .toast-pill { display:flex; align-items:flex-start; gap:11px; background:var(--surface); border:1px solid var(--line);
            border-left:4px solid var(--brand); border-radius:12px; padding:12px 14px; box-shadow:var(--shadow);
            transition:opacity .32s var(--ease), transform .32s var(--ease); }
        .toast-pill.toast-success { border-left-color:var(--green,#2F9E33); }
        .toast-pill.toast-danger  { border-left-color:#d33; }
        .toast-dot { width:9px; height:9px; border-radius:50%; margin-top:5px; flex-shrink:0; background:var(--brand); }
        .toast-success .toast-dot { background:var(--green,#2F9E33); }
        .toast-danger .toast-dot  { background:#d33; }
        .toast-title { font-weight:700; font-size:12.5px; color:var(--t1); }
        .toast-msg { font-size:12.5px; color:var(--t2); margin-top:2px; line-height:1.35; }
        .toast-text { flex:1; min-width:0; }
        .toast-x { background:none; border:none; color:var(--t3); cursor:pointer; font-size:13px; padding:0 2px; line-height:1; }
        .toast-x:hover { color:var(--t1); }

        /* Bouton de téléchargement du document officiel (vif, met en valeur l'action) */
        .dl-official { background:linear-gradient(135deg,#16a34a,#15803d); border:1px solid #15803d; color:#fff !important;
            box-shadow:0 3px 10px rgba(22,163,74,.35); transition:transform var(--dur) var(--ease), box-shadow var(--dur) var(--ease); }
        .dl-official:hover { transform:translateY(-1px); box-shadow:0 5px 16px rgba(22,163,74,.5); color:#fff !important; }
    </style>
@endsection

@section('content')
    <div id="toastShelf"></div>
    <div id="dashRoot" data-url="{{ route('student.dashboard') }}">
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

    {{-- ═══ Hero ═══ --}}
    <div class="dash-hero mb-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h1>Bonjour, {{ $student->prenom }}</h1>
                <p>Bienvenue dans votre espace — déposez vos dossiers et suivez vos demandes en ligne.</p>
                <div class="hero-badges">
                    <span class="hero-chip"><i class="fas fa-id-card-clip me-1"></i>{{ Auth::user()->numero_identifiant }}</span>
                    <span class="hero-chip"><i class="fas fa-user-graduate me-1"></i>{{ $typeEtudiant === 'nouveau' ? 'Nouveau Bachelier' : 'Ancien Étudiant' }}</span>
                    @if ($student->numero_suivi)
                        <span class="hero-chip"><i class="fas fa-hashtag me-1"></i>Suivi {{ $student->numero_suivi }}</span>
                    @endif
                </div>
            </div>

            {{-- Sélecteur d'année de consultation --}}
            @if ($annees->count() > 0)
                <form method="POST" action="{{ route('student.contexte.annee') }}" class="year-switch" data-no-loading>
                    @csrf
                    <span class="year-label"><i class="fas fa-calendar-days me-1"></i>Année académique</span>
                    <select name="annee_academique_id" class="year-select" onchange="this.form.submit()"
                        title="Changer l'année consultée">
                        @foreach ($annees as $a)
                            <option value="{{ $a->id }}" @selected($anneeContexte && $anneeContexte->id === $a->id)>
                                {{ $a->libelle }}{{ $a->is_active ? ' (en cours)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </div>

    @if ($isReadOnly)
        <div class="alert alert-info d-flex align-items-start gap-2 mb-4">
            <i class="fas fa-clock-rotate-left mt-1"></i>
            <span>Vous consultez l'année <strong>{{ $anneeContexte->libelle }}</strong> en
                <strong>lecture seule</strong> : vos dossiers et fiches de cette année restent
                consultables et téléchargeables, mais aucune nouvelle opération n'est possible.
                Revenez à l'année en cours pour déposer.</span>
        </div>
    @endif

    {{-- ═══ Statistiques ═══ --}}
    <div class="stat-grid mb-2">
        @php
            // Icônes sobres et monochromes (style pro) : la couleur ne sert plus de code,
            // seul le compteur « En attente » est discrètement mis en avant.
            $tiles = [
                ['n' => $stats['total'],      'l' => 'Demandes',   'i' => 'fa-folder-open',    'attn' => false],
                ['n' => $stats['en_attente'], 'l' => 'En attente', 'i' => 'fa-hourglass-half', 'attn' => $stats['en_attente'] > 0],
                ['n' => $stats['a_corriger'], 'l' => 'À corriger', 'i' => 'fa-rotate-left',     'attn' => false],
                ['n' => $stats['validees'],   'l' => 'Validées',   'i' => 'fa-circle-check',    'attn' => false],
            ];
        @endphp
        @foreach ($tiles as $t)
            <div class="stat-tile {{ $t['attn'] ? 'stat-attn' : '' }}">
                <div class="stat-ic"><i class="fas {{ $t['i'] }}"></i></div>
                <div>
                    <div class="stat-n">{{ $t['n'] }}</div>
                    <div class="stat-l">{{ $t['l'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ═══ Actions rapides ═══ --}}
    <div class="sect-label">Actions rapides</div>
    <div class="qa-grid mb-2">
        @foreach ($cats as $key => $label)
            <a href="{{ route('student.dashboard', ['tab' => $key]) }}#st-{{ $key }}" class="qa-tile">
                <div class="qa-ic">
                    <i class="fas {{ $catIcons[$key] ?? 'fa-file' }}"></i>
                </div>
                <div class="qa-title">{{ $label }}</div>
                <div class="qa-sub">
                    @php $c = ($requestsByCategory[$key] ?? collect())->sortByDesc('created_at')->first(); @endphp
                    {{ $c ? ($slabel[$c->status] ?? 'En cours') : ($key === 'depot' ? 'Déposer mon dossier' : 'Faire une demande') }}
                </div>
            </a>
        @endforeach
    </div>

    <div class="sect-label">Mes dossiers &amp; demandes</div>

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
                /* Les pièces dépendent du niveau du dossier : un master ne
                   fournit pas ce qu'une licence fournit. Sans dossier, il
                   n'y a pas encore de niveau, donc rien à afficher. */
                $types = $req
                    ? $req->typesAAfficher()
                    : new \Illuminate\Database\Eloquent\Collection();
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
                                        @if ($isReadOnly)
                                            <span class="badge bg-secondary py-2 px-3">
                                                <i class="fas fa-lock"></i> Année clôturée — aucun dépôt pour cette année
                                            </span>
                                        @elseif (!($openCats['depot'] ?? false))
                                            @php $f = $fenetres['depot'] ?? null; @endphp
                                            <div class="d-inline-block p-3 rounded text-start"
                                                style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);max-width:420px;">
                                                <div class="fw-semibold small" style="color:var(--amber,#b45309);">
                                                    <i class="fas fa-door-closed"></i> Dépôts actuellement fermés
                                                </div>
                                                <div class="small mt-1" style="color:var(--t2);">
                                                    @if ($f && $f->date_debut && now()->startOfDay()->lt($f->date_debut))
                                                        La période ouvrira le <strong>{{ $f->date_debut->format('d/m/Y') }}</strong>@if($f->date_fin) et se fermera le <strong>{{ $f->date_fin->format('d/m/Y') }}</strong>@endif.
                                                    @elseif ($f && $f->date_fin && now()->gt($f->date_fin->copy()->endOfDay()))
                                                        La période s'est terminée le <strong>{{ $f->date_fin->format('d/m/Y') }}</strong>.
                                                    @else
                                                        Revenez lorsque l'administration ouvrira la période de dépôt.
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <a href="{{ route('student.depot.start') }}" class="btn btn-primary btn-lg">
                                                <i class="fas fa-folder-plus"></i> Créer mon dossier
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @else
                                {{-- ─── Contexte académique du dépôt ─── --}}
                                <div class="card mb-4">
                                    <div class="card-body py-3">
                                        <div class="d-flex flex-wrap gap-4 align-items-center">
                                            <div>
                                                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--t3);">Parcours</div>
                                                <div style="font-weight:600;color:var(--t1);">
                                                    {{ $req->parcours->nom ?? '—' }}
                                                    @if ($req->parcours)<span class="badge bg-secondary ms-1" style="font-size:10px;">{{ $req->parcours->grade_label }}</span>@endif
                                                </div>
                                                @if ($req->parcours)
                                                    <small style="color:var(--t2);">{{ $req->parcours->departement->faculte->nom ?? '' }}</small>
                                                @endif
                                            </div>
                                            <div>
                                                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--t3);">Année</div>
                                                <div style="font-weight:600;color:var(--t1);">{{ $req->anneeAcademique->libelle ?? '—' }}</div>
                                            </div>
                                            <div>
                                                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--t3);">Année d'études</div>
                                                <div style="font-weight:600;color:var(--t1);">
                                                    {{ \App\Models\Parcours::libelleNiveau($req->semestre, $req->parcours?->grade) ?? '—' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

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
                                                            style="color:var(--t1);">{{ $req->validator->staff_name }}</strong>.
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

                                {{-- ─── Frais de dossier (module de paiement actif) ─── --}}
                                @if (\App\Models\Paiement::moduleActif())
                                    @php
                                        $paiement = \App\Models\Paiement::where('payable_type', $req->getMorphClass())
                                            ->where('payable_id', $req->id)->orderByDesc('id')->first();
                                    @endphp
                                    @if ($paiement)
                                        @php
                                            $consignes = app(\App\Services\Paiement\PasserelleOperateur::class)->instructions($paiement);
                                        @endphp
                                        <div class="card mb-3"
                                            style="border-color:{{ $paiement->estPaye() ? 'rgba(47,158,51,.5)' : 'rgba(245,158,11,.5)' }};">
                                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <span style="color:var(--t1);font-weight:600;">
                                                    <i class="fas fa-money-bill-transfer"></i> Frais de dossier
                                                </span>
                                                <span class="badge bg-{{ $paiement->statut_badge }}">{{ $paiement->statut_label }}</span>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                                    <div class="fw-bold" style="font-size:1.4rem;color:var(--brand);">
                                                        {{ $paiement->montant_formate }}
                                                    </div>
                                                    <code class="small">{{ $paiement->reference }}</code>
                                                </div>

                                                @if ($paiement->estPaye())
                                                    <div class="alert alert-success small mb-0">
                                                        <i class="fas fa-circle-check"></i> Paiement confirmé, merci.
                                                    </div>
                                                @else
                                                    @if ($paiement->statut === 'soumis')
                                                        <div class="alert alert-warning small">
                                                            <i class="fas fa-hourglass-half"></i>
                                                            Transaction <code>{{ $paiement->transaction_id }}</code> en cours
                                                            de vérification. Vous n'avez rien d'autre à faire.
                                                        </div>
                                                    @endif
                                                    @if ($paiement->statut === 'rejete' && $paiement->motif_rejet)
                                                        <div class="alert alert-danger small">
                                                            <i class="fas fa-circle-exclamation"></i>
                                                            <strong>Versement non confirmé :</strong> {{ $paiement->motif_rejet }}
                                                        </div>
                                                    @endif

                                                    <ol class="small ps-3" style="color:var(--t2);">
                                                        @foreach ($consignes['etapes'] as $etape)
                                                            <li class="mb-1">{{ $etape }}</li>
                                                        @endforeach
                                                    </ol>

                                                    @if ($paiement->peutDeclarer())
                                                        <form method="POST"
                                                            action="{{ route('student.paiements.declarer', $paiement) }}"
                                                            class="row g-2">
                                                            @csrf
                                                            <div class="col-md-4">
                                                                <label class="form-label small">Opérateur</label>
                                                                <select name="operateur" class="form-select form-select-sm">
                                                                    @foreach (\App\Models\Paiement::$operateurs as $k => $l)
                                                                        <option value="{{ $k }}" @selected($paiement->operateur === $k)>{{ $l }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small">
                                                                    Identifiant de transaction <span class="text-danger">*</span>
                                                                </label>
                                                                <input name="transaction_id" class="form-control form-control-sm"
                                                                    placeholder="Reçu par SMS" required maxlength="80">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small">Numéro utilisé</label>
                                                                <input name="numero_payeur" class="form-control form-control-sm"
                                                                    placeholder="90 00 00 00" maxlength="30">
                                                            </div>
                                                            <div class="col-12">
                                                                <button class="btn btn-primary btn-sm w-100 mt-1">
                                                                    <i class="fas fa-paper-plane"></i> Déclarer mon paiement
                                                                </button>
                                                            </div>
                                                        </form>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @endif
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
                                                            <div class="d-flex align-items-center gap-2 p-2 rounded mt-1 piece-fichier"
                                                                style="border:1px solid var(--line-mid);background:var(--surface-2);">
                                                                <i
                                                                    class="fas {{ $file->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                                                                <span class="small piece-nom" style="color:var(--t1);"
                                                                    title="{{ $file->original_filename }}">
                                                                    {{ $file->original_filename }}
                                                                </span>
                                                                <span class="small"
                                                                    style="color:var(--t3);white-space:nowrap;">
                                                                    {{ $file->file_size_formatted }}
                                                                </span>

                                                                {{-- ✅ TÉLÉCHARGER — uniquement fichier is_stamped=true --}}
                                                                @if ($canDl)
                                                                    <a href="{{ route('student.requests.file.download', $file) }}"
                                                                        class="btn btn-sm fw-semibold dl-official"
                                                                        style="white-space:nowrap;"
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
                                                                        class="d-inline js-ajax">
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
                                                    @if ($canUpload)
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

                                {{-- ─── Études antérieures (verso de la fiche) ─── --}}
                                @php $etudes = $req->etudesAnterieures; @endphp
                                <div class="card mt-4">
                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <span style="color:var(--t1);font-weight:600;">
                                            <i class="fas fa-graduation-cap"></i> Études antérieures
                                        </span>
                                        <small style="color:var(--t2);">Ces informations rempliront le verso de votre fiche.</small>
                                    </div>
                                    <div class="card-body">
                                        @if ($req->canEdit())
                                            @php
                                                $etudesData = $etudes->map(fn ($e) => [
                                                    'annee_debut' => $e->annee_debut, 'annee_fin' => $e->annee_fin,
                                                    'universite' => $e->universite, 'domaine' => $e->domaine,
                                                    'etablissement' => $e->etablissement, 'parcours' => $e->parcours,
                                                    'credits' => $e->credits,
                                                ])->values();
                                            @endphp
                                            <div id="etudesApp"
                                                data-save-url="{{ route('student.depot.etudes', $req) }}"
                                                data-etudes='@json($etudesData)'
                                                data-ref='@json($refEtudes)'>
                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                    <small style="color:var(--t2);">
                                                        Seuls <strong>l'année</strong> et les <strong>crédits</strong> sont à saisir —
                                                        le reste se choisit dans les listes.
                                                    </small>
                                                    <span id="etudesStatus" class="small" style="color:var(--t3);"></span>
                                                </div>
                                                <div id="etudesList"></div>
                                                <button type="button" id="addEtude" class="btn btn-sm btn-outline-primary mt-1">
                                                    <i class="fas fa-plus"></i> Ajouter une année
                                                </button>
                                                <small class="d-block mt-2" style="color:var(--t3);">
                                                    <i class="fas fa-circle-info"></i> Enregistrement automatique. Laissez vide si vous n'avez aucune étude antérieure.
                                                    Cochez « hors UK » pour une étude effectuée dans un autre établissement.
                                                </small>
                                            </div>
                                        @else
                                            @if ($etudes->isEmpty())
                                                <p class="mb-0 small" style="color:var(--t2);">Aucune étude antérieure déclarée.</p>
                                            @else
                                                <div class="table-responsive">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead><tr style="font-size:12px;color:var(--t2);"><th>Période</th><th>Université</th><th>Domaine</th><th>Établissement</th><th>Parcours</th><th>Crédits</th></tr></thead>
                                                        <tbody>
                                                            @foreach ($etudes as $e)
                                                                <tr style="font-size:13px;color:var(--t1);">
                                                                    <td>{{ $e->periode ?: '—' }}</td>
                                                                    <td>{{ $e->universite ?: '—' }}</td>
                                                                    <td>{{ $e->domaine ?: '—' }}</td>
                                                                    <td>{{ $e->etablissement ?: '—' }}</td>
                                                                    <td>{{ $e->parcours ?: '—' }}</td>
                                                                    <td>{{ $e->credits ?? '—' }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
                                        @endif
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
                                        <i class="fas {{ $catIcons[$key] ?? 'fa-file' }}"
                                            style="font-size:3rem;color:rgba(16,201,122,.3);"></i>
                                        <h5 class="fw-bold mt-3" style="color:var(--t1);">{{ $label }}</h5>
                                        <p style="color:var(--t2);max-width:400px;margin:0 auto 20px;">
                                            Soumettez votre demande en fournissant les documents requis.
                                            Vous recevrez une date de rendez-vous pour le retrait.
                                        </p>
                                        @if ($isReadOnly)
                                            <span class="badge bg-secondary py-2 px-3">
                                                <i class="fas fa-lock"></i> Année clôturée — aucune demande pour cette année
                                            </span>
                                        @elseif (!($openCats[$key] ?? false))
                                            @php $f = $fenetres[$key] ?? null; @endphp
                                            <div class="d-inline-block p-3 rounded text-start"
                                                style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);max-width:420px;">
                                                <div class="fw-semibold small" style="color:var(--amber,#b45309);">
                                                    <i class="fas fa-door-closed"></i> Cette opération est actuellement fermée
                                                </div>
                                                <div class="small mt-1" style="color:var(--t2);">
                                                    @if ($f && $f->date_debut && now()->startOfDay()->lt($f->date_debut))
                                                        La période ouvrira le <strong>{{ $f->date_debut->format('d/m/Y') }}</strong>@if($f->date_fin) et se fermera le <strong>{{ $f->date_fin->format('d/m/Y') }}</strong>@endif.
                                                    @elseif ($f && $f->date_fin && now()->gt($f->date_fin->copy()->endOfDay()))
                                                        La période s'est terminée le <strong>{{ $f->date_fin->format('d/m/Y') }}</strong>.
                                                    @else
                                                        Revenez lorsque l'administration ouvrira la période.
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            @php
                                                /* Le parcours decide du niveau, et le niveau decide des
                                                   pieces a fournir : un master ne fournit pas ce qu'une
                                                   licence fournit. On le demande donc a la creation. */
                                                $parcoursOuverts = \App\Models\Parcours::with('departement.faculte')
                                                    ->where('is_active', true)->orderBy('nom')->get()
                                                    ->groupBy(fn ($p) => $p->departement?->faculte?->nom ?? 'Autres');
                                            @endphp
                                            <form method="POST" class="mx-auto" style="max-width:420px;"
                                                action="{{ route('student.requests.create') }}?tab={{ $key }}">
                                                @csrf
                                                <input type="hidden" name="category" value="{{ $key }}">

                                                <label class="form-label small text-start d-block"
                                                    for="parc-{{ $key }}" style="color:var(--t2);">
                                                    Votre parcours
                                                </label>
                                                <select name="parcours_id" id="parc-{{ $key }}"
                                                    class="form-select mb-2" required>
                                                    <option value="">— Choisissez votre parcours —</option>
                                                    @foreach ($parcoursOuverts as $facNom => $liste)
                                                        <optgroup label="{{ $facNom }}">
                                                            @foreach ($liste as $p)
                                                                <option value="{{ $p->id }}"
                                                                    @selected(old('parcours_id') == $p->id)>
                                                                    {{ $p->nom }}
                                                                    ({{ \App\Models\Parcours::$grades[$p->grade]['label'] ?? $p->grade }})
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                                <div class="small text-start mb-3" style="color:var(--t2);">
                                                    Il détermine les pièces qui vous seront demandées.
                                                </div>

                                                <button type="submit" class="btn btn-{{ $catColors[$key] ?? 'primary' }} btn-lg w-100"
                                                    data-loading-text="Création…">
                                                    <i class="fas fa-plus"></i> Créer une demande
                                                </button>
                                            </form>
                                        @endif
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
                                                            style="color:var(--t2);">{{ $req->validator->staff_name }}</strong>
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
                                                    @if ($canUpload)
                                                        <button type="button"
                                                            class="btn btn-sm btn-{{ $catColors[$key] ?? 'primary' }}"
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
                                                            class="btn btn-sm btn-{{ $catColors[$key] ?? 'primary' }}"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#up-{{ $key }}-{{ $type->id }}">
                                                            <i class="fas fa-upload"></i>
                                                        </button>
                                                    @elseif($canUpload && $file)
                                                        <div class="d-flex gap-2 flex-shrink-0">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#up-{{ $key }}-{{ $type->id }}">
                                                                <i class="fas fa-arrows-rotate"></i> Remplacer
                                                            </button>
                                                            <form method="POST"
                                                                action="{{ route('student.requests.file.delete', $file) }}?tab={{ $key }}"
                                                                class="d-inline js-ajax">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                    onclick="return confirm('Supprimer ?')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
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
                                <div class="card-header bg-{{ $catColors[$key] ?? 'primary' }} text-white">
                                    <h6 class="mb-0"><i class="fas fa-paper-plane"></i> Soumettre</h6>
                                </div>
                                <div class="card-body">
                                    @php
                                        /* Certaines pièces mettent des semaines à être délivrées, et la
                                           période, elle, se ferme. L'administration ouvre au report celles
                                           qui s'y prêtent : l'usager s'engage à les fournir plus tard et
                                           son dossier avance. Les autres restent bloquantes, comme avant. */
                                        $derogeables = $req->piecesDerogeables();
                                        $idsDerogeables = $derogeables->pluck('id')->all();
                                        $bloquantes = $req->piecesObligatoiresManquantes()
                                            ->reject(fn ($t) => in_array($t->id, $idsDerogeables, true))
                                            ->values();
                                        $dejaEngagees = $req->derogationsEnCours()->pluck('request_type_id')->all();
                                        $allOk = $bloquantes->isEmpty();
                                    @endphp
                                    <ul class="list-unstyled mb-3">
                                        @foreach ($types->where('is_required', true) as $dt)
                                            @php
                                                $f = $req->fileForType($dt);
                                                $reportable = !$f && in_array($dt->id, $idsDerogeables, true);
                                            @endphp
                                            <li class="d-flex align-items-center gap-2 mb-2">
                                                @if ($f)
                                                    <i class="fas fa-check-circle text-success small"></i>
                                                @elseif ($reportable)
                                                    <i class="fas fa-clock small" style="color:var(--amber);"
                                                        title="Peut être fournie plus tard"></i>
                                                @else
                                                    <i class="fas fa-circle small" style="color:var(--t3);opacity:.4;"></i>
                                                @endif
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
                                            Pièces encore exigées :
                                            <strong
                                                style="color:var(--t1);">{{ $bloquantes->pluck('label')->implode(', ') }}</strong>
                                        </p>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-lock"></i> Soumettre
                                        </button>
                                    @else
                                        <form method="POST" id="formSoumission-{{ $req->id }}"
                                            action="{{ route('student.requests.submit') }}?tab={{ $key }}">
                                            @csrf
                                            <input type="hidden" name="student_request_id" value="{{ $req->id }}">
                                            @if ($derogeables->isNotEmpty())
                                                {{-- Pièces que l'administration autorise à fournir plus tard --}}
                                                <div class="p-3 rounded mb-3"
                                                    style="background:var(--surface-2);border:1px solid var(--amber);">
                                                    <div class="small mb-2" style="color:var(--t2);">
                                                        <i class="fas fa-clock" style="color:var(--amber);"></i>
                                                        <strong style="color:var(--t1);">Pièces à fournir plus tard</strong>
                                                    </div>
                                                    <p class="small mb-2" style="color:var(--t2);">
                                                        Ces pièces manquent encore. Vous pouvez soumettre sans elles en
                                                        vous engageant à les déposer dès leur obtention. Elles restent
                                                        dues : votre dossier portera la mention jusqu'à leur remise.
                                                    </p>
                                                    @foreach ($derogeables as $dg)
                                                        <div class="form-check mb-1">
                                                            <input class="form-check-input" type="checkbox" name="derogations[]"
                                                                value="{{ $dg->id }}" id="derog-{{ $req->id }}-{{ $dg->id }}"
                                                                @checked(in_array($dg->id, $dejaEngagees, true))
                                                                onchange="ukMajSoumission({{ $req->id }});">
                                                            <label class="form-check-label small"
                                                                for="derog-{{ $req->id }}-{{ $dg->id }}" style="color:var(--t1);">
                                                                Je ne dispose pas encore de
                                                                <strong>{{ $dg->label }}</strong> et m'engage à la fournir.
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($isDepot)
                                                {{-- Engagement obligatoire avant soumission --}}
                                                <div class="p-3 rounded mb-3"
                                                    style="background:var(--surface-2);border:1px solid var(--line);">
                                                    <div class="small mb-2" style="color:var(--t2);">
                                                        <i class="fas fa-file-signature"></i>
                                                        <strong style="color:var(--t1);">Engagement</strong>
                                                    </div>
                                                    <p class="small mb-2" style="color:var(--t2);">
                                                        Je certifie que les informations et pièces fournies sont exactes et
                                                        authentiques. J'ai <strong>lu et approuvé</strong> les conditions
                                                        de dépôt.
                                                    </p>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="engagement"
                                                            value="1" id="engage-{{ $req->id }}"
                                                            onchange="ukMajSoumission({{ $req->id }});">
                                                        <label class="form-check-label small" for="engage-{{ $req->id }}"
                                                            style="color:var(--t1);">
                                                            Lu et approuvé
                                                        </label>
                                                    </div>
                                                </div>
                                            @endif
                                            <button type="submit" id="submitBtn-{{ $req->id }}"
                                                class="btn btn-{{ $catColors[$key] ?? 'primary' }} w-100"
                                                @disabled($isDepot || $derogeables->isNotEmpty())
                                                onclick="return confirm(@js($isDepot ? 'Confirmer la soumission de votre dépôt ?' : 'Confirmer la soumission ?'))"
                                                data-loading-text="Soumission…">
                                                <i class="fas fa-check"></i> Soumettre
                                            </button>
                                        </form>
                                    @endif
                                    @if ($isDepot && $types->where('has_stamp', true)->isNotEmpty())
                                        <div class="mt-3 pt-3 d-flex align-items-start gap-2" style="border-top:1px solid var(--line);">
                                            <i class="fas fa-stamp mt-1" style="color:var(--blue);flex-shrink:0;font-size:13px;"></i>
                                            <small style="color:var(--t2);line-height:1.5;">
                                                Les documents marqués reçoivent un
                                                <strong style="color:var(--t1);">cachet officiel</strong>
                                                et deviennent téléchargeables après validation.
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

                                    {{-- Annuler / recommencer : possible tant que le dossier est un brouillon --}}
                                    @if ($req->isDraft())
                                        <div class="mt-3 pt-3" style="border-top:1px solid var(--line);">
                                            <form method="POST" action="{{ route('student.requests.cancel', $req) }}?tab={{ $key }}"
                                                onsubmit="return confirm('Annuler ce dossier ? Les pièces déjà chargées seront supprimées et vous pourrez recommencer.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                                                    data-loading-text="Annulation…">
                                                    <i class="fas fa-trash-can"></i> Annuler et recommencer
                                                </button>
                                            </form>
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
            $types = $ar ? $ar->typesAAfficher() : new \Illuminate\Database\Eloquent\Collection();
        @endphp
        @if ($ar)
            @foreach ($types as $type)
                @php $f = $ar->fileForType($type); @endphp
                @if (!$f || !$f->is_locked)
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
                                    enctype="multipart/form-data" class="js-ajax" data-modal="up-{{ $key }}-{{ $type->id }}">
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
                                        <button type="submit" class="btn btn-{{ $catColors[$key] ?? 'primary' }} btn-sm"
                                            data-loading-text="Téléversement…">
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
    </div>{{-- /#dashRoot --}}
@endsection

@section('js')
    <script>
        /* Le bouton « Soumettre » ne s'ouvre que lorsque tout ce qui engage
           l'usager est coché : l'engagement de dépôt, et chaque pièce qu'il
           choisit de fournir plus tard. Le serveur revérifie de son côté ;
           ceci évite seulement un aller-retour pour rien. */
        function ukMajSoumission(id) {
            var form = document.getElementById('formSoumission-' + id);
            var btn = document.getElementById('submitBtn-' + id);
            if (!form || !btn) return;

            var engagement = form.querySelector('input[name="engagement"]');
            var pret = !engagement || engagement.checked;

            form.querySelectorAll('input[name="derogations[]"]').forEach(function(c) {
                if (!c.checked) pret = false;
            });

            btn.disabled = !pret;
        }

        /* « Action impossible » ne disait rien : l'usager renvoyait le même
           fichier en boucle, et personne ne pouvait diagnostiquer. On
           nomme la cause, et on donne toujours le code HTTP — c'est lui qui
           permet de savoir, à distance, où chercher. */
        function ukMessageErreur(res, data) {
            // Un message vide vaut « faux » en JavaScript : on le teste
            // explicitement, sinon on retomberait sur le repli.
            if (data && typeof data.message === 'string' && data.message.trim() !== '') {
                return data.message;
            }
            if (data && data.errors) {
                var premiere = Object.values(data.errors)[0];
                if (premiere && premiere[0]) return premiere[0];
            }

            /* Pas de réponse exploitable : elle ne vient pas de
               l'application, mais du serveur ou de son pare-feu. */
            switch (res.status) {
                case 413:
                    return 'Fichier trop volumineux pour le serveur. Réduisez sa taille '
                        + '(une numérisation en 150 dpi suffit) puis réessayez.';
                case 419:
                    return 'Votre session a expiré. Rechargez la page, puis recommencez.';
                case 429:
                    return 'Trop de tentatives rapprochées. Patientez une minute avant de réessayer.';
                case 403:
                case 406:
                    return 'Envoi refusé par le serveur (erreur ' + res.status + '). '
                        + 'Si cela se répète avec ce fichier, signalez ce code à l\'administration.';
                default:
                    return 'Envoi impossible (erreur ' + res.status + '). '
                        + 'Réessayez ; si cela persiste, signalez ce code à l\'administration.';
            }
        }

        function ukSyncSoumissions() {
            document.querySelectorAll('form[id^="formSoumission-"]').forEach(function(f) {
                ukMajSoumission(f.id.substring('formSoumission-'.length));
            });
        }

        (function() {
            // Montage (ré-exécutable après un rafraîchissement AJAX du bloc dépôt)
            function mountDash() {
                ukSyncSoumissions();
                document.querySelectorAll('#stTabs .nav-link').forEach(link => {
                    link.addEventListener('shown.bs.tab', function() {
                        const url = new URL(window.location);
                        url.searchParams.set('tab', this.dataset.tab);
                        history.replaceState(null, '', url);
                        // Ramène la barre d'onglets en haut de l'écran pour voir le contenu
                        const tabs = document.getElementById('stTabs');
                        if (tabs) {
                            const top = tabs.getBoundingClientRect().top + window.scrollY - 80;
                            if (window.scrollY > top + 4) window.scrollTo({ top, behavior: 'smooth' });
                        }
                    });
                });
                const app = document.getElementById('etudesApp');
                if (app) initEtudes(app);

                // Rendre l'onglet ACTIF visible dans la barre (défilement horizontal)
                const tabs = document.getElementById('stTabs');
                const actif = tabs?.querySelector('.nav-link.active');
                if (tabs && actif) {
                    tabs.scrollLeft = Math.max(0, actif.offsetLeft - tabs.clientWidth / 2 + actif.clientWidth / 2);
                }
            }

            function initEtudes(root) {
                const SAVE_URL = root.dataset.saveUrl;
                const REF = JSON.parse(root.dataset.ref || '{}');
                const DATA = JSON.parse(root.dataset.etudes || '[]');
                const list = root.querySelector('#etudesList');
                const statusEl = root.querySelector('#etudesStatus');
                const MIN_ROWS = 4;
                const SELECT_FIELDS = { universite: REF.universites || [], domaine: REF.domaines || [], etablissement: REF.etablissements || [], parcours: REF.parcours || [] };

                const inList = (field, val) => val && (SELECT_FIELDS[field] || []).some(o => o.value === val);
                const esc = s => (s ?? '').toString().replace(/"/g, '&quot;');

                function fieldSelect(field, val, hors) {
                    const opts = SELECT_FIELDS[field] || [];
                    if (hors) {
                        return `<input class="form-control" data-f="${field}" value="${esc(val)}" placeholder="Saisir (hors UK)…">`;
                    }
                    let html = `<select class="form-select" data-f="${field}"><option value="">— Choisir —</option>`;
                    opts.forEach(o => { html += `<option value="${esc(o.value)}" ${o.value === val ? 'selected' : ''}>${esc(o.label)}</option>`; });
                    html += `</select>`;
                    return html;
                }

                function cardHtml(e, idx) {
                    const hors = !!(e.universite && !inList('universite', e.universite)) ||
                                 !!(e.domaine && !inList('domaine', e.domaine)) ||
                                 !!(e.etablissement && !inList('etablissement', e.etablissement)) ||
                                 !!(e.parcours && !inList('parcours', e.parcours));
                    const c = document.createElement('div');
                    c.className = 'etu-card';
                    c.dataset.hors = hors ? '1' : '0';
                    c.innerHTML = `
                        <div class="etu-head">
                            <span class="etu-num">Étude ${idx + 1}</span>
                            <div class="d-flex align-items-center gap-3">
                                <label class="etu-hors"><input type="checkbox" class="form-check-input mt-0 chk-hors" ${hors ? 'checked' : ''}> Étude hors UK</label>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-del" title="Retirer"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <div class="etu-grid">
                            <div class="fld c-3"><label>Année début</label><input class="form-control" data-f="annee_debut" value="${esc(e.annee_debut)}" maxlength="4" inputmode="numeric" placeholder="2024"></div>
                            <div class="fld c-3"><label>Année fin</label><input class="form-control" data-f="annee_fin" value="${esc(e.annee_fin)}" maxlength="4" inputmode="numeric" placeholder="2025"></div>
                            <div class="fld c-3"><label>Crédits</label><input class="form-control" data-f="credits" value="${esc(e.credits)}" inputmode="numeric" placeholder="60"></div>
                            <div class="fld c-3"><label>Université</label><span class="wrap-universite">${fieldSelect('universite', e.universite, hors)}</span></div>
                            <div class="fld c-6"><label>Domaine</label><span class="wrap-domaine">${fieldSelect('domaine', e.domaine, hors)}</span></div>
                            <div class="fld c-6"><label>Établissement</label><span class="wrap-etablissement">${fieldSelect('etablissement', e.etablissement, hors)}</span></div>
                            <div class="fld c-12" style="grid-column:span 12;"><label>Parcours</label><span class="wrap-parcours">${fieldSelect('parcours', e.parcours, hors)}</span></div>
                        </div>`;
                    return c;
                }

                function collect() {
                    return [...list.querySelectorAll('.etu-card')].map(card => {
                        const o = {};
                        card.querySelectorAll('[data-f]').forEach(el => o[el.dataset.f] = el.value.trim());
                        return o;
                    }).filter(o => Object.values(o).some(v => v));
                }

                let timer = null, saving = false;
                function scheduleSave() {
                    statusEl.textContent = 'Modifié…';
                    clearTimeout(timer);
                    timer = setTimeout(save, 900);
                }
                async function save() {
                    if (saving) { scheduleSave(); return; }
                    saving = true;
                    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement…';
                    const rows = collect();
                    const body = new FormData();
                    body.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');
                    rows.forEach((r, i) => Object.entries(r).forEach(([k, v]) => body.append(`lignes[${i}][${k}]`, v)));
                    try {
                        const res = await fetch(SAVE_URL, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body });
                        statusEl.innerHTML = res.ok ? '<i class="fas fa-check text-success"></i> Enregistré' : '<i class="fas fa-triangle-exclamation text-warning"></i> Non enregistré';
                    } catch (e) {
                        statusEl.innerHTML = '<i class="fas fa-triangle-exclamation text-warning"></i> Hors ligne';
                    }
                    saving = false;
                }

                function ensureTrailingEmpty() {
                    const cards = list.querySelectorAll('.etu-card');
                    const last = cards[cards.length - 1];
                    const lastFilled = last && [...last.querySelectorAll('[data-f]')].some(el => el.value.trim());
                    if (!last || lastFilled) addCard({});
                    renumber();
                }
                function renumber() { list.querySelectorAll('.etu-num').forEach((n, i) => n.textContent = 'Étude ' + (i + 1)); }

                function addCard(e) {
                    const card = cardHtml(e, list.querySelectorAll('.etu-card').length);
                    list.appendChild(card);
                    wire(card);
                }
                function wire(card) {
                    card.addEventListener('input', () => { scheduleSave(); ensureTrailingEmpty(); });
                    card.addEventListener('change', () => scheduleSave());
                    card.querySelector('.btn-del').addEventListener('click', () => {
                        card.remove();
                        if (!list.querySelector('.etu-card')) addCard({});
                        renumber(); ensureTrailingEmpty(); scheduleSave();
                    });
                    card.querySelector('.chk-hors').addEventListener('change', function () {
                        const hors = this.checked; card.dataset.hors = hors ? '1' : '0';
                        ['universite', 'domaine', 'etablissement', 'parcours'].forEach(f => {
                            const wrap = card.querySelector('.wrap-' + f);
                            const cur = wrap.querySelector('[data-f]').value;
                            wrap.innerHTML = fieldSelect(f, cur, hors);
                        });
                        scheduleSave();
                    });
                }

                // Rendu initial : données existantes + lignes vides jusqu'à MIN_ROWS
                const initial = DATA.length ? DATA.slice() : [];
                while (initial.length < MIN_ROWS) initial.push({});
                initial.forEach(e => addCard(e));
                ensureTrailingEmpty();

                document.getElementById('addEtude').addEventListener('click', () => { addCard({}); renumber(); });
            }

            /* ── Toast minimal ── */
            function toast(msg, type) {
                const shelf = document.getElementById('toastShelf');
                if (!shelf) { return; }
                const el = document.createElement('div');
                el.className = 'toast-pill toast-' + (type || 'info');
                el.innerHTML = '<div class="toast-dot"></div><div class="toast-text"><div class="toast-title">' +
                    (type === 'danger' ? 'Erreur' : 'Succès') + '</div><div class="toast-msg">' + msg + '</div></div>' +
                    '<button class="toast-x" onclick="this.closest(\'.toast-pill\').remove()"><i class="fas fa-xmark"></i></button>';
                shelf.appendChild(el);
                setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateY(10px)'; setTimeout(() => el.remove(), 360); }, 4000);
            }

            /* ── Rafraîchissement ciblé du bloc dépôt (sans recharger la page) ── */
            let reloading = false;
            async function reloadDash() {
                if (reloading) return;
                reloading = true;
                const y = window.scrollY;
                const root = document.getElementById('dashRoot');
                const url = new URL(root.dataset.url, window.location.origin);
                // Rester sur l'ONGLET COURANT (bug : revenait toujours au 1er onglet)
                const tabCourant = new URLSearchParams(window.location.search).get('tab')
                    || document.querySelector('#stTabs .nav-link.active')?.dataset.tab
                    || 'depot';
                url.searchParams.set('tab', tabCourant);
                try {
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const html = await res.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const fresh = doc.getElementById('dashRoot');
                    if (fresh) {
                        root.innerHTML = fresh.innerHTML;
                        mountDash();
                        window.scrollTo(0, y);
                    }
                } catch (e) { /* silencieux */ }
                reloading = false;
            }

            /* ── Interception AJAX des formulaires d'upload / suppression ── */
            document.addEventListener('submit', function (e) {
                const form = e.target;
                if (!form.classList || !form.classList.contains('js-ajax')) return;
                e.preventDefault();
                e.stopPropagation();
                const btn = form.querySelector('button[type="submit"], button:not([type])');
                if (btn) { btn.disabled = true; btn.dataset._h = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
                const modalId = form.dataset.modal;
                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new FormData(form)
                }).then(async res => {
                    let data = {};
                    try { data = await res.json(); } catch (_) {}
                    if (res.ok) {
                        if (modalId) { const m = bootstrap.Modal.getInstance(document.getElementById(modalId)); if (m) m.hide(); }
                        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                        document.body.classList.remove('modal-open'); document.body.style.removeProperty('overflow'); document.body.style.removeProperty('padding-right');
                        toast(data.message || 'Enregistré.', 'success');
                        await reloadDash();
                    } else {
                        toast(ukMessageErreur(res, data), 'danger');
                        if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset._h; }
                    }
                }).catch(() => {
                    toast('Connexion interrompue.', 'danger');
                    if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset._h; }
                });
            }, true); // capture : passe avant le handler global de chargement

            mountDash();
        })();
    </script>
@endsection
