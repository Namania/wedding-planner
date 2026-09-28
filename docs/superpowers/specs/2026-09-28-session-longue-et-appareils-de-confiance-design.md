# Session longue et appareils de confiance

## Contexte

L'authentification admin est une session Sanctum SPA : un cookie de session
chiffré, `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`. Il n'y a ni JWT ni
couple access/refresh nulle part dans le projet.

La demande — « access 5 min, refresh 1 semaine » — n'a donc pas de traduction
directe, mais elle en a une native : Laravel possède déjà les deux étages.

| Vocabulaire de la demande | Équivalent Laravel |
|---|---|
| access token, 5 min | cookie de session, `SESSION_LIFETIME=5` |
| refresh token, 1 semaine | cookie « remember me » adossé à `users.remember_token` |

Quand la session expire, le cookie remember reconnecte silencieusement et en
ouvre une neuve. C'est le comportement demandé, sans réécrire l'authentification.

S'y ajoutent des appareils de confiance : sur un appareil marqué comme tel, le
mot de passe reste exigé mais l'étape TOTP est sautée pendant 30 jours.

## Décisions validées

- **Posture de sécurité assumée** : une semaine sans ressaisir le mot de passe,
  trente jours sans second facteur.
- **`SESSION_LIFETIME=5` partout**, développement compris, pour que le chemin de
  reconnexion soit exercé là où il se corrige plutôt que découvert en production.
- **Rotation du `remember_token` à chaque réutilisation.** Sans elle, la
  découpe 5 min / 1 semaine n'apporte rien : Laravel ne fait tourner ce jeton
  qu'à la déconnexion, donc un cookie volé vaudrait une semaine d'accès —
  exactement comme une session d'une semaine.
- **Révocation depuis les réglages**, pas seulement en ligne de commande.
- **Les futurs comptes invités seront des lignes `users` porteuses d'un rôle**,
  et non un modèle distinct. La table des appareils reste donc sur `user_id`,
  et rien ne devient polymorphe. Ces comptes ne sont pas créés ici ; seuls les
  points d'accroche qui les rendront possibles le sont.

## Hors périmètre

- Le rôle invité lui-même, et la migration de `GalleryGuest` vers `users`.
- Le nettoyage automatique des appareils expirés. Ils sont filtrés en lecture et
  le compte admin est unique : une tâche planifiée pour quelques lignes en base
  serait du poids inutile.
- Les codes de secours, écartés lors de la mise en place du TOTP. La commande
  `user:disable-2fa` reste le filet.

## Backend

### Durée de session et remember me

- **`config/auth.php`** : `'remember_lifetime' => (int) env('AUTH_REMEMBER_LIFETIME', 10080)`
  (minutes, soit sept jours) et `'trusted_device_days' => (int) env('AUTH_TRUSTED_DEVICE_DAYS', 30)`.
- **`app/Providers/AppServiceProvider.php`** : `Auth::guard('web')->setRememberDuration(config('auth.remember_lifetime'))`
  dans `boot()`. Sans cela le cookie durerait le défaut de Laravel, environ
  quatre cents jours.
- **`AuthController::completeLogin()`** : `Auth::guard('web')->login($user, remember: true)`.
- **`compose.yml`, `compose.prod.yml`, `.env.example`** : `SESSION_LIFETIME=5`.

### Rotation du jeton remember

Nouveau middleware `app/Http/Middleware/RotateRememberToken.php`, placé après
`auth:sanctum` sur toute route authentifiée par la garde de session — le groupe
admin aujourd'hui, et les routes invités le jour où elles existeront. Il ne
suppose rien du type de compte.

`Auth::viaRemember()` n'est vrai que sur la requête où le cookie a ressuscité la
session : une fois celle-ci ouverte, les requêtes suivantes passent par elle. La
rotation a donc lieu exactement une fois par reconnexion. Le middleware
régénère `users.remember_token` puis réémet le cookie, ce qui réduit un vol de
cookie à une seule réutilisation au lieu d'une semaine.

### Appareils de confiance

Migration `create_two_factor_trusted_devices_table` :

| Colonne | Type | Rôle |
|---|---|---|
| `user_id` | FK `users`, cascade | propriétaire |
| `token_hash` | string, unique, indexé | SHA-256 du jeton du cookie |
| `name` | string nullable | libellé lisible déduit du user-agent |
| `user_agent` | text nullable | pour que l'écran de révocation soit parlant |
| `ip_address` | string(45) nullable | idem, IPv6 compris |
| `last_used_at` | timestamp nullable | repérer un appareil oublié |
| `expires_at` | timestamp | 30 jours après l'émission |

Le jeton est haché en **SHA-256 et non bcrypt** : un hash déterministe se
retrouve par index, là où bcrypt imposerait de balayer la table et de comparer
ligne à ligne.

Nouveau modèle `app/Models/TwoFactorTrustedDevice.php` et service
`app/Services/TrustedDeviceRegistry.php` exposant `issueFor(Authenticatable)`,
`findValidFor(Authenticatable, string $token)`, `revoke()`, `revokeAll()`.

**La logique vit dans le service, pas dans `AuthController`** : c'est ce qui
permettra aux comptes invités de s'en servir sans dupliquer le contrôleur. Le
service est écrit contre le contrat `Authenticatable`, jamais contre `User`.

### Flux de connexion

`AuthController::login()`, après validation du mot de passe :

1. Un cookie `trusted_device` valide et appartenant à ce compte → `completeLogin()`
   directement, `last_used_at` touché. Le mot de passe reste donc toujours exigé.
2. Sinon, compte avec 2FA active → `two_factor: required`, inchangé.
3. Sinon, compte dont le rôle exige la 2FA → `two_factor: setup_required`, inchangé.
4. Sinon → `completeLogin()`.

Les branches 3 et 4 sont l'accroche pour les comptes invités : aujourd'hui
`login()` pousse **tout** compte non enrôlé vers l'enrôlement, ce qui
imposerait le TOTP aux invités le jour où ils existeront. On introduit
`User::requiresTwoFactor()`, qui renvoie `true` pour l'instant et deviendra
`$this->role !== 'guest'`. Une méthode qui renvoie une constante se justifie
ici : elle donne un point d'atterrissage unique et nommé à une décision déjà
prise.

`twoFactorSetup()` et `twoFactorChallenge()` acceptent un `trust_device`
booléen optionnel. À `true`, le service crée l'enregistrement et la réponse
repart avec le cookie.

**Le cookie est attaché par `->withCookie()` sur la réponse, et non par
`Cookie::queue()`.** La file de cookies n'est vidée dans la réponse que par
`AddQueuedCookiesToResponse`, absent du groupe `api` : il n'est présent que
parce que le `EnsureFrontendRequestsAreStateful` de Sanctum l'applique aux
requêtes venant d'un domaine déclaré stateful. Dépendre de cet empilement
rendrait la pose du cookie silencieusement fragile.

La déconnexion ne révoque pas l'appareil de confiance : c'est sa raison d'être.

### Routes

Sous `auth:sanctum` + `admin.user` :

- `GET /api/two-factor/devices` — liste, l'appareil courant marqué
- `DELETE /api/two-factor/devices/{device}` — révoque
- `DELETE /api/two-factor/devices` — révoque tout

Révoquer l'appareil courant efface aussi son cookie.

## Frontend

- **`api/client.ts`** : sur un `419`, rappeler `/sanctum/csrf-cookie` puis
  rejouer la requête une fois, protégé par un drapeau anti-boucle. Sans cela,
  une session de cinq minutes produit un « Page Expired » à chaque écriture qui
  suit une période d'inactivité : la reconnexion par remember régénère la
  session, donc le jeton CSRF, et le SPA envoie encore l'ancien.
- **`stores/auth.ts`** : `submitTwoFactor(code, trustDevice)`. La réponse de
  `login()` peut désormais contenir un `user` directement (appareil de
  confiance) : le store doit traiter ce cas au lieu de supposer un second
  facteur.
- **`views/LoginView.vue`** : case « Se souvenir de cet appareil » sur l'étape
  TOTP, dans les conventions du fichier.
- **`components/TwoFactorDevices.vue`** (nouveau), inclus par `SettingsView.vue`.
  Composant dédié plutôt qu'une section de plus dans une vue qui ne parle
  aujourd'hui que du mariage.

## Points de vigilance

- **`EnsureAdminUser` ne teste qu'un `instanceof User`.** Le jour où les invités
  seront des lignes `users`, ce middleware les laissera entrer dans toute
  l'administration. Rien à corriger ici puisque le rôle n'existe pas, mais c'est
  la première chose à reprendre quand il arrivera.
- **Les invités actuels ne sont pas concernés** par le raccourcissement de la
  session : `GalleryGuest` s'authentifie par jeton Bearer en `localStorage`, pas
  par cookie de session. Vérifié avant conception.
- **`two_factor_secret` ne doit pas fuiter.** `GET /api/user` sérialise le modèle
  brut ; l'attribut `#[Hidden]` le couvre, un test existant le garde.
- **Le `remember_token` est effacé à la déconnexion** par Laravel. Une
  déconnexion explicite coupe donc bien la semaine.

## Vérification

- `tests/Feature/TrustedDeviceTest.php` : un appareil valide saute le TOTP ; un
  appareil expiré non ; un appareil appartenant à un autre compte non ; la
  révocation rétablit le TOTP ; `trust_device: false` ne crée rien.
- La rotation elle-même : une requête authentifiée par le seul cookie remember
  doit changer `users.remember_token` et réémettre le cookie, et l'ancien cookie
  ne doit plus reconnecter. C'est le test qui donne sa valeur à la découpe
  5 min / 1 semaine ; sans lui, la rotation peut échouer en silence et personne
  ne le verra.
- Dans les tests 2FA existants : la durée réelle du cookie remember, et la
  non-régression de `two_factor_secret` absent de `/api/user`.
- `php artisan test` complet, pour le chemin invité de la galerie.
- Navigateur réel : connexion, appareil de confiance coché, déconnexion,
  reconnexion sans TOTP, révocation depuis les réglages, TOTP redemandé.
