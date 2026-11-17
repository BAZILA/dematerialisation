@include('errors._page', [
    'code'    => 500,
    'titre'   => 'Erreur interne',
    'icone'   => 'fa-triangle-exclamation',
    'fond'    => 'rgba(245,158,11,.14)',
    'teinte'  => '#B45309',
    'message' => $exception?->getMessage() ?: "Une erreur inattendue s'est produite de notre côté.",
    'detail'  => "L'incident a été enregistré. Si le problème persiste, signalez-le à l'administration en précisant ce que vous faisiez.",
])
