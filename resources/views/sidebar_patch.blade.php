{{--
    PATCH app.blade.php — remplacer uniquement le bloc @if(auth()->user()->isAdmin()) de la sidebar
    par le contenu ci-dessous. Le reste du fichier reste identique.
--}}

{{-- ── Admin ── --}}
@if(auth()->user()->isAdmin())
    <div class="sb-section">Gestion</div>

    <a href="{{ route('admin.dashboard') }}"
       class="sb-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <span class="sb-icon"><i class="fas fa-gauge-high"></i></span>
        <span class="sb-label">Vue d'ensemble</span>
    </a>

    <div class="sb-section" style="margin-top:6px;">Dépôts & Demandes</div>

    <a href="{{ route('admin.requests.index', 'depot') }}"
       class="sb-link {{ request()->routeIs('admin.requests.*') && request()->route('category')==='depot' ? 'active' : '' }}">
        <span class="sb-icon"><i class="fas fa-folder-open"></i></span>
        <span class="sb-label">Dépôts</span>
    </a>

    <a href="{{ route('admin.requests.index', 'attestation') }}"
       class="sb-link {{ request()->route('category')==='attestation' ? 'active' : '' }}">
        <span class="sb-icon"><i class="fas fa-certificate"></i></span>
        <span class="sb-label">Attestations</span>
    </a>

    <a href="{{ route('admin.requests.index', 'diplome') }}"
       class="sb-link {{ request()->route('category')==='diplome' ? 'active' : '' }}">
        <span class="sb-icon"><i class="fas fa-graduation-cap"></i></span>
        <span class="sb-label">Diplômes</span>
    </a>

    <a href="{{ route('admin.requests.index', 'releve') }}"
       class="sb-link {{ request()->route('category')==='releve' ? 'active' : '' }}">
        <span class="sb-icon"><i class="fas fa-list-alt"></i></span>
        <span class="sb-label">Relevés de notes</span>
    </a>

    <a href="{{ route('admin.requests.index', 'duplicat') }}"
       class="sb-link {{ request()->route('category')==='duplicat' ? 'active' : '' }}">
        <span class="sb-icon"><i class="fas fa-id-card"></i></span>
        <span class="sb-label">Duplicatas carte</span>
    </a>

    <div class="sb-section" style="margin-top:6px;">Configuration</div>

    <a href="{{ route('admin.request-types.index') }}"
       class="sb-link {{ request()->routeIs('admin.request-types.*') ? 'active' : '' }}">
        <span class="sb-icon"><i class="fas fa-sliders"></i></span>
        <span class="sb-label">Types de fichiers</span>
    </a>

{{-- ── Étudiant ── --}}
@else
    <div class="sb-section">Étudiant</div>

    <a href="{{ route('student.dashboard') }}"
       class="sb-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
        <span class="sb-icon"><i class="fas fa-folder-open"></i></span>
        <span class="sb-label">Mes demandes</span>
    </a>
@endif
