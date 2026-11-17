@include('errors._page', [
    'code'    => 419,
    'titre'   => 'Session expirée',
    'icone'   => 'fa-hourglass-end',
    'fond'    => 'rgba(245,158,11,.14)',
    'teinte'  => '#B45309',
    'message' => $exception?->getMessage() ?: "Votre session a expiré par sécurité après une période d'inactivité.",
    'detail'  => 'Reconnectez-vous, puis reprenez votre saisie. Les données non enregistrées sont perdues.',
])
