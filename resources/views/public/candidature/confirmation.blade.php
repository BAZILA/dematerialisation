@extends('layouts.app')
@section('title', 'Candidature soumise')

@section('content')
    <div style="width:100%;max-width:520px;position:relative;z-index:1;">
        <div class="card"><div class="card-body text-center" style="padding:36px !important;">
            <div class="icon-box mx-auto mb-3" style="width:72px;height:72px;background:var(--green-dim);">
                <i class="fas fa-check" style="font-size:1.9rem;color:var(--green);"></i>
            </div>
            <h4 class="fw-bold" style="color:var(--t1);">Candidature envoyée !</h4>
            <p class="text-muted">Conservez précieusement votre numéro de suivi :</p>
            <div class="p-3 rounded mb-3" style="background:var(--em-dim);border:1px dashed var(--brand);">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--t3);">Numéro de suivi</div>
                <div style="font-family:var(--font-head);font-size:24px;font-weight:800;color:var(--brand);letter-spacing:.04em;">{{ $candidature->reference }}</div>
            </div>
            <p class="small text-muted">Un email de confirmation sera envoyé à <strong>{{ $candidature->email }}</strong>. Vous pourrez suivre l'état de votre candidature avec ce numéro.</p>
            <div class="d-flex gap-2 justify-content-center mt-3">
                <a href="{{ route('candidature.suivi') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-magnifying-glass"></i> Suivre ma candidature</a>
                <a href="{{ route('login') }}" class="btn btn-secondary btn-sm">Terminer</a>
            </div>
        </div></div>
    </div>
@endsection
