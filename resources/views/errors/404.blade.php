@include('errors._page', [
    'code'    => 404,
    'titre'   => 'Page introuvable',
    'icone'   => 'fa-compass',
    'fond'    => 'rgba(26,86,168,.12)',
    'teinte'  => '#1A56A8',
    'message' => $exception?->getMessage() ?: "La page demandée n'existe pas ou a été déplacée.",
    'detail'  => "Vérifiez l'adresse saisie, ou revenez à l'accueil pour reprendre votre navigation.",
])
