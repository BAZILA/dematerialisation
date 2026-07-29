@include('errors._page', [
    'code'    => 503,
    'titre'   => 'Maintenance en cours',
    'icone'   => 'fa-screwdriver-wrench',
    'fond'    => 'rgba(26,86,168,.12)',
    'teinte'  => '#1A56A8',
    'message' => $exception?->getMessage() ?: 'La plateforme est momentanément indisponible pour maintenance.',
    'detail'  => 'Merci de revenir dans quelques instants. Vos données sont intactes.',
])
