<?php

/*
|--------------------------------------------------------------------------
| Proxys de confiance
|--------------------------------------------------------------------------
|
| Derrière un proxy (Cloudflare, répartiteur de l'hébergeur), l'adresse
| IP du visiteur et le protocole HTTPS n'arrivent plus directement :
| ils sont rapportés dans les en-têtes X-Forwarded-*. L'application ne
| doit croire ces en-têtes QUE s'ils viennent du proxy.
|
| Avec « * », elle les croit de n'importe qui. Si le serveur est
| joignable sans passer par le proxy, un visiteur peut alors déclarer
| l'adresse IP de son choix — et contourner la limitation des essais de
| connexion, ou signer le journal d'audit d'une adresse inventée.
|
| TRUSTED_PROXIES dans le .env :
|
|   cloudflare    les plages d'adresses publiées par Cloudflare
|                 (à utiliser sur le VPS, qui est derrière Cloudflare) ;
|   *             le proxy qui nous parle, quel qu'il soit (valeur par
|                 défaut, celle d'un hébergement mutualisé classique) ;
|   1.2.3.4,...   une liste d'adresses ou de plages CIDR.
|
*/

$valeur = trim((string) env('TRUSTED_PROXIES', '*'));

/* Plages Cloudflare : https://www.cloudflare.com/ips/ — elles changent
   rarement (dernier ajout IPv4 en 2021). A revoir une fois l'an. */
$cloudflare = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
    '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    // Le serveur lui-même (tâches locales, sondes)
    '127.0.0.1', '::1',
];

return [
    'proxies' => match (true) {
        $valeur === '' || $valeur === '*'     => '*',
        strtolower($valeur) === 'cloudflare'  => $cloudflare,
        default => array_values(array_filter(array_map('trim', explode(',', $valeur)))),
    },
];
