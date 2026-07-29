@extends('layouts.app')
@section('page-title', 'Paramètres de la plateforme')
@section('page-subtitle', 'Espace super-administrateur — activer / désactiver les options')

@section('css')
    <style>
        /* Colonnes en maçonnerie : les cartes se répartissent sur toute la
           largeur au lieu de laisser la moitié de l'écran vide. */
        .params-grid { column-gap: 20px; }
        @media (min-width: 992px)  { .params-grid { column-count: 2; } }
        @media (min-width: 1500px) { .params-grid { column-count: 3; } }
        .params-grid > .card { break-inside: avoid; page-break-inside: avoid; width: 100%; display: inline-block; }

        .param-ligne + .param-ligne { border-top: 1px solid var(--line); margin-top: 14px; padding-top: 14px; }
        .param-cle { font-size: 10.5px; color: var(--t3); background: var(--surface-2);
            padding: 1px 6px; border-radius: 5px; display: inline-block; margin-top: 5px; }

        /* Barre d'enregistrement toujours accessible en bas d'écran */
        .params-barre { position: sticky; bottom: 0; z-index: 5; background: var(--bg);
            border-top: 1px solid var(--line); padding: 14px 0; margin-top: 8px; }
    </style>
@endsection

@section('content')
    @php
        $groupMeta = [
            'general'       => ['Général', 'fa-sliders'],
            'modules'       => ['Modules', 'fa-puzzle-piece'],
            'paiement'      => ['Paiement', 'fa-credit-card'],
            'notifications' => ['Notifications', 'fa-bell'],
            'recyclage'     => ['Recyclage', 'fa-arrows-rotate'],
            'affichage'     => ['Affichage', 'fa-table-list'],
            'sauvegarde'    => ['Sauvegarde', 'fa-shield-halved'],
        ];
    @endphp

    <form method="POST" action="{{ route('superadmin.settings.update') }}">
        @csrf

        <div class="params-grid">
            @foreach ($groups as $group => $settings)
                @php [$gLabel, $gIcon] = $groupMeta[$group] ?? [ucfirst($group), 'fa-gear']; @endphp
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas {{ $gIcon }}"></i> {{ $gLabel }}</span>
                        <span class="badge bg-secondary">{{ count($settings) }}</span>
                    </div>
                    <div class="card-body">
                        @foreach ($settings as $s)
                            <div class="param-ligne">
                                @if ($s->type === 'boolean')
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" id="set_{{ $s->id }}"
                                            name="settings[{{ $s->key }}]" value="1" {{ $s->typedValue() ? 'checked' : '' }}>
                                        <label class="form-check-label" for="set_{{ $s->id }}">
                                            <strong>{{ $s->label ?? $s->key }}</strong>
                                            @if ($s->description)
                                                <br><small class="text-muted">{{ $s->description }}</small>
                                            @endif
                                        </label>
                                    </div>
                                    <code class="param-cle">{{ $s->key }}</code>
                                @else
                                    <label class="form-label mb-1" for="set_{{ $s->id }}">
                                        <strong>{{ $s->label ?? $s->key }}</strong>
                                        @if ($s->description)
                                            <small class="text-muted d-block" style="font-weight:400;">{{ $s->description }}</small>
                                        @endif
                                    </label>
                                    <input type="{{ $s->type === 'integer' ? 'number' : 'text' }}"
                                        class="form-control form-control-sm" id="set_{{ $s->id }}"
                                        name="settings[{{ $s->key }}]" value="{{ $s->value }}">
                                    <code class="param-cle">{{ $s->key }}</code>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- ── Cachet officiel (image apposée sur le verso des fiches) ── --}}
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-stamp"></i> Cachet officiel</div>
                <div class="card-body">
                    <p class="small text-muted">
                        Image PNG (fond transparent conseillé) apposée automatiquement sur le
                        <strong>verso de la fiche</strong> lors de la validation d'un dépôt.
                    </p>
                    <div class="text-center mb-3"
                        style="border:1px dashed var(--line);border-radius:10px;background:var(--surface-2);padding:14px;">
                        @if ($hasCachet)
                            <img src="{{ route('superadmin.cachet') }}?v={{ time() }}" alt="Cachet"
                                style="max-width:100%;max-height:110px;">
                        @else
                            <span class="small text-muted"><i class="fas fa-image"></i> Aucun cachet enregistré</span>
                        @endif
                    </div>
                    <label class="form-label small">Choisir un fichier PNG (max 2 Mo)</label>
                    <div class="input-group input-group-sm">
                        <input type="file" name="cachet" accept=".png" class="form-control"
                            form="formCachet" required>
                        <button class="btn btn-primary" form="formCachet" data-loading-text="Envoi…">
                            <i class="fas fa-upload"></i> Charger
                        </button>
                    </div>
                    @if ($hasCachet)
                        <button class="btn btn-outline-danger btn-sm mt-2 w-100" form="formCachetSuppr">
                            <i class="fas fa-trash"></i> Supprimer le cachet
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="params-barre">
            <button type="submit" class="btn btn-primary" data-loading-text="Enregistrement…">
                <i class="fas fa-save"></i> Enregistrer les paramètres
            </button>
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
