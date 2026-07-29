@include('errors._page', [
    'code'    => 429,
    'titre'   => 'Trop de tentatives',
    'icone'   => 'fa-gauge-high',
    'fond'    => 'rgba(220,38,38,.12)',
    'teinte'  => '#DC2626',
    'message' => $exception?->getMessage() ?: 'Vous avez effectué trop de requêtes en peu de temps.',
    'detail'  => 'Cette limite protège la plateforme des abus. Patientez une minute avant de réessayer.',
])
