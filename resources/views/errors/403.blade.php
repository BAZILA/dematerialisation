@include('errors._page', [
    'code'    => 403,
    'titre'   => 'Accès refusé',
    'icone'   => 'fa-lock',
    'fond'    => 'rgba(220,38,38,.12)',
    'teinte'  => '#DC2626',
    'message' => $exception?->getMessage() ?: "Vous n'avez pas les droits nécessaires pour consulter cette page.",
    'detail'  => "Si vous pensez qu'il s'agit d'une erreur, rapprochez-vous de votre responsable : vos accès dépendent de votre rôle et de votre périmètre d'affectation.",
])
