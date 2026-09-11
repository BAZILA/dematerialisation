@extends('layouts.app')
@section('page-title', 'Paramètres de la plateforme')
@section('page-subtitle', 'Espace super-administrateur')

@section('css')
    <style>
        .par-layout { display: grid; gap: 20px; grid-template-columns: 1fr; align-items: start; }
        @media (min-width: 992px) { .par-layout { grid-template-columns: 236px 1fr; } }

        /* ── Rail des rubriques ── */
        .par-rail { position: sticky; top: 78px; }
        .par-onglet {
            display: flex; align-items: center; gap: 11px; width: 100%;
            padding: 9px 12px; margin-bottom: 3px;
            background: none; border: none; border-radius: 9px;
            color: var(--t2); font-size: 13.5px; font-weight: 500;
            font-family: inherit; text-align: left; cursor: pointer;
            transition: background var(--dur), color var(--dur);
        }
        .par-onglet:hover { background: var(--surface-2); color: var(--t1); }
        .par-onglet.actif { background: var(--blue); color: #fff; }
        .par-onglet i { width: 16px; text-align: center; font-size: 13px; }
        .par-compte {
            margin-left: auto; font-size: 11px; font-weight: 600;
            background: var(--surface-2); color: var(--t3);
            padding: 1px 7px; border-radius: 20px;
        }
        .par-onglet.actif .par-compte { background: rgba(255,255,255,.22); color: #fff; }
        /* Une rubrique qui contient un resultat de recherche reste reperable */
        .par-onglet.touche { box-shadow: inset 2px 0 0 var(--amber); }

        /* ── Réglages ── */
        .par-panneau { display: none; }
        .par-panneau.actif { display: block; }
        body.par-recherche .par-panneau { display: block; }
        body.par-recherche .par-rail { opacity: .45; pointer-events: none; }

        .par-ligne { padding: 15px 0; border-top: 1px solid var(--line); }
        .par-ligne:first-child { border-top: none; padding-top: 4px; }
        .par-ligne.masque { display: none; }
        .par-ligne.modifie { box-shadow: inset 3px 0 0 var(--amber); padding-left: 12px; }

        /* Un reglage commande par un interrupteur eteint : visible, mais
           visiblement sans effet. On ne le cache pas — le masquer ferait
           croire qu'il n'existe pas. */
        .par-ligne.dort { opacity: .5; }

        .par-titre { font-size: 14px; font-weight: 600; color: var(--t1); }
        .par-aide { font-size: 12.5px; color: var(--t2); line-height: 1.5; margin-top: 2px; }
        .par-cle { font-size: 10.5px; color: var(--t3); font-family: var(--font-mono, monospace); }
        body:not(.par-cles) .par-cle { display: none; }

        /* ── Interrupteur maître ── */
        .par-maitre {
            background: var(--surface-2); border: 1px solid var(--line);
            border-radius: 11px; padding: 15px; margin-bottom: 4px;
        }
        .par-maitre .par-titre { font-size: 15px; }
        .par-enfants { padding-left: 18px; border-left: 2px solid var(--line); margin-left: 6px; }

        .par-alerte {
            font-size: 12.5px; border-radius: 9px; padding: 9px 12px;
            background: rgba(245, 158, 11, .12); border: 1px solid rgba(245, 158, 11, .35);
            color: var(--t1); margin-top: 10px;
        }

        /* ── Barre d'enregistrement ── */
        .par-barre {
            position: sticky; bottom: 0; z-index: 5; background: var(--bg);
            border-top: 1px solid var(--line); padding: 13px 0; margin-top: 10px;
            display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
        }
        .par-etat { font-size: 13px; color: var(--t2); }
        .par-etat strong { color: var(--amber); }
    </style>
@endsection

@section('content')
    @php
        $meta = [
            'general'       => ['Général',        'fa-building-columns'],
            'modules'       => ['Modules',        'fa-puzzle-piece'],
            'notifications' => ['Notifications',  'fa-bell'],
            'relances'      => ['Relances',       'fa-bell-concierge'],
            'paiement'      => ['Paiement',       'fa-credit-card'],
            'acces'         => ['Accès par code', 'fa-key'],
            'recyclage'     => ['Recyclage',      'fa-arrows-rotate'],
            'sauvegarde'    => ['Sauvegarde',     'fa-shield-halved'],
            'affichage'     => ['Affichage',      'fa-table-list'],
        ];

        /* Un interrupteur maître commande d'autres réglages. Tant qu'il est
           éteint, ceux-ci n'ont aucun effet : on le montre, plutôt que de
           laisser régler dans le vide. */
        $maitres = [
            'notifications.enabled' => ['notifications.email.enabled', 'notifications.sms.enabled',
                'sms.fournisseur', 'sms.togocom.cle', 'sms.togocom.url', 'sms.togocom.expediteur',
                'sms.moov.cle', 'sms.moov.url', 'sms.moov.expediteur'],
            'relances.enabled'  => ['relances.delai_jours', 'relances.intervalle_jours', 'relances.max'],
            'payments.enabled'  => ['payment.card.enabled', 'payment.mobile_money.enabled',
                'payment.offline.enabled', 'paiement.bloque_validation', 'paiement.devise',
                'paiement.operateur_defaut', 'paiement.nom_marchand', 'paiement.numero_marchand'],
            'acces.code_enabled' => ['acces.code_duree', 'acces.code_email'],
            'recyclage.enabled'  => ['recyclage.derogation_requise', 'recyclage.max_annees',
                'depot.annees_derogatoires'],
            'sauvegarde.auto'    => ['sauvegarde.heure', 'sauvegarde.retention_jours'],
        ];

        /* Dépendances qui traversent les rubriques : c'est là qu'on règle
           sans effet sans jamais s'en apercevoir. */
        $exige = [
            'relances.enabled'         => 'notifications.enabled',
            'acces.code_email'         => 'notifications.enabled',
            'candidature.fees.enabled' => 'payments.enabled',
        ];

        $tous = collect($groups)->flatten();
        $valeurs = $tous->mapWithKeys(fn ($s) => [$s->key => $s->typedValue()]);
        $libelles = $tous->mapWithKeys(fn ($s) => [$s->key => $s->label ?? $s->key]);

        // Un enfant hérite du maître : on retrouve le maître de chaque clé.
        $maitreDe = [];
        foreach ($maitres as $cle => $enfants) {
            foreach ($enfants as $e) { $maitreDe[$e] = $cle; }
        }

        $ordre = array_keys($meta);
        $rubriques = collect($groups)->sortBy(function ($v, $k) use ($ordre) {
            $rang = array_search($k, $ordre, true);

            // array_search rend 0 pour le premier : « ?: » l'aurait renvoye
            // en fin de liste, la rubrique « General » se retrouvant derniere.
            return $rang === false ? 99 : $rang;
        });
        $premiere = $rubriques->keys()->first();
    @endphp

    {{-- ═══ Recherche ═══ --}}
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <div class="position-relative" style="flex:1;min-width:260px;max-width:460px;">
            <i class="fas fa-magnifying-glass position-absolute"
                style="left:13px;top:50%;transform:translateY(-50%);color:var(--t3);font-size:13px;"></i>
            <input type="search" id="parRecherche" class="form-control" style="padding-left:36px;"
                placeholder="Chercher un réglage — « relance », « SMS », « sauvegarde »…"
                autocomplete="off">
        </div>
        <span class="par-etat" id="parResultat"></span>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="parClesBtn">
            <i class="fas fa-code"></i> Noms techniques
        </button>
    </div>

    <form method="POST" action="{{ route('superadmin.settings.update') }}" id="formParams">
        @csrf

        <div class="par-layout">
            {{-- ═══ Rail ═══ --}}
            <div class="par-rail">
                @foreach ($rubriques as $groupe => $reglages)
                    @php [$label, $icone] = $meta[$groupe] ?? [ucfirst($groupe), 'fa-gear']; @endphp
                    <button type="button" class="par-onglet {{ $groupe === $premiere ? 'actif' : '' }}"
                        data-rubrique="{{ $groupe }}" onclick="parAfficher('{{ $groupe }}')">
                        <i class="fas {{ $icone }}"></i>
                        <span>{{ $label }}</span>
                        <span class="par-compte">{{ count($reglages) }}</span>
                    </button>
                @endforeach
                <button type="button" class="par-onglet" data-rubrique="cachet"
                    onclick="parAfficher('cachet')">
                    <i class="fas fa-stamp"></i>
                    <span>Cachet officiel</span>
                </button>
            </div>

            {{-- ═══ Panneaux ═══ --}}
            <div>
                @foreach ($rubriques as $groupe => $reglages)
                    @php [$label, $icone] = $meta[$groupe] ?? [ucfirst($groupe), 'fa-gear']; @endphp
                    <div class="par-panneau {{ $groupe === $premiere ? 'actif' : '' }}"
                        data-panneau="{{ $groupe }}">
                        <div class="card">
                            <div class="card-header"><i class="fas {{ $icone }}"></i> {{ $label }}</div>
                            <div class="card-body">
                                @foreach ($reglages as $s)
                                    @php
                                        $estMaitre = isset($maitres[$s->key]);
                                        $maitre    = $maitreDe[$s->key] ?? null;
                                        $requis    = $exige[$s->key] ?? null;

                                        // Un maître absent de la même rubrique est quand même
                                        // consulté : sa valeur décide de l'état affiché.
                                        $dort = ($maitre && !($valeurs[$maitre] ?? false))
                                             || ($requis && !($valeurs[$requis] ?? false));
                                    @endphp
                                    <div class="par-ligne {{ $estMaitre ? 'par-maitre' : '' }} {{ $maitre ? 'par-enfants' : '' }} {{ $dort ? 'dort' : '' }}"
                                        data-cle="{{ $s->key }}"
                                        data-texte="{{ mb_strtolower(($s->label ?? '') . ' ' . ($s->description ?? '') . ' ' . $s->key) }}"
                                        @if ($maitre) data-maitre="{{ $maitre }}" @endif
                                        @if ($requis) data-requis="{{ $requis }}" @endif>

                                        @if ($s->type === 'boolean')
                                            <div class="form-check form-switch mb-0">
                                                <input type="checkbox" class="form-check-input par-champ"
                                                    id="set_{{ $s->id }}" name="settings[{{ $s->key }}]" value="1"
                                                    data-initial="{{ $s->typedValue() ? '1' : '0' }}"
                                                    @checked($s->typedValue())>
                                                <label class="form-check-label" for="set_{{ $s->id }}">
                                                    <span class="par-titre">{{ $s->label ?? $s->key }}</span>
                                                    @if ($s->description)
                                                        <span class="par-aide d-block">{{ $s->description }}</span>
                                                    @endif
                                                    <span class="par-cle d-block mt-1">{{ $s->key }}</span>
                                                </label>
                                            </div>
                                        @else
                                            <label class="d-block mb-1" for="set_{{ $s->id }}">
                                                <span class="par-titre">{{ $s->label ?? $s->key }}</span>
                                                @if ($s->description)
                                                    <span class="par-aide d-block">{{ $s->description }}</span>
                                                @endif
                                            </label>
                                            <input type="{{ $s->type === 'integer' ? 'number' : 'text' }}"
                                                class="form-control form-control-sm par-champ"
                                                style="max-width:340px;" id="set_{{ $s->id }}"
                                                name="settings[{{ $s->key }}]" value="{{ $s->value }}"
                                                data-initial="{{ $s->value }}">
                                            <span class="par-cle d-block mt-1">{{ $s->key }}</span>
                                        @endif

                                        @if ($requis)
                                            <div class="par-alerte" data-alerte-pour="{{ $requis }}"
                                                @if ($valeurs[$requis] ?? false) hidden @endif>
                                                <i class="fas fa-link-slash"></i>
                                                Sans <strong>{{ $libelles[$requis] ?? $requis }}</strong>,
                                                ce réglage n'a aucun effet : rien ne partira.
                                            </div>
                                        @elseif ($maitre)
                                            <div class="par-alerte" data-alerte-pour="{{ $maitre }}"
                                                @if ($valeurs[$maitre] ?? false) hidden @endif>
                                                <i class="fas fa-power-off"></i>
                                                <strong>{{ $libelles[$maitre] ?? $maitre }}</strong> est éteint :
                                                ce réglage est conservé, mais reste sans effet.
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- ═══ Cachet ═══ --}}
                <div class="par-panneau" data-panneau="cachet">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-stamp"></i> Cachet officiel</div>
                        <div class="card-body">
                            <p class="par-aide">
                                Image PNG, fond transparent conseillé, apposée automatiquement sur le
                                <strong>verso de la fiche</strong> lors de la validation d'un dépôt.
                            </p>
                            <div class="text-center mb-3"
                                style="border:1px dashed var(--line);border-radius:10px;background:var(--surface-2);padding:18px;">
                                @if ($hasCachet)
                                    <img src="{{ route('superadmin.cachet') }}?v={{ time() }}" alt="Cachet"
                                        style="max-width:100%;max-height:130px;">
                                @else
                                    <span class="par-aide"><i class="fas fa-image"></i> Aucun cachet enregistré</span>
                                @endif
                            </div>
                            <label class="form-label small" for="champCachet">Fichier PNG (2 Mo maximum)</label>
                            <div class="input-group input-group-sm" style="max-width:460px;">
                                <input type="file" name="cachet" id="champCachet" accept=".png"
                                    class="form-control" form="formCachet" required>
                                <button class="btn btn-primary" form="formCachet" data-loading-text="Envoi…">
                                    <i class="fas fa-upload"></i> Charger
                                </button>
                            </div>
                            @if ($hasCachet)
                                <button class="btn btn-outline-danger btn-sm mt-3" form="formCachetSuppr">
                                    <i class="fas fa-trash"></i> Supprimer le cachet
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="par-barre">
            <button type="submit" class="btn btn-primary" data-loading-text="Enregistrement…">
                <i class="fas fa-save"></i> Enregistrer
            </button>
            <span class="par-etat" id="parModifs">Aucune modification</span>
        </div>
    </form>

    {{-- Formulaires du cachet, hors du formulaire principal (pas d'imbrication) --}}
    <form id="formCachet" method="POST" action="{{ route('superadmin.cachet.upload') }}"
        enctype="multipart/form-data" class="d-none">@csrf</form>
    @if ($hasCachet)
        <form id="formCachetSuppr" method="POST" action="{{ route('superadmin.cachet.delete') }}"
            class="d-none" onsubmit="return confirm('Supprimer le cachet ?')">@csrf @method('DELETE')</form>
    @endif
@endsection

@section('js')
    <script>
        /* Appelée depuis l'attribut onclick : doit rester globale. */
        function parAfficher(rubrique) {
            document.querySelectorAll('.par-onglet').forEach(function(b) {
                b.classList.toggle('actif', b.dataset.rubrique === rubrique);
            });
            document.querySelectorAll('.par-panneau').forEach(function(p) {
                p.classList.toggle('actif', p.dataset.panneau === rubrique);
            });
            try { localStorage.setItem('parRubrique', rubrique); } catch (e) {}
        }

        (function() {
            var corps = document.body;

            /* ── Rubrique retrouvée d'une visite à l'autre ──
               On revient souvent au même endroit : inutile de le rechercher. */
            try {
                var memoire = localStorage.getItem('parRubrique');
                if (memoire && document.querySelector('[data-panneau="' + memoire + '"]')) {
                    parAfficher(memoire);
                }
            } catch (e) {}

            /* ── Noms techniques, masqués par défaut ── */
            var btnCles = document.getElementById('parClesBtn');
            try {
                if (localStorage.getItem('parCles') === '1') corps.classList.add('par-cles');
            } catch (e) {}
            btnCles?.addEventListener('click', function() {
                var on = corps.classList.toggle('par-cles');
                try { localStorage.setItem('parCles', on ? '1' : '0'); } catch (e) {}
            });

            /* ── Dépendances ──
               Un réglage commandé par un interrupteur éteint reste visible et
               modifiable, mais il le dit. Le masquer ferait croire qu'il
               n'existe pas ; le laisser net ferait croire qu'il agit. */
            function majDependances() {
                document.querySelectorAll('.par-ligne[data-maitre], .par-ligne[data-requis]').forEach(function(ligne) {
                    var cle = ligne.dataset.maitre || ligne.dataset.requis;
                    var interrupteur = document.querySelector('[name="settings[' + cle + ']"]');
                    var allume = interrupteur ? interrupteur.checked : true;

                    ligne.classList.toggle('dort', !allume);
                    var alerte = ligne.querySelector('[data-alerte-pour]');
                    if (alerte) alerte.hidden = allume;
                });
            }

            /* ── Compteur de modifications ──
               Sans lui, on quitte la page en croyant avoir enregistré. */
            function majModifs() {
                var modifies = 0;

                document.querySelectorAll('.par-champ').forEach(function(champ) {
                    var courant = champ.type === 'checkbox' ? (champ.checked ? '1' : '0') : champ.value;
                    var change = courant !== champ.dataset.initial;

                    champ.closest('.par-ligne')?.classList.toggle('modifie', change);
                    if (change) modifies++;
                });

                var etat = document.getElementById('parModifs');
                if (etat) {
                    etat.innerHTML = modifies === 0
                        ? 'Aucune modification'
                        : '<strong>' + modifies + ' modification' + (modifies > 1 ? 's' : '')
                          + '</strong> non enregistrée' + (modifies > 1 ? 's' : '');
                }
                corps.dataset.parModifs = modifies;
            }

            document.querySelectorAll('.par-champ').forEach(function(champ) {
                champ.addEventListener('change', function() { majModifs(); majDependances(); });
                champ.addEventListener('input', majModifs);
            });

            // Quitter avec des modifications non enregistrées : on prévient.
            window.addEventListener('beforeunload', function(e) {
                if (parseInt(corps.dataset.parModifs || '0', 10) > 0) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
            document.getElementById('formParams')?.addEventListener('submit', function() {
                corps.dataset.parModifs = '0';
            });

            /* ── Recherche ──
               38 réglages répartis en neuf rubriques : chercher est plus
               rapide que se souvenir où la chose se trouve. */
            var champRecherche = document.getElementById('parRecherche');
            var resultat = document.getElementById('parResultat');

            champRecherche?.addEventListener('input', function() {
                var terme = this.value.trim().toLowerCase();
                corps.classList.toggle('par-recherche', terme.length > 0);

                if (!terme) {
                    document.querySelectorAll('.par-ligne').forEach(function(l) { l.classList.remove('masque'); });
                    document.querySelectorAll('.par-onglet').forEach(function(b) { b.classList.remove('touche'); });
                    if (resultat) resultat.textContent = '';
                    return;
                }

                var trouves = 0;
                document.querySelectorAll('.par-panneau[data-panneau]').forEach(function(panneau) {
                    var dansCePanneau = 0;

                    panneau.querySelectorAll('.par-ligne').forEach(function(ligne) {
                        var colle = (ligne.dataset.texte || '').includes(terme);
                        ligne.classList.toggle('masque', !colle);
                        if (colle) { dansCePanneau++; trouves++; }
                    });

                    // Une rubrique sans resultat s'efface, sinon on lit du vide.
                    panneau.style.display = dansCePanneau ? '' : 'none';
                    document.querySelector('.par-onglet[data-rubrique="' + panneau.dataset.panneau + '"]')
                        ?.classList.toggle('touche', dansCePanneau > 0);
                });

                if (resultat) {
                    resultat.textContent = trouves === 0
                        ? 'Aucun réglage ne correspond'
                        : trouves + ' réglage' + (trouves > 1 ? 's' : '') + ' trouvé' + (trouves > 1 ? 's' : '');
                }
            });

            // Sortir de la recherche restaure l'affichage par rubrique.
            champRecherche?.addEventListener('search', function() {
                if (!this.value) {
                    document.querySelectorAll('.par-panneau').forEach(function(p) { p.style.display = ''; });
                }
            });

            majDependances();
            majModifs();
        })();
    </script>
@endsection
