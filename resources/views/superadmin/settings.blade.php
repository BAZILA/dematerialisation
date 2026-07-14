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
@endsection
