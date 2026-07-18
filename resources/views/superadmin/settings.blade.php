@extends('layouts.app')
@section('page-title', 'Paramètres de la plateforme')
@section('page-subtitle', 'Espace super-administrateur — activer / désactiver les options')

@section('content')
    @php
        $groupMeta = [
            'general'       => ['Général', 'fa-sliders'],
            'modules'       => ['Modules', 'fa-puzzle-piece'],
            'paiement'      => ['Paiement', 'fa-credit-card'],
            'notifications' => ['Notifications', 'fa-bell'],
            'recyclage'     => ['Recyclage', 'fa-arrows-rotate'],
        ];
    @endphp

    <form method="POST" action="{{ route('superadmin.settings.update') }}" style="max-width:820px;">
        @csrf

        @foreach ($groups as $group => $settings)
            @php [$gLabel, $gIcon] = $groupMeta[$group] ?? [ucfirst($group), 'fa-gear']; @endphp
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas {{ $gIcon }}"></i> {{ $gLabel }}
                </div>
                <div class="card-body">
                    @foreach ($settings as $s)
                        <div class="mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}" style="border-color:var(--line)!important;">
                            @if ($s->type === 'boolean')
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" id="set_{{ $s->id }}"
                                        name="settings[{{ $s->key }}]" value="1" {{ $s->typedValue() ? 'checked' : '' }}>
                                    <label class="form-check-label" for="set_{{ $s->id }}">
                                        <strong>{{ $s->label ?? $s->key }}</strong>
                                        @if ($s->description)
                                            <br><small class="text-muted">{{ $s->description }}</small>
                                        @endif
                                        <br><code style="font-size:10.5px;">{{ $s->key }}</code>
                                    </label>
                                </div>
                            @else
                                <label class="form-label" for="set_{{ $s->id }}">
                                    <strong>{{ $s->label ?? $s->key }}</strong>
                                    @if ($s->description)
                                        <small class="text-muted d-block">{{ $s->description }}</small>
                                    @endif
                                </label>
                                <input type="text" class="form-control" id="set_{{ $s->id }}"
                                    name="settings[{{ $s->key }}]" value="{{ $s->value }}">
                                <code style="font-size:10.5px;">{{ $s->key }}</code>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary" data-loading-text="Enregistrement…">
            <i class="fas fa-save"></i> Enregistrer les paramètres
        </button>
    </form>

    {{-- ── Cachet officiel (image apposée sur le verso des fiches) ── --}}
    <div class="card mb-4 mt-4" style="max-width:820px;">
        <div class="card-header"><i class="fas fa-stamp"></i> Cachet officiel</div>
        <div class="card-body">
            <p class="small text-muted">
                Image PNG (fond transparent conseillé) apposée automatiquement sur le
                <strong>verso de la fiche</strong> lors de la validation d'un dépôt.
            </p>
            <div class="d-flex align-items-center gap-4 flex-wrap">
                <div style="width:150px;height:110px;border:1px dashed var(--line);border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--surface-2);">
                    @if ($hasCachet)
                        <img src="{{ route('superadmin.cachet') }}?v={{ time() }}" alt="Cachet" style="max-width:140px;max-height:100px;">
                    @else
                        <span class="small text-muted"><i class="fas fa-image"></i> Aucun cachet</span>
                    @endif
                </div>
                <div class="flex-grow-1" style="min-width:260px;">
                    <form method="POST" action="{{ route('superadmin.cachet.upload') }}" enctype="multipart/form-data" class="mb-2">
                        @csrf
                        <label class="form-label small">Choisir un fichier PNG (max 2 Mo)</label>
                        <div class="input-group">
                            <input type="file" name="cachet" accept=".png" class="form-control" required>
                            <button class="btn btn-primary" data-loading-text="Envoi…"><i class="fas fa-upload"></i> Charger</button>
                        </div>
                    </form>
                    @if ($hasCachet)
                        <form method="POST" action="{{ route('superadmin.cachet.delete') }}"
                            onsubmit="return confirm('Supprimer le cachet ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> Supprimer le cachet</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
