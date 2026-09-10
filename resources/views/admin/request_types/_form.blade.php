{{--
    Partial réutilisé dans le modal Ajouter et dans chaque modal Modifier.
    Variables attendues :
        $item    : RequestType|null  (null = création)
        $isDepot : bool|null         (hint pour pré-afficher le champ profil)
--}}

<div class="row g-3">

    {{-- Intitulé --}}
    <div class="col-12">
        <label class="form-label">
            Intitulé du document <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control @error('label') is-invalid @enderror" name="label"
            value="{{ old('label', $item?->label) }}" placeholder="Ex : Diplôme de BEPC, Attestation de résidence…"
            required>
        @error('label')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Catégorie --}}
    <div class="col-md-6">
        <label class="form-label">
            Catégorie <span class="text-danger">*</span>
        </label>
        <select class="form-select @error('category') is-invalid @enderror" name="category"
            id="cat-{{ $item?->id ?? 'new' }}" onchange="toggleProfile(this)" required>
            @foreach (\App\Models\RequestType::categories() as $key => $label)
                <option value="{{ $key }}" {{ old('category', $item?->category) === $key ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('category')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Ordre d'affichage --}}
    <div class="col-md-6">
        <label class="form-label">Ordre d'affichage</label>
        <input type="number" class="form-control" name="order" value="{{ old('order', $item?->order ?? 99) }}"
            min="0" max="999" placeholder="1, 2, 3…">
        <small class="text-muted ">Les documents sont affichés du plus petit au plus grand.</small>
    </div>

    {{-- Profil étudiant (visible seulement si catégorie = dépôt) --}}
    <div class="col-12" id="profile-row-{{ $item?->id ?? 'new' }}"
        style="display: {{ old('category', $item?->category) === 'depot' || $isDepot || $item === null ? '' : 'none' }};">
        <label class="form-label">
            Applicable à <span class="text-danger">*</span>
        </label>
        <div class="d-flex gap-3 flex-wrap">
            @foreach (['both' => 'Nouveau & Ancien', 'nouveau' => 'Nouveau seulement', 'ancien' => 'Ancien seulement'] as $val => $lbl)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="type_etudiant"
                        id="te-{{ $val }}-{{ $item?->id ?? 'new' }}" value="{{ $val }}"
                        {{ old('type_etudiant', $item?->type_etudiant ?? 'both') === $val ? 'checked' : '' }}>
                    <label class="form-check-label" for="te-{{ $val }}-{{ $item?->id ?? 'new' }}">
                        {{ $lbl }}
                    </label>
                </div>
            @endforeach
        </div>
        <small class="text-muted ">
            Pour les catégories hors dépôt (attestation, diplôme, relevé, duplicata),
            ce champ est ignoré — elles sont réservées aux anciens étudiants.
        </small>
    </div>

    {{-- Portée de la pièce, À L'INTÉRIEUR de son niveau --}}
    @php
        $uid = $item?->id ?? 'new';

        /* Le niveau vient de l'écran d'où l'on édite : une pièce appartient
           à un niveau et à un seul. Les niveaux partagent les procédures,
           jamais les pièces. */
        $niveauCourant = $niveauCourant ?? $item?->grade ?? array_key_first($grades ?? []);
        $niveauLabel = \App\Models\Parcours::$grades[$niveauCourant]['label'] ?? $niveauCourant;

        $porteeActuelle = old('portee', $item && $item->parcours->isNotEmpty() ? 'specifique' : 'commune');
        $parcoursCoches = collect(old('parcours_ids', $item?->parcours->pluck('id')->all() ?? []))
            ->map(fn($v) => (int) $v)
            ->all();

        // Seuls les parcours de CE niveau ont un sens ici : en désigner un
        // d'ailleurs rendrait la pièce invisible pour tout le monde.
        $parcoursDuNiveau = collect($parcoursParFaculte ?? [])
            ->map(fn($liste) => collect($liste)->where('grade', $niveauCourant)->values())
            ->filter(fn($liste) => $liste->isNotEmpty());
    @endphp

    <input type="hidden" name="grade" value="{{ $niveauCourant }}">

    <div class="col-12">
        <label class="form-label">
            Qui doit fournir cette pièce ? <span class="text-danger">*</span>
        </label>

        <div class="alert alert-light border py-2 px-3 mb-2" style="font-size:12.5px;">
            <i class="fas fa-graduation-cap"></i>
            Cette pièce appartient au niveau <strong>{{ $niveauLabel }}</strong>.
            Elle ne sera <strong>jamais</strong> réclamée à un autre niveau.
        </div>

        <div class="d-flex flex-column gap-2 mb-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="portee" value="commune"
                    id="portee-com-{{ $uid }}" {{ $porteeActuelle === 'commune' ? 'checked' : '' }}
                    onchange="togglePortee(this)">
                <label class="form-check-label" for="portee-com-{{ $uid }}">
                    <strong>Tout le niveau {{ $niveauLabel }}</strong>
                    <span class="d-block small" style="color:var(--t2);">
                        Chaque parcours de ce niveau la fournit, y compris ceux créés plus tard.
                    </span>
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="portee" value="specifique"
                    id="portee-spe-{{ $uid }}" {{ $porteeActuelle === 'specifique' ? 'checked' : '' }}
                    onchange="togglePortee(this)">
                <label class="form-check-label" for="portee-spe-{{ $uid }}">
                    <strong>Certains parcours seulement</strong>
                    <span class="d-block small" style="color:var(--t2);">
                        Les autres parcours de {{ $niveauLabel }} ne la verront pas.
                    </span>
                </label>
            </div>
        </div>

        <div id="portee-list-{{ $uid }}" style="display: {{ $porteeActuelle === 'specifique' ? '' : 'none' }};">
            <div class="border rounded p-2" style="max-height:220px;overflow-y:auto;">
                @forelse ($parcoursDuNiveau as $facNom => $liste)
                    <div class="small fw-semibold mt-2 mb-1" style="color:var(--t2);">{{ $facNom }}</div>
                    @foreach ($liste as $p)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="parcours_ids[]"
                                value="{{ $p->id }}" id="pp-{{ $uid }}-{{ $p->id }}"
                                {{ in_array($p->id, $parcoursCoches, true) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="pp-{{ $uid }}-{{ $p->id }}">
                                {{ $p->nom }}
                            </label>
                        </div>
                    @endforeach
                @empty
                    <div class="small text-muted">
                        Aucun parcours actif en {{ $niveauLabel }}. Sans parcours, la pièce
                        reste commune au niveau.
                    </div>
                @endforelse
            </div>
            <small class="text-muted d-block mt-1">
                Ne cocher aucun parcours revient à la rendre commune à tout le niveau.
            </small>
        </div>
    </div>

    {{-- Description --}}
    <div class="col-12">
        <label class="form-label">Description / consigne <small class="text-muted ">(optionnel)</small></label>
        <input type="text" class="form-control" name="description"
            value="{{ old('description', $item?->description) }}"
            placeholder="Ex : Original ou copie certifiée conforme datant de moins de 3 mois">
    </div>

    {{-- Options --}}
    <div class="col-12">
        <div class="d-flex gap-4 flex-wrap">

            {{-- Obligatoire --}}
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_required" value="1"
                    id="req-{{ $item?->id ?? 'new' }}"
                    {{ old('is_required', $item?->is_required ?? true) ? 'checked' : '' }}
                    onchange="ukMajDerogation('{{ $item?->id ?? 'new' }}');">
                <label class="form-check-label" for="req-{{ $item?->id ?? 'new' }}">
                    <strong>Obligatoire</strong>
                    <br><small class="text-muted ">Bloque la soumission si absent</small>
                </label>
            </div>

            {{-- Report possible : seule une pièce obligatoire a quelque chose à différer --}}
            <div class="form-check form-switch" id="blocDerog-{{ $item?->id ?? 'new' }}">
                <input class="form-check-input" type="checkbox" name="derogation_possible" value="1"
                    id="derog-{{ $item?->id ?? 'new' }}"
                    {{ old('derogation_possible', $item?->derogation_possible ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="derog-{{ $item?->id ?? 'new' }}">
                    <strong>Report autorisé</strong>
                    <br><small class="text-muted ">L'usager peut soumettre sans elle en s'engageant à la
                        fournir. La pièce reste due.</small>
                </label>
            </div>

            {{-- Tamponné --}}
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="has_stamp" value="1"
                    id="stmp-{{ $item?->id ?? 'new' }}"
                    {{ old('has_stamp', $item?->has_stamp ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="stmp-{{ $item?->id ?? 'new' }}">
                    <strong>Tamponné à la validation</strong>
                    <br><small class="text-muted ">Cachet officiel apposé automatiquement (PDF
                        uniquement)</small>
                </label>
            </div>

            {{-- Remplit le verso (la fiche) --}}
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="fills_verso" value="1"
                    id="verso-{{ $item?->id ?? 'new' }}"
                    {{ old('fills_verso', $item?->fills_verso ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="verso-{{ $item?->id ?? 'new' }}">
                    <strong>Remplit le verso auto.</strong>
                    <br><small class="text-muted ">La fiche : verso rempli (études antérieures +
                        cachet + signature agent). Nécessite « Tamponné ».</small>
                </label>
            </div>

            {{-- Actif (modification seulement) --}}
            @if ($item)
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                        id="act-{{ $item->id }}" {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="act-{{ $item->id }}">
                        <strong>Actif (visible)</strong>
                        <br><small class="text-muted ">Décocher pour masquer sans supprimer</small>
                    </label>
                </div>
            @endif

        </div>
    </div>

</div>

<script>
    function toggleProfile(selectEl) {
        // Retrouver le row profil dans le même modal
        const modal = selectEl.closest('.modal-body, .card-body, form');
        const profileRow = modal?.querySelector('[id^="profile-row"]');
        if (profileRow) {
            profileRow.style.display = selectEl.value === 'depot' ? '' : 'none';
        }
        // La portée vaut désormais pour TOUTES les procédures : un dépôt de
        // licence peut n'exiger un certificat médical que du parcours STAPS.
    }

    function togglePortee(radioEl) {
        const modal = radioEl.closest('.modal-body, .card-body, form');
        const list = modal?.querySelector('[id^="portee-list"]');
        if (list) {
            list.style.display = radioEl.value === 'specifique' ? '' : 'none';
        }
    }

    /* Une pièce facultative n'a rien à différer : le report ne s'offre
       que sur une pièce obligatoire, et se retire avec elle. */
    function ukMajDerogation(suffixe) {
        const requis = document.getElementById('req-' + suffixe);
        const bloc = document.getElementById('blocDerog-' + suffixe);
        const cible = document.getElementById('derog-' + suffixe);
        if (!requis || !bloc || !cible) return;

        bloc.style.display = requis.checked ? '' : 'none';
        if (!requis.checked) cible.checked = false;
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="blocDerog-"]').forEach(function(bloc) {
            ukMajDerogation(bloc.id.substring('blocDerog-'.length));
        });
    });
</script>
