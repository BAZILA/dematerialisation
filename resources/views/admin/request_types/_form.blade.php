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
                    {{ old('is_required', $item?->is_required ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="req-{{ $item?->id ?? 'new' }}">
                    <strong>Obligatoire</strong>
                    <br><small class="text-muted ">Bloque la soumission si absent</small>
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
    }
</script>
