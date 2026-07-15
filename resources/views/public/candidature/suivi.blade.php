@extends('layouts.app')
@section('title', 'Suivi de candidature')

@section('content')
    <div style="width:100%;max-width:520px;position:relative;z-index:1;">
        <div class="text-center mb-3"><img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" style="height:46px;" alt="UK"></div>
        <div class="card"><div class="card-body" style="padding:28px !important;">
            <h5 class="fw-bold" style="color:var(--t1);"><i class="fas fa-magnifying-glass"></i> Suivre ma candidature</h5>
            <p class="text-muted small">Entrez le numéro de suivi reçu lors de votre candidature.</p>
            <form method="POST" action="{{ route('candidature.suivi.post') }}" class="mt-3">
                @csrf
                <div class="input-group">
                    <input class="form-control" name="reference" placeholder="Ex : CAND-2026-XXXXXX" value="{{ old('reference', $candidature->reference ?? '') }}" required>
                    <button class="btn btn-primary" data-loading-text="…"><i class="fas fa-search"></i> Vérifier</button>
                </div>
            </form>

            @if ($cherche)
                @if ($candidature)
                    <div class="mt-4 p-3 rounded" style="background:var(--surface-2);border:1px solid var(--line);">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div><strong style="color:var(--t1);">{{ $candidature->full_name }}</strong><br><small class="text-muted"><code>{{ $candidature->reference }}</code></small></div>
                            <span class="badge bg-{{ $candidature->status_badge }} fs-6 px-3 py-2">{{ $candidature->statut_label }}</span>
                        </div>
                        @if ($candidature->parcours)<div class="small mt-2" style="color:var(--t2);"><i class="fas fa-graduation-cap"></i> {{ $candidature->parcours->nom }}</div>@endif
                        <div class="small mt-1" style="color:var(--t3);">Soumise le {{ $candidature->created_at->format('d/m/Y à H:i') }}</div>
                        @if ($candidature->admin_comment)<div class="alert alert-info small mt-2 mb-0"><i class="fas fa-comment"></i> {{ $candidature->admin_comment }}</div>@endif
                    </div>
                @else
                    <div class="alert alert-danger mt-4 mb-0">Aucune candidature trouvée pour cette référence.</div>
                @endif
            @endif

            <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}" style="color:var(--t2);text-decoration:none;font-size:13px;">Retour à la connexion</a></p>
        </div></div>
    </div>
@endsection
