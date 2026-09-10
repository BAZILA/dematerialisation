{{--
    Mot de passe provisoire, affiché UNE SEULE FOIS.

    Il transite par la session, jamais par la base ni par le journal
    d'audit. Un rechargement de page le fait disparaître définitivement.

    Ce fragment est inclus partout où une réinitialisation peut partir :
    l'écran Utilisateurs pour le personnel, l'écran Identités pour les
    usagers — dont les comptes n'apparaissent nulle part ailleurs.
--}}
@if (session('mot_de_passe_provisoire'))
    @php $mdp = session('mot_de_passe_provisoire'); @endphp
    <div class="alert alert-warning d-flex align-items-start gap-3 mb-4">
        <i class="fas fa-key fs-4 mt-1"></i>
        <div>
            <strong>Mot de passe provisoire — notez-le maintenant</strong>
            <div class="mt-2">
                Identifiant : <code style="font-size:15px;">{{ $mdp['identifiant'] }}</code><br>
                Mot de passe : <code style="font-size:17px;letter-spacing:1px;">{{ $mdp['valeur'] }}</code>
            </div>
            <div class="small mt-2">
                Il ne sera plus jamais affiché. Transmettez-le de vive voix, et
                demandez à la personne de le changer dès sa connexion.
            </div>
        </div>
    </div>
@endif
