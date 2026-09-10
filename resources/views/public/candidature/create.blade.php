@extends('layouts.app')
@section('title', 'Candidature en ligne')

@section('css')
    <style>
        /* Une marge automatique centre verticalement sans rogner le haut
           quand le formulaire dépasse l'écran : les marges auto neutralisent
           l'étirement, là où « flex-start » collait tout en haut. */
        body.guest-mode .content { align-items: stretch !important; padding-top: 20px; padding-bottom: 40px; }
        .cand-wrap { width: 100%; max-width: 1280px; margin-block: auto; position: relative; z-index: 1; }
        /* Candidatures fermées : la page ne porte qu'un message et un bouton.
           1280 px de large le laissaient flotter dans le vide. */
        .cand-wrap.cand-message { max-width: 520px; }

        .cand-grid { display: grid; grid-template-columns: 1fr; gap: 20px; align-items: start; }
        @media (min-width: 992px)  { .cand-grid { grid-template-columns: minmax(0, 1fr) 340px; } }
        @media (min-width: 1400px) { .cand-grid { grid-template-columns: minmax(0, 1fr) 400px; } }
        .cand-recap { position: sticky; top: 16px; }
        /* Sur mobile la synthèse passe EN PREMIER : reléguée en bas, elle
           n'était jamais vue. */
        @media (max-width: 991.98px) {
            .cand-recap { position: static; order: -1; }
        }

        /* Champs de saisie : au-delà d'une certaine largeur, un champ
           devient inconfortable à lire — on borne plutôt que d'étirer. */
        @media (min-width: 992px) { .champs-serres { max-width: 720px; } }

        /* Aide « comment ça marche » sous le récapitulatif */
        .aide-e { display: flex; gap: 10px; padding: 9px 0; border-bottom: 1px dashed var(--line); }
        .aide-e:last-child { border-bottom: none; }
        .aide-n { width: 22px; height: 22px; border-radius: 7px; background: var(--em-dim);
            color: var(--brand); font-weight: 800; font-size: 11px; display: flex;
            align-items: center; justify-content: center; flex-shrink: 0; }
        .aide-t { font-size: 12px; font-weight: 600; color: var(--t1); line-height: 1.3; }
        .aide-d { font-size: 11px; color: var(--t3); line-height: 1.35; }

        /* ── Étapes ── */
        .etapes { display: flex; gap: 6px; margin-bottom: 18px; }
        .etape { flex: 1; text-align: center; padding: 10px 6px; border-radius: 12px; cursor: pointer;
            background: var(--surface-2); border: 1px solid var(--line); transition: all .18s; }
        .etape:hover { border-color: var(--brand); }
        .etape.on { background: var(--brand); border-color: var(--brand); box-shadow: 0 6px 16px var(--em-glow); }
        .etape.on .etape-l, .etape.on .etape-n { color: #fff; }
        .etape.fait { border-color: #2F9E33; background: rgba(47, 158, 51, .09); }
        .etape-n { font-weight: 800; font-size: 15px; line-height: 1; color: var(--t1); }
        .etape-l { font-size: 11.5px; font-weight: 600; margin-top: 4px; line-height: 1.25; color: var(--t2); }
        .etape.fait .etape-n span { display: none; }
        .etape.fait .etape-n::before { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; color: #2F9E33; }
        .volet { display: none; }
        .volet.on { display: block; animation: apparait .22s ease; }
        @keyframes apparait { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        /* ── Pièces ── */
        .pieces-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
        @media (min-width: 768px) { .pieces-grid { grid-template-columns: 1fr 1fr; } }
        .piece { border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; background: var(--surface); }
        .piece.ok { border-color: #2F9E33; background: rgba(47, 158, 51, .05); }
        .piece.err { border-color: #DC2626; background: rgba(220, 38, 38, .05); }
        .piece-head { display: flex; align-items: flex-start; gap: 10px; }
        .piece-ic { width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0; display: flex;
            align-items: center; justify-content: center; background: var(--em-dim); color: var(--brand); font-size: 12px; }
        .piece.ok .piece-ic { background: #2F9E33; color: #fff; }
        .piece.err .piece-ic { background: #DC2626; color: #fff; }
        .piece-name { font-size: 13px; font-weight: 600; color: var(--t1); line-height: 1.3; }
        .piece-meta { font-size: 11px; color: var(--t3); }
        .piece input[type=file] { margin-top: 8px; font-size: 12px; }
        .piece-msg { font-size: 11.5px; margin-top: 6px; }

        /* Groupes de pièces : « pour tous » puis un par parcours */
        .grp { border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; margin-bottom: 14px;
            background: var(--surface-2); }
        .grp-titre { font-size: 13px; font-weight: 700; color: var(--t1); margin-bottom: 3px; }
        .grp-sous { font-size: 11.5px; color: var(--t3); margin-bottom: 10px; }
        .grp.pour-tous { border-left: 3px solid var(--brand); }
        .grp.pour-parcours { border-left: 3px solid #f59e0b; }

        /* ── Parcours ── */
        .sections-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
        @media (min-width: 992px) { .sections-grid { grid-template-columns: 1fr 1fr; } }
        .sec-parcours { border: 1px solid var(--line); border-top: 3px solid var(--brand);
            border-radius: 14px; padding: 14px 16px; background: var(--surface); }
        .sec-parcours.vide { border-top-color: var(--line-mid); }
        .sec-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
        .sec-num { width: 26px; height: 26px; border-radius: 8px; background: var(--em-dim); color: var(--brand);
            font-weight: 800; font-size: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .sec-titre { font-weight: 700; color: var(--t1); font-size: 13.5px; }
        .sec-ajout { border: 2px dashed var(--line-mid); border-radius: 14px; min-height: 118px;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
            cursor: pointer; color: var(--brand); background: transparent; transition: all .18s; width: 100%; }
        .sec-ajout:hover { border-color: var(--brand); background: var(--em-dim); }

        /* ── Vérification ── */
        .verif-l { display: flex; gap: 10px; align-items: flex-start; padding: 10px 0;
            border-bottom: 1px dashed var(--line); }
        .verif-l:last-child { border-bottom: none; }

        /* ── Récapitulatif ── */
        .recap-l { display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px dashed var(--line); font-size: 12.5px; }
        .recap-l:last-child { border-bottom: none; }
        .recap-v { font-weight: 700; color: var(--t1); }
        .prog-bar { height: 7px; border-radius: 99px; background: var(--line-mid); overflow: hidden; margin-top: 6px; }
        .prog-fill { height: 100%; width: 0; background: linear-gradient(90deg, var(--brand), #2F9E33);
            border-radius: 99px; transition: width .25s ease; }

        .send-ov { position: fixed; inset: 0; background: rgba(15, 23, 42, .72); backdrop-filter: blur(3px);
            display: none; align-items: center; justify-content: center; z-index: 2000; }
        .send-ov.on { display: flex; }
        .send-box { background: var(--surface); border-radius: 16px; padding: 26px 30px; width: min(360px, 88vw);
            text-align: center; box-shadow: 0 18px 50px rgba(0, 0, 0, .35); }
        .send-pct { font-family: var(--font-head); font-size: 30px; font-weight: 800; color: var(--brand); }
    </style>
@endsection

@section('content')
    <div class="cand-wrap {{ $ouverte ? '' : 'cand-message' }}">
        <div class="text-center mb-3">
            <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" style="height:46px;" alt="Université de Kara">
            <h1 style="font-family:var(--font-head);font-size:21px;font-weight:800;margin-top:10px;color:var(--t1);">Candidature en ligne</h1>
            <p style="color:var(--t2);font-size:13px;margin-bottom:0;">Sans créer de compte — un numéro de suivi vous sera remis.</p>
        </div>

        @if (!$ouverte)
            <div class="card"><div class="card-body text-center py-5">
                <i class="fas fa-door-closed" style="font-size:2.5rem;color:var(--t3);"></i>
                <h5 class="mt-3" style="color:var(--t1);">Candidatures fermées</h5>
                <p class="text-muted">Les candidatures ne sont pas ouvertes actuellement. Revenez pendant la période d'admission.</p>
                <a href="{{ route('login') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
            </div></div>
        @else
            @php
                $communes    = $types->filter(fn($t) => $t->parcours->isEmpty());
                $specifiques = $types->filter(fn($t) => $t->parcours->isNotEmpty());
            @endphp

            <form method="POST" action="{{ route('candidature.store') }}" enctype="multipart/form-data" id="candForm">
                @csrf
                <div class="cand-grid">
                    <div>
                        <div class="etapes">
                            <div class="etape on" data-etape="0"><div class="etape-n"><span>1</span></div>
                                <div class="etape-l">Que voulez-vous étudier ?</div></div>
                            <div class="etape" data-etape="1"><div class="etape-n"><span>2</span></div>
                                <div class="etape-l">Qui êtes-vous ?</div></div>
                            <div class="etape" data-etape="2"><div class="etape-n"><span>3</span></div>
                                <div class="etape-l">Vos pièces</div></div>
                            <div class="etape" data-etape="3"><div class="etape-n"><span>4</span></div>
                                <div class="etape-l">Vérification</div></div>
                        </div>

                        {{-- ══ Étape 1 : le parcours d'abord ══ --}}
                        <div class="volet on" data-volet="0">
                            <div class="card"><div class="card-body">
                                <h6 class="fw-bold mb-1" style="color:var(--brand);">
                                    <i class="fas fa-graduation-cap"></i> Que voulez-vous étudier ?
                                </h6>
                                <p class="small text-muted mb-3">
                                    Chaque parcours est une <strong>candidature à part entière</strong>, instruite séparément.
                                    Ajoutez-en autant que vous voulez — les pièces à fournir s'adapteront.
                                </p>
                                <div class="sections-grid" id="sections"></div>
                                <button type="button" class="sec-ajout mt-3" id="btnAjoutSection">
                                    <i class="fas fa-plus-circle fs-4"></i>
                                    <span class="fw-semibold">Ajouter un parcours</span>
                                </button>
                            </div></div>
                        </div>

                        {{-- ══ Étape 2 : identité ══ --}}
                        <div class="volet" data-volet="1">
                            <div class="card"><div class="card-body">
                                <h6 class="fw-bold mb-3" style="color:var(--brand);"><i class="fas fa-user"></i> Qui êtes-vous ?</h6>
                                <div class="row g-3 champs-serres">
                                    <div class="col-md-6"><label class="form-label">Nom *</label>
                                        <input class="form-control" name="nom" value="{{ old('nom') }}" required></div>
                                    <div class="col-md-6"><label class="form-label">Prénom(s) *</label>
                                        <input class="form-control" name="prenom" value="{{ old('prenom') }}" required></div>
                                    <div class="col-md-4"><label class="form-label">Sexe *</label>
                                        <select class="form-select" name="sexe" required>
                                            <option value="">—</option>
                                            <option value="M" @selected(old('sexe') == 'M')>Masculin</option>
                                            <option value="F" @selected(old('sexe') == 'F')>Féminin</option>
                                        </select></div>
                                    <div class="col-md-4"><label class="form-label">Date de naissance *</label>
                                        <input type="date" class="form-control" name="date_naissance" value="{{ old('date_naissance') }}" required></div>
                                    <div class="col-md-4"><label class="form-label">Lieu de naissance *</label>
                                        <input class="form-control" name="lieu_naissance" value="{{ old('lieu_naissance') }}" required></div>
                                    <div class="col-md-6"><label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email" value="{{ old('email') }}" required>
                                        <small class="piece-meta">La décision vous sera communiquée à cette adresse.</small></div>
                                    <div class="col-md-6"><label class="form-label">Téléphone</label>
                                        <input class="form-control" name="telephone" value="{{ old('telephone') }}"
                                            placeholder="90 00 00 00"></div>
                                </div>
                            </div></div>
                        </div>

                        {{-- ══ Étape 3 : pièces, regroupées ══ --}}
                        <div class="volet" data-volet="2">
                            <div class="card"><div class="card-body">
                                <h6 class="fw-bold mb-1" style="color:var(--brand);"><i class="fas fa-paperclip"></i> Vos pièces</h6>
                                <p class="small text-muted mb-3">
                                    Voici <strong>exactement</strong> ce qui vous est demandé, d'après les parcours choisis.
                                </p>

                                @if ($communes->count())
                                    <div class="grp pour-tous">
                                        <div class="grp-titre"><i class="fas fa-layer-group"></i> Pour l'ensemble de votre candidature</div>
                                        <div class="grp-sous" id="sousCommun">Fournies une seule fois.</div>
                                        <div class="pieces-grid">
                                            @foreach ($communes as $t)
                                                <div class="piece" id="piece-{{ $t->id }}">
                                                    <div class="piece-head">
                                                        <div class="piece-ic"><i class="fas fa-file-arrow-up"></i></div>
                                                        <div class="flex-grow-1">
                                                            <div class="piece-name">{{ $t->label }}
                                                                @if ($t->is_required)<span class="text-danger">*</span>@else <span class="piece-meta">(optionnel)</span>@endif
                                                            </div>
                                                            @if ($t->description)<div class="piece-meta">{{ $t->description }}</div>@endif
                                                        </div>
                                                        <span class="piece-meta text-nowrap" data-etat>—</span>
                                                    </div>
                                                    <input type="file" class="form-control form-control-sm" name="files[{{ $t->id }}]"
                                                        accept=".pdf,.jpg,.jpeg,.png" data-req="{{ $t->is_required ? 1 : 0 }}">
                                                    <div class="piece-msg text-danger" data-err style="display:none;"></div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Groupes par parcours, construits selon les choix --}}
                                <div id="grpParcours"></div>
                            </div></div>
                        </div>

                        {{-- ══ Étape 4 : vérification ══ --}}
                        <div class="volet" data-volet="3">
                            <div class="card"><div class="card-body">
                                <h6 class="fw-bold mb-1" style="color:var(--brand);"><i class="fas fa-list-check"></i> Vérification</h6>
                                <p class="small text-muted mb-3">Relisez avant d'envoyer — vous ne pourrez plus modifier ensuite.</p>
                                <div id="verifContenu"></div>
                            </div></div>
                        </div>

                        <div class="alert alert-danger py-2 small mt-3" id="candAlerte" style="display:none;"></div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary" id="btnPrec" style="display:none;">
                                <i class="fas fa-arrow-left"></i> Précédent
                            </button>
                            <button type="button" class="btn btn-primary flex-grow-1" id="btnSuiv">
                                Continuer <i class="fas fa-arrow-right"></i>
                            </button>
                            <button type="submit" class="btn btn-primary flex-grow-1" id="btnEnvoyer" style="display:none;height:46px;">
                                <i class="fas fa-paper-plane"></i> Soumettre ma candidature
                            </button>
                        </div>
                    </div>

                    {{-- ══ Récapitulatif permanent ══ --}}
                    <div class="cand-recap">
                        <div class="card">
                            <div class="card-header"><i class="fas fa-clipboard-list"></i> Votre candidature</div>
                            <div class="card-body">
                                <div class="small fw-semibold mb-1" style="color:var(--t1);">Avancement</div>
                                <div class="prog-bar"><div class="prog-fill" id="progFill"></div></div>
                                <div class="piece-meta mt-1" id="progTxt">0 / 0 pièce(s)</div>

                                <div class="mt-3">
                                    <div class="recap-l"><span style="color:var(--t2);">Parcours visés</span>
                                        <span class="recap-v" id="rcParcours">0</span></div>
                                    <div class="recap-l"><span style="color:var(--t2);">Pièces à fournir</span>
                                        <span class="recap-v" id="rcPieces">0</span></div>
                                </div>
                                <div id="rcListe" class="mt-2"></div>

                                <div class="alert alert-info small mt-3 mb-0 py-2">
                                    <i class="fas fa-circle-info"></i> PDF, JPG ou PNG — 5 Mo maximum par pièce.
                                </div>
                            </div>
                        </div>

                        {{-- Marche à suivre, toujours visible --}}
                        <div class="card mt-3 d-none d-lg-block">
                            <div class="card-header"><i class="fas fa-circle-question"></i> Comment ça marche</div>
                            <div class="card-body py-2">
                                <div class="aide-e">
                                    <div class="aide-n">1</div>
                                    <div><div class="aide-t">Choisissez vos parcours</div>
                                        <div class="aide-d">Chacun est une candidature distincte, instruite séparément.</div></div>
                                </div>
                                <div class="aide-e">
                                    <div class="aide-n">2</div>
                                    <div><div class="aide-t">Renseignez votre identité</div>
                                        <div class="aide-d">Une seule fois, quel que soit le nombre de parcours.</div></div>
                                </div>
                                <div class="aide-e">
                                    <div class="aide-n">3</div>
                                    <div><div class="aide-t">Joignez vos pièces</div>
                                        <div class="aide-d">Les pièces communes ne sont demandées qu'une fois ; chaque parcours ajoute les siennes.</div></div>
                                </div>
                                <div class="aide-e">
                                    <div class="aide-n">4</div>
                                    <div><div class="aide-t">Relisez puis envoyez</div>
                                        <div class="aide-d">Un numéro de suivi vous est remis : gardez-le, il donne accès à votre dossier.</div></div>
                                </div>
                                <div class="aide-e">
                                    <div class="aide-n"><i class="fas fa-lock"></i></div>
                                    <div><div class="aide-t">Besoin d'aide ?</div>
                                        <div class="aide-d">Rapprochez-vous de la scolarité muni de votre numéro de suivi.</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Modèle : une section de parcours (étape 1) --}}
            <template id="tplSection">
                <div class="sec-parcours vide" data-section>
                    <div class="sec-head">
                        <div class="d-flex align-items-center gap-2">
                            <div class="sec-num" data-num>1</div>
                            <span class="sec-titre" data-nom>Parcours visé</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-retirer title="Retirer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <select class="form-select form-select-sm" data-parcours required>
                        <option value="">— Choisissez un parcours —</option>
                        @foreach ($facultes as $fac)
                            @foreach ($fac->departements as $dep)
                                @if ($dep->parcours->count())
                                    <optgroup label="{{ $fac->sigle ?? $fac->nom }} · {{ $dep->nom }}">
                                        @foreach ($dep->parcours as $p)
                                            <option value="{{ $p->id }}">{{ $p->nom }} ({{ $p->grade_label }})</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        @endforeach
                    </select>
                    <div class="piece-msg text-danger mt-2" data-doublon style="display:none;">
                        <i class="fas fa-circle-exclamation"></i> Ce parcours est déjà dans votre candidature.
                    </div>
                    <div class="piece-meta mt-2" data-info></div>
                </div>
            </template>

            {{-- Modèle : un groupe de pièces propre à un parcours (étape 3) --}}
            <template id="tplGroupe">
                <div class="grp pour-parcours" data-groupe>
                    <div class="grp-titre"><i class="fas fa-code-branch"></i> <span data-nom></span></div>
                    <div class="grp-sous">Pièces réclamées uniquement par ce parcours.</div>
                    <div class="pieces-grid" data-pieces></div>
                </div>
            </template>

            {{-- Modèle : une pièce spécifique --}}
            <template id="tplPieceSpec">
                @foreach ($specifiques as $t)
                    <div class="piece piece-spec" data-type="{{ $t->id }}"
                        data-parcours-cibles="{{ $t->parcours->pluck('id')->implode(',') }}"
                        data-required="{{ $t->is_required ? 1 : 0 }}">
                        <div class="piece-head">
                            <div class="piece-ic"><i class="fas fa-code-branch"></i></div>
                            <div class="flex-grow-1">
                                <div class="piece-name">{{ $t->label }}
                                    @if ($t->is_required)<span class="text-danger">*</span>@else <span class="piece-meta">(optionnel)</span>@endif
                                </div>
                                @if ($t->description)<div class="piece-meta">{{ $t->description }}</div>@endif
                            </div>
                            <span class="piece-meta text-nowrap" data-etat>—</span>
                        </div>
                        <input type="file" class="form-control form-control-sm" data-fichier accept=".pdf,.jpg,.jpeg,.png">
                        <div class="piece-msg text-danger" data-err style="display:none;"></div>
                    </div>
                @endforeach
            </template>

            <div class="send-ov" id="sendOv">
                <div class="send-box">
                    <div class="send-pct" id="sendPct">0%</div>
                    <div class="small mt-1" style="color:var(--t2);" id="sendTxt">Envoi de vos pièces…</div>
                    <div class="prog-bar mt-3"><div class="prog-fill" id="sendFill"></div></div>
                </div>
            </div>

            <script>
                (function () {
                    const form = document.getElementById('candForm');
                    if (!form) return;

                    const zone = document.getElementById('sections');
                    const zoneGrp = document.getElementById('grpParcours');
                    const tplSec = document.getElementById('tplSection');
                    const tplGrp = document.getElementById('tplGroupe');
                    const tplPiece = document.getElementById('tplPieceSpec');
                    const alerte = document.getElementById('candAlerte');
                    const etapes = [...document.querySelectorAll('.etape')];
                    const volets = [...document.querySelectorAll('.volet')];
                    const btnPrec = document.getElementById('btnPrec');
                    const btnSuiv = document.getElementById('btnSuiv');
                    const btnEnv = document.getElementById('btnEnvoyer');
                    const DERNIERE = 3;
                    let courante = 0;

                    // Les groupes de pièces sont conservés d'un passage à l'autre :
                    // revenir en arrière ne doit jamais faire perdre un fichier déjà choisi.
                    const groupes = new Map();   // parcoursId -> élément

                    const koctets = o => o < 1024 ? o + ' o'
                        : (o < 1048576 ? (o / 1024).toFixed(0) + ' Ko' : (o / 1048576).toFixed(1) + ' Mo');

                    /* ── Étapes ── */
                    function afficher(i) {
                        courante = Math.max(0, Math.min(DERNIERE, i));
                        volets.forEach((v, k) => v.classList.toggle('on', k === courante));
                        etapes.forEach((e, k) => {
                            e.classList.toggle('on', k === courante);
                            e.classList.toggle('fait', k < courante);
                        });
                        btnPrec.style.display = courante > 0 ? '' : 'none';
                        btnSuiv.style.display = courante < DERNIERE ? '' : 'none';
                        btnEnv.style.display = courante === DERNIERE ? '' : 'none';
                        if (courante === 2) construireGroupes();
                        if (courante === DERNIERE) construireVerification();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }

                    function parcoursChoisis() {
                        return [...zone.querySelectorAll('[data-section]')]
                            .map(s => s.querySelector('[data-parcours]'))
                            .filter(sel => sel.value)
                            .map(sel => ({ id: sel.value, nom: sel.options[sel.selectedIndex].text }));
                    }

                    /** L'étape des parcours doit être remplie avant tout le reste. */
                    function etapeParcoursValide() {
                        const choisis = parcoursChoisis();
                        if (!choisis.length) {
                            alerte.style.display = '';
                            alerte.textContent = 'Choisissez au moins un parcours pour continuer.';
                            return false;
                        }
                        if (!verifierDoublons()) {
                            alerte.style.display = '';
                            alerte.textContent = 'Vous avez sélectionné deux fois le même parcours.';
                            return false;
                        }
                        alerte.style.display = 'none';
                        return true;
                    }

                    function etapeIdentiteValide() {
                        for (const c of volets[1].querySelectorAll('input[required], select[required]')) {
                            if (!c.value) { c.reportValidity(); return false; }
                        }
                        return true;
                    }

                    function peutAllerA(k) {
                        if (k >= 1 && !etapeParcoursValide()) { afficher(0); return false; }
                        if (k >= 2 && !etapeIdentiteValide()) { afficher(1); return false; }
                        return true;
                    }

                    etapes.forEach((e, k) => e.addEventListener('click', () => { if (peutAllerA(k)) afficher(k); }));
                    btnSuiv.addEventListener('click', () => { if (peutAllerA(courante + 1)) afficher(courante + 1); });
                    btnPrec.addEventListener('click', () => afficher(courante - 1));

                    /* ── Pièces ── */
                    function majPiece(input) {
                        const bloc = input.closest('.piece');
                        if (!bloc) return;
                        const f = input.files && input.files[0];
                        bloc.classList.toggle('ok', !!f);
                        bloc.classList.remove('err');
                        const err = bloc.querySelector('[data-err]');
                        if (err) { err.style.display = 'none'; err.textContent = ''; }
                        const etat = bloc.querySelector('[data-etat]');
                        if (etat) etat.innerHTML = f
                            ? '<i class="fas fa-circle-check text-success"></i> ' + koctets(f.size) : '—';
                    }

                    /** Reconstruit les groupes de pièces à partir des parcours choisis. */
                    function construireGroupes() {
                        const choisis = parcoursChoisis();
                        const ids = choisis.map(c => c.id);

                        // Retirer les groupes dont le parcours n'est plus retenu
                        [...groupes.keys()].forEach(id => {
                            if (!ids.includes(id)) { groupes.get(id).remove(); groupes.delete(id); }
                        });

                        choisis.forEach((c, index) => {
                            let grp = groupes.get(c.id);
                            if (!grp) {
                                grp = tplGrp.content.firstElementChild.cloneNode(true);
                                const cible = grp.querySelector('[data-pieces]');
                                // Seules les pièces réclamées par CE parcours
                                [...tplPiece.content.querySelectorAll('.piece-spec')].forEach(modele => {
                                    const cibles = (modele.dataset.parcoursCibles || '').split(',').filter(Boolean);
                                    if (!cibles.includes(c.id)) return;
                                    const p = modele.cloneNode(true);
                                    const inp = p.querySelector('[data-fichier]');
                                    inp.addEventListener('change', () => { majPiece(inp); majRecap(); });
                                    cible.appendChild(p);
                                });
                                if (!cible.children.length) {
                                    grp.querySelector('.grp-sous').textContent =
                                        'Aucune pièce supplémentaire : les pièces communes suffisent.';
                                }
                                groupes.set(c.id, grp);
                            }
                            grp.querySelector('[data-nom]').textContent = c.nom;
                            zoneGrp.appendChild(grp);   // remet dans l'ordre des sections
                            // Les noms de champs suivent l'ordre des sections envoyé au serveur
                            grp.querySelectorAll('.piece-spec').forEach(p => {
                                p.querySelector('[data-fichier]').name =
                                    `sections[${index}][files][${p.dataset.type}]`;
                            });
                        });

                        const sous = document.getElementById('sousCommun');
                        if (sous) {
                            sous.textContent = choisis.length > 1
                                ? `Fournies une seule fois pour vos ${choisis.length} parcours.`
                                : 'Fournies une seule fois.';
                        }
                        majRecap();
                    }

                    /* ── Récapitulatif ── */
                    function majRecap() {
                        let tot = 0, fait = 0;
                        form.querySelectorAll('input[type=file]').forEach(i => {
                            if (!i.closest('.piece')) return;
                            tot++;
                            if (i.files && i.files.length) fait++;
                        });
                        const pct = tot ? Math.round(fait / tot * 100) : 0;
                        document.getElementById('progFill').style.width = pct + '%';
                        document.getElementById('progTxt').textContent = fait + ' / ' + tot + ' pièce(s) fournie(s)';
                        document.getElementById('rcPieces').textContent = tot;

                        const choisis = parcoursChoisis();
                        document.getElementById('rcParcours').textContent = choisis.length;
                        const liste = document.getElementById('rcListe');
                        liste.innerHTML = '';
                        choisis.forEach(c => {
                            const d = document.createElement('div');
                            d.className = 'piece-meta';
                            d.innerHTML = '<i class="fas fa-circle-check text-success"></i> ' + c.nom;
                            liste.appendChild(d);
                        });
                    }

                    /* ── Vérification finale ── */
                    function construireVerification() {
                        const c = document.getElementById('verifContenu');
                        const id = n => form.querySelector(`[name="${n}"]`)?.value || '—';
                        const choisis = parcoursChoisis();

                        let manque = [];
                        form.querySelectorAll('input[type=file]').forEach(i => {
                            const bloc = i.closest('.piece');
                            if (!bloc) return;
                            const oblig = i.dataset.req === '1' || bloc.dataset.required === '1';
                            if (oblig && !(i.files && i.files.length)) {
                                manque.push(bloc.querySelector('.piece-name').textContent.trim().replace('*', ''));
                            }
                        });

                        c.innerHTML =
                            '<div class="verif-l"><i class="fas fa-user text-primary mt-1"></i><div>'
                            + '<div class="fw-semibold" style="color:var(--t1);">' + id('nom') + ' ' + id('prenom') + '</div>'
                            + '<div class="piece-meta">Né(e) le ' + id('date_naissance') + ' à ' + id('lieu_naissance')
                            + ' · ' + id('email') + (id('telephone') !== '—' ? ' · ' + id('telephone') : '') + '</div>'
                            + '</div></div>'
                            + '<div class="verif-l"><i class="fas fa-graduation-cap text-primary mt-1"></i><div>'
                            + '<div class="fw-semibold" style="color:var(--t1);">' + choisis.length + ' parcours visé(s)</div>'
                            + '<div class="piece-meta">' + (choisis.map(x => x.nom).join('<br>') || '—') + '</div>'
                            + '</div></div>'
                            + '<div class="verif-l"><i class="fas fa-paperclip text-primary mt-1"></i><div>'
                            + '<div class="fw-semibold" style="color:var(--t1);">Pièces</div>'
                            + '<div class="piece-meta">' + document.getElementById('progTxt').textContent + '</div>'
                            + '</div></div>'
                            + (manque.length
                                ? '<div class="alert alert-warning small mt-2 mb-0"><i class="fas fa-triangle-exclamation"></i> '
                                  + 'Pièce(s) obligatoire(s) manquante(s) : <strong>' + manque.join(', ') + '</strong>. '
                                  + 'Revenez à l\'étape « Vos pièces ».</div>'
                                : '<div class="alert alert-success small mt-2 mb-0"><i class="fas fa-circle-check"></i> '
                                  + 'Votre dossier est complet, vous pouvez l\'envoyer.</div>');
                    }

                    /* ── Sections ── */
                    function renumeroter() {
                        const secs = zone.querySelectorAll('[data-section]');
                        secs.forEach((sec, i) => {
                            sec.querySelector('[data-num]').textContent = i + 1;
                            sec.querySelector('[data-parcours]').name = `sections[${i}][parcours_id]`;
                            sec.querySelector('[data-retirer]').style.display = secs.length > 1 ? '' : 'none';
                        });
                        majRecap();
                    }

                    function verifierDoublons() {
                        const vus = new Set();
                        let ok = true;
                        zone.querySelectorAll('[data-section]').forEach(sec => {
                            const sel = sec.querySelector('[data-parcours]');
                            const msg = sec.querySelector('[data-doublon]');
                            const dbl = sel.value && vus.has(sel.value);
                            sel.classList.toggle('is-invalid', !!dbl);
                            msg.style.display = dbl ? '' : 'none';
                            if (dbl) ok = false;
                            if (sel.value) vus.add(sel.value);
                        });
                        return ok;
                    }

                    /** Annonce, dès le choix, le nombre de pièces propres au parcours. */
                    function majInfoSection(sec) {
                        const sel = sec.querySelector('[data-parcours]');
                        const val = sel.value;
                        sec.classList.toggle('vide', !val);
                        sec.querySelector('[data-nom]').textContent =
                            val ? sel.options[sel.selectedIndex].text : 'Parcours visé';

                        let n = 0;
                        [...tplPiece.content.querySelectorAll('.piece-spec')].forEach(m => {
                            if ((m.dataset.parcoursCibles || '').split(',').includes(val)) n++;
                        });
                        sec.querySelector('[data-info]').innerHTML = !val ? ''
                            : (n ? '<i class="fas fa-circle-info"></i> ' + n + ' pièce(s) en plus des pièces communes.'
                                 : '<i class="fas fa-circle-check text-success"></i> Aucune pièce supplémentaire.');
                    }

                    function ajouterSection() {
                        const sec = tplSec.content.firstElementChild.cloneNode(true);
                        zone.appendChild(sec);
                        sec.querySelector('[data-parcours]').addEventListener('change', () => {
                            majInfoSection(sec); verifierDoublons(); majRecap();
                        });
                        sec.querySelector('[data-retirer]').addEventListener('click', () => {
                            sec.remove(); renumeroter(); verifierDoublons();
                        });
                        renumeroter();
                        majInfoSection(sec);
                    }

                    document.getElementById('btnAjoutSection').addEventListener('click', ajouterSection);
                    document.querySelectorAll('#grpParcours, .grp.pour-tous').forEach(z => {
                        z.querySelectorAll('input[type=file]').forEach(i => {
                            i.addEventListener('change', () => { majPiece(i); majRecap(); });
                        });
                    });

                    ajouterSection();
                    afficher(0);

                    /* ── Envoi ── */
                    const ov = document.getElementById('sendOv');
                    const pct = document.getElementById('sendPct');
                    const fill = document.getElementById('sendFill');
                    const txt = document.getElementById('sendTxt');

                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        form.querySelectorAll('.piece').forEach(b => {
                            b.classList.remove('err');
                            const x = b.querySelector('[data-err]');
                            if (x) { x.style.display = 'none'; x.textContent = ''; }
                        });
                        alerte.style.display = 'none';

                        if (!etapeParcoursValide()) { afficher(0); return; }
                        if (!etapeIdentiteValide()) { afficher(1); return; }

                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', form.action, true);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.setRequestHeader('Accept', 'application/json');

                        xhr.upload.addEventListener('progress', ev => {
                            if (!ev.lengthComputable) return;
                            const p = Math.round(ev.loaded / ev.total * 100);
                            pct.textContent = p + '%'; fill.style.width = p + '%';
                            if (p >= 100) txt.textContent = 'Enregistrement de votre dossier…';
                        });

                        xhr.addEventListener('load', () => {
                            let data = {};
                            try { data = JSON.parse(xhr.responseText); } catch (_) { }
                            if (xhr.status === 200 && data.redirect) {
                                txt.textContent = 'Candidature enregistrée !';
                                window.location = data.redirect;
                                return;
                            }
                            ov.classList.remove('on');

                            const errs = data.errors || {};
                            let premier = null;
                            Object.keys(errs).forEach(cle => {
                                const msg = Array.isArray(errs[cle]) ? errs[cle][0] : errs[cle];
                                let bloc = null;
                                let m = cle.match(/^files\.(\d+)$/);
                                if (m) bloc = document.getElementById('piece-' + m[1]);
                                m = cle.match(/^sections\.(\d+)\.files\.(\d+)$/);
                                if (m) bloc = zoneGrp.querySelector(
                                    '.piece-spec[data-type="' + m[2] + '"] input[name="sections[' + m[1] + '][files][' + m[2] + ']"]'
                                )?.closest('.piece');
                                if (bloc) {
                                    bloc.classList.add('err');
                                    const x = bloc.querySelector('[data-err]');
                                    if (x) { x.textContent = msg; x.style.display = ''; }
                                    if (!premier) premier = bloc;
                                } else {
                                    alerte.style.display = ''; alerte.textContent = msg;
                                    if (!premier) premier = alerte;
                                }
                            });
                            if (!premier) {
                                alerte.style.display = '';
                                alerte.textContent = data.message || "L'envoi a échoué. Vérifiez votre dossier et réessayez.";
                                premier = alerte;
                            }
                            // Les pièces sont à l'étape 3 : y ramener le candidat
                            afficher(2);
                            setTimeout(() => premier.scrollIntoView({ behavior: 'smooth', block: 'center' }), 220);
                        });

                        xhr.addEventListener('error', () => {
                            ov.classList.remove('on');
                            alerte.style.display = '';
                            alerte.textContent = 'Connexion interrompue. Vérifiez votre réseau et réessayez.';
                        });

                        pct.textContent = '0%'; fill.style.width = '0%';
                        txt.textContent = 'Envoi de vos pièces…';
                        ov.classList.add('on');
                        xhr.send(new FormData(form));
                    });
                })();
            </script>

            <p class="text-center mt-3" style="font-size:13px;">
                <a href="{{ route('candidature.suivi') }}" style="color:var(--brand);text-decoration:none;"><i class="fas fa-magnifying-glass"></i> Suivre une candidature existante</a>
                &nbsp;·&nbsp;
                <a href="{{ route('login') }}" style="color:var(--t2);text-decoration:none;">Retour à la connexion</a>
            </p>
        @endif
    </div>
@endsection
