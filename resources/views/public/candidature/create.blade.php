@extends('layouts.app')
@section('title', 'Candidature en ligne')

@section('css')
    <style>
        body.guest-mode .content { align-items: flex-start !important; padding-top: 28px; padding-bottom: 44px; }
        .cand-wrap { width: 100%; max-width: 720px; position: relative; z-index: 1; }

        /* ── Pièces : une ligne = un document, état visible d'un coup d'œil ── */
        .piece { border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px;
            margin-bottom: 10px; background: var(--surface); transition: border-color .15s, background .15s; }
        .piece.ok { border-color: #2F9E33; background: rgba(47, 158, 51, .05); }
        .piece.err { border-color: #DC2626; background: rgba(220, 38, 38, .05); }
        .piece-head { display: flex; align-items: center; gap: 10px; }
        .piece-ic { width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0; display: flex;
            align-items: center; justify-content: center; background: var(--em-dim); color: var(--brand); font-size: 13px; }
        .piece.ok .piece-ic { background: #2F9E33; color: #fff; }
        .piece.err .piece-ic { background: #DC2626; color: #fff; }
        .piece-name { font-size: 13.5px; font-weight: 600; color: var(--t1); }
        .piece-meta { font-size: 11.5px; color: var(--t3); }
        .piece input[type=file] { margin-top: 8px; }
        .piece-msg { font-size: 12px; margin-top: 6px; }

        /* ── Avancement du dossier ── */
        .prog-card { border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px;
            background: var(--surface); margin-bottom: 14px; }
        .prog-bar { height: 8px; border-radius: 99px; background: var(--line-mid); overflow: hidden; margin-top: 8px; }
        .prog-fill { height: 100%; width: 0; background: linear-gradient(90deg, var(--brand), #2F9E33);
            border-radius: 99px; transition: width .25s ease; }

        /* ── Overlay d'envoi ── */
        .send-ov { position: fixed; inset: 0; background: rgba(15, 23, 42, .72); backdrop-filter: blur(3px);
            display: none; align-items: center; justify-content: center; z-index: 2000; }
        .send-ov.on { display: flex; }
        .send-box { background: var(--surface); border-radius: 16px; padding: 26px 30px; width: min(360px, 88vw);
            text-align: center; box-shadow: 0 18px 50px rgba(0, 0, 0, .35); }
        .send-pct { font-family: var(--font-head); font-size: 30px; font-weight: 800; color: var(--brand); }
    </style>
@endsection

@section('content')
    <div class="cand-wrap">
        <div class="text-center mb-4">
            <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" style="height:52px;" alt="Université de Kara">
            <h1 style="font-family:var(--font-head);font-size:22px;font-weight:800;margin-top:14px;color:var(--t1);">Candidature en ligne</h1>
            <p style="color:var(--t2);font-size:13.5px;">Soumettez votre candidature <strong>sans créer de compte</strong>. Un numéro de suivi vous sera remis.</p>
        </div>

        @if (!$ouverte)
            <div class="card"><div class="card-body text-center py-5">
                <i class="fas fa-door-closed" style="font-size:2.5rem;color:var(--t3);"></i>
                <h5 class="mt-3" style="color:var(--t1);">Candidatures fermées</h5>
                <p class="text-muted">Les candidatures ne sont pas ouvertes actuellement. Revenez pendant la période d'admission.</p>
                <a href="{{ route('login') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
            </div></div>
        @else
            <div class="card"><div class="card-body" style="padding:28px !important;">
                <form method="POST" action="{{ route('candidature.store') }}" enctype="multipart/form-data" id="candForm">
                    @csrf
                    <h6 class="fw-bold mb-3" style="color:var(--brand);"><i class="fas fa-user"></i> Vos informations</h6>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nom *</label><input class="form-control" name="nom" value="{{ old('nom') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Prénom(s) *</label><input class="form-control" name="prenom" value="{{ old('prenom') }}" required></div>
                        <div class="col-md-4"><label class="form-label">Sexe *</label>
                            <select class="form-select" name="sexe" required>
                                <option value="">—</option>
                                <option value="M" @selected(old('sexe') == 'M')>Masculin</option>
                                <option value="F" @selected(old('sexe') == 'F')>Féminin</option>
                            </select></div>
                        <div class="col-md-4"><label class="form-label">Date de naissance *</label><input type="date" class="form-control" name="date_naissance" value="{{ old('date_naissance') }}" required></div>
                        <div class="col-md-4"><label class="form-label">Lieu de naissance *</label><input class="form-control" name="lieu_naissance" value="{{ old('lieu_naissance') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Email *</label><input type="email" class="form-control" name="email" value="{{ old('email') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="telephone" value="{{ old('telephone') }}"></div>
                    </div>

                    <h6 class="fw-bold mb-1 mt-4" style="color:var(--brand);"><i class="fas fa-graduation-cap"></i> Parcours souhaités</h6>
                    <p class="small text-muted mb-3">Classez jusqu'à <strong>3 parcours</strong> par ordre de préférence.
                        Le 1er choix est obligatoire ; l'admission sera prononcée sur l'un de vos choix.</p>
                    @for ($i = 1; $i <= 3; $i++)
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">
                                {{ $i === 1 ? '1er choix' : $i . 'e choix' }}
                                @if ($i === 1)<span class="text-danger">*</span>@else <span class="text-muted">(optionnel)</span>@endif
                            </label>
                            <select class="form-select choix-parcours" name="parcours_ids[{{ $i }}]" @required($i === 1)>
                                <option value="">— {{ $i === 1 ? 'Choisissez un parcours' : 'Aucun' }} —</option>
                                @foreach ($facultes as $fac)
                                    @foreach ($fac->departements as $dep)
                                        @if ($dep->parcours->count())
                                            <optgroup label="{{ $fac->sigle ?? $fac->nom }} · {{ $dep->nom }}">
                                                @foreach ($dep->parcours as $p)
                                                    <option value="{{ $p->id }}" @selected(old("parcours_ids.$i") == $p->id)>{{ $p->nom }} ({{ $p->grade_label }})</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                    @endfor

                    @php
                        $communes = $types->filter(fn($t) => $t->parcours->isEmpty());
                        $specifiques = $types->filter(fn($t) => $t->parcours->isNotEmpty());
                    @endphp

                    @if ($types->count())
                        <h6 class="fw-bold mb-2 mt-4" style="color:var(--brand);"><i class="fas fa-paperclip"></i> Pièces à joindre</h6>

                        {{-- Avancement du dossier : mis à jour à chaque fichier choisi --}}
                        <div class="prog-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small fw-semibold" style="color:var(--t1);">Avancement de votre dossier</span>
                                <span class="small" id="progTxt" style="color:var(--t2);">0 / 0 pièce(s) obligatoire(s)</span>
                            </div>
                            <div class="prog-bar"><div class="prog-fill" id="progFill"></div></div>
                        </div>

                        @if ($communes->count())
                            <p class="small text-muted mb-2">Pièces demandées à <strong>tous les candidats</strong>.</p>
                            @foreach ($communes as $t)
                                <div class="piece" id="piece-{{ $t->id }}">
                                    <div class="piece-head">
                                        <div class="piece-ic"><i class="fas fa-file-arrow-up"></i></div>
                                        <div class="flex-grow-1">
                                            <div class="piece-name">
                                                {{ $t->label }}
                                                @if ($t->is_required)<span class="text-danger">*</span>@else <span class="piece-meta">(optionnel)</span>@endif
                                            </div>
                                            @if ($t->description)<div class="piece-meta">{{ $t->description }}</div>@endif
                                        </div>
                                        <span class="piece-meta text-nowrap" data-etat>—</span>
                                    </div>
                                    <input type="file" class="form-control form-control-sm" name="files[{{ $t->id }}]"
                                        accept=".pdf,.jpg,.jpeg,.png" data-req="{{ $t->is_required ? 1 : 0 }}" @required($t->is_required)>
                                    <div class="piece-msg text-danger" data-err style="display:none;"></div>
                                </div>
                            @endforeach
                        @endif

                        @if ($specifiques->count())
                            {{-- Pièces propres à certains parcours : révélées selon les choix --}}
                            <div id="piecesSpecifiques" class="mt-3">
                                <p class="small text-muted mb-2" id="specHint" style="display:none;">
                                    <i class="fas fa-circle-info"></i>
                                    Pièces supplémentaires exigées par les parcours que vous avez choisis.
                                </p>
                                @foreach ($specifiques as $t)
                                    <div class="piece piece-spec" id="piece-{{ $t->id }}" style="display:none;"
                                        data-parcours="{{ $t->parcours->pluck('id')->implode(',') }}"
                                        data-required="{{ $t->is_required ? 1 : 0 }}">
                                        <div class="piece-head">
                                            <div class="piece-ic"><i class="fas fa-code-branch"></i></div>
                                            <div class="flex-grow-1">
                                                <div class="piece-name">
                                                    {{ $t->label }}
                                                    @if ($t->is_required)<span class="text-danger">*</span>@else <span class="piece-meta">(optionnel)</span>@endif
                                                    <span class="badge bg-warning text-dark ms-1" style="font-weight:600;">
                                                        {{ $t->parcours->pluck('nom')->implode(' · ') }}
                                                    </span>
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
                        @endif
                    @endif

                    <div class="alert alert-danger py-2 small mt-3" id="candAlerte" style="display:none;"></div>

                    <button type="submit" class="btn btn-primary w-100 mt-2" style="height:48px;font-size:15px;">
                        <i class="fas fa-paper-plane"></i> Soumettre ma candidature
                    </button>
                </form>
            </div></div>
            {{-- Overlay d'envoi : progression RÉELLE du téléversement --}}
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

                    const selects = document.querySelectorAll('.choix-parcours');
                    const specs = document.querySelectorAll('.piece-spec');
                    const hint = document.getElementById('specHint');
                    const progTxt = document.getElementById('progTxt');
                    const progFill = document.getElementById('progFill');
                    const alerte = document.getElementById('candAlerte');

                    const koctets = o => o < 1024 ? o + ' o'
                        : (o < 1048576 ? (o / 1024).toFixed(0) + ' Ko' : (o / 1048576).toFixed(1) + ' Mo');

                    /* ── Pièces propres à un parcours : visibles seulement si le
                          candidat a choisi l'un des parcours concernés ── */
                    function majSpecifiques() {
                        const choisis = Array.from(selects).map(s => s.value).filter(Boolean);
                        let visibles = 0;
                        specs.forEach(bloc => {
                            const cibles = (bloc.dataset.parcours || '').split(',').filter(Boolean);
                            const actif = cibles.some(id => choisis.includes(id));
                            bloc.style.display = actif ? '' : 'none';
                            const input = bloc.querySelector('input[type=file]');
                            if (input) {
                                input.required = actif && bloc.dataset.required === '1';
                                if (!actif) { input.value = ''; majPiece(input); }
                            }
                            if (actif) visibles++;
                        });
                        if (hint) hint.style.display = visibles ? '' : 'none';
                    }

                    /* ── État d'une pièce (choisie / vide) ── */
                    function majPiece(input) {
                        const bloc = input.closest('.piece');
                        if (!bloc) return;
                        const etat = bloc.querySelector('[data-etat]');
                        const err = bloc.querySelector('[data-err]');
                        const f = input.files && input.files[0];
                        bloc.classList.toggle('ok', !!f);
                        bloc.classList.remove('err');
                        if (err) { err.style.display = 'none'; err.textContent = ''; }
                        if (etat) {
                            etat.innerHTML = f
                                ? '<i class="fas fa-circle-check text-success"></i> ' + koctets(f.size)
                                : '—';
                        }
                        const ic = bloc.querySelector('.piece-ic i');
                        if (ic) ic.className = f ? 'fas fa-circle-check' : 'fas fa-file-arrow-up';
                    }

                    /* ── Avancement global : pièces obligatoires visibles ── */
                    function majAvancement() {
                        let requis = 0, fournis = 0;
                        form.querySelectorAll('input[type=file]').forEach(i => {
                            const bloc = i.closest('.piece');
                            if (!bloc || bloc.style.display === 'none') return;
                            if (i.dataset.req === '1') {
                                requis++;
                                if (i.files && i.files.length) fournis++;
                            }
                        });
                        const pct = requis ? Math.round(fournis / requis * 100) : 0;
                        if (progFill) progFill.style.width = pct + '%';
                        if (progTxt) progTxt.textContent = fournis + ' / ' + requis + ' pièce(s) obligatoire(s)';
                    }

                    form.querySelectorAll('input[type=file]').forEach(i => {
                        i.addEventListener('change', () => { majPiece(i); majAvancement(); });
                    });
                    selects.forEach(s => s.addEventListener('change', () => { majSpecifiques(); majAvancement(); }));
                    majSpecifiques();
                    majAvancement();

                    /* ── Envoi avec progression réelle ── */
                    const ov = document.getElementById('sendOv');
                    const pct = document.getElementById('sendPct');
                    const fill = document.getElementById('sendFill');
                    const txt = document.getElementById('sendTxt');

                    function nettoyerErreurs() {
                        form.querySelectorAll('.piece').forEach(b => {
                            b.classList.remove('err');
                            const e = b.querySelector('[data-err]');
                            if (e) { e.style.display = 'none'; e.textContent = ''; }
                        });
                        if (alerte) alerte.style.display = 'none';
                    }

                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        nettoyerErreurs();

                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', form.action, true);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.setRequestHeader('Accept', 'application/json');

                        xhr.upload.addEventListener('progress', ev => {
                            if (!ev.lengthComputable) return;
                            const p = Math.round(ev.loaded / ev.total * 100);
                            pct.textContent = p + '%';
                            fill.style.width = p + '%';
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

                            // Erreurs replacées en face de la pièce concernée
                            const errs = data.errors || {};
                            let premier = null;
                            Object.keys(errs).forEach(cle => {
                                const msg = Array.isArray(errs[cle]) ? errs[cle][0] : errs[cle];
                                const m = cle.match(/^files\.(\d+)$/);
                                const bloc = m ? document.getElementById('piece-' + m[1]) : null;
                                if (bloc) {
                                    bloc.classList.add('err');
                                    const e = bloc.querySelector('[data-err]');
                                    if (e) { e.textContent = msg; e.style.display = ''; }
                                    if (!premier) premier = bloc;
                                } else if (alerte) {
                                    alerte.style.display = '';
                                    alerte.textContent = msg;
                                    if (!premier) premier = alerte;
                                }
                            });
                            if (!premier && alerte) {
                                alerte.style.display = '';
                                alerte.textContent = data.message || "L'envoi a échoué. Vérifiez votre dossier et réessayez.";
                                premier = alerte;
                            }
                            if (premier) premier.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        });

                        xhr.addEventListener('error', () => {
                            ov.classList.remove('on');
                            if (alerte) {
                                alerte.style.display = '';
                                alerte.textContent = 'Connexion interrompue. Vérifiez votre réseau et réessayez.';
                            }
                        });

                        pct.textContent = '0%';
                        fill.style.width = '0%';
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
