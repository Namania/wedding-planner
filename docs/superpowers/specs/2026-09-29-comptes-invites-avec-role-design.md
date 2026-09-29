# Comptes invités avec rôle

## Contexte

Un invité de la galerie s'inscrit aujourd'hui depuis un lien de partage avec un
prénom et un code PIN. Il est représenté par un modèle `GalleryGuest` distinct
de `User`, et s'authentifie par jeton Bearer conservé dans `localStorage`.
L'unicité porte sur le prénom normalisé, d'où le message « Ce prénom est déjà
pris — ajoutez une initiale ou un surnom. »

La demande : un vrai compte, avec email, mot de passe et nom, les trois
obligatoires.

## Décisions validées

- **Les invités deviennent des lignes `users` porteuses d'un rôle** `guest`, et
  s'authentifient par session comme les administrateurs. `GalleryGuest`
  disparaît.
- **Le rôle sert d'abord à les tenir hors des préparatifs.** C'est la raison
  explicite du choix.
- **Rien à conserver en production** : la galerie n'a jamais été ouverte. La
  migration repart de zéro, sans code de transition.
- **La récupération d'un mot de passe oublié reste manuelle** : les mariés
  génèrent un mot de passe temporaire depuis l'écran d'administration, comme ils
  réinitialisent un PIN aujourd'hui. Aucun envoi de mail n'est configuré
  (`MAIL_MAILER: log` en production) et en configurer un est un chantier à part.
- **Conséquence acceptée** : avec une seule session par navigateur, un
  administrateur ne peut pas consulter la galerie côté invité depuis son
  navigateur connecté. L'écran d'administration de la galerie, qui montre déjà
  toutes les photos, reste son point d'entrée.

## Intentions poursuivies

Identifier les invités de façon fiable, disposer de leur email pour les
recontacter plus tard, et remplacer un PIN de quatre chiffres par un mot de
passe. La préparation d'un portail invité plus large a été explicitement
écartée : le compte ne sert que la galerie.

## Hors périmètre

- L'envoi de mails, donc les liens de réinitialisation et la vérification
  d'adresse.
- Toute fonctionnalité invité au-delà de la galerie.
- Les codes de secours et la double authentification pour les invités : le
  second facteur reste réservé aux comptes d'administration.

## Le piège central

Trois endroits décident aujourd'hui « administrateur ou invité » en testant le
**type** de l'objet authentifié. Le jour où les invités sont des `User`, ces
trois tests deviennent vrais pour eux :

| Endroit | Test actuel | Conséquence si oublié |
|---|---|---|
| `app/Http/Middleware/EnsureAdminUser.php` | `$user instanceof User` | un invité entre dans toute l'administration du mariage |
| `routes/channels.php`, canal `wedding` | `$user instanceof User` | un invité écoute les événements des préparatifs |
| `routes/channels.php`, canal `gallery` | `instanceof User \|\| instanceof GalleryGuest` | un administrateur banni resterait autorisé |

Ces trois lignes sont la partie la plus importante du chantier. Tout le reste
est mécanique ; celles-ci sont la raison d'être du rôle.

## Base de données

Sur `users` :

| Colonne | Type | Rôle |
|---|---|---|
| `role` | string, indexé, sans défaut | `admin` ou `guest` |
| `banned_at` | timestamp nullable | bannissement d'un invité |
| `last_seen_at` | timestamp nullable | dernière activité d'un invité |

`banned_at` et `last_seen_at` n'ont de sens que pour les invités. Les porter sur
`users` plutôt que dans une table de profil séparée est assumé : deux colonnes
nullables coûtent moins qu'une jointure et une seconde entité, et le projet n'a
qu'un seul type d'invité.

Le rôle n'a **pas** de valeur par défaut : un compte créé sans rôle explicite
doit échouer plutôt que de devenir administrateur par inadvertance. La migration
donne `admin` aux comptes existants.

Sur `gallery_photos` : `gallery_guest_id` devient `user_id`, contrainte vers
`users`, suppression en cascade.

La table `gallery_guests` est supprimée.

## Backend

- **`app/Models/User.php`** : constantes `ROLE_ADMIN` et `ROLE_GUEST`, helpers
  `isAdmin()` et `isGuest()`, `banned_at` et `last_seen_at` castés en `datetime`,
  `isBanned()`, relation `photos()`. `requiresTwoFactor()` devient
  `$this->role !== self::ROLE_GUEST` — c'est le point d'accroche posé par la
  spec de la session longue, et le seul endroit à reprendre pour cela.
- **`app/Models/GalleryGuest.php`** : supprimé, ainsi que sa fabrique.
  `GalleryGuestResource` est renommée en `GuestResource` et pointe sur `User`.
  **Sa forme de réponse ne change pas** — `id`, `name`, `banned`,
  `photos_count`, `last_seen_at`, `created_at` — car le front la consomme telle
  quelle ; seul `email` s'y ajoute, et uniquement pour l'écran
  d'administration.
- **`app/Http/Middleware/EnsureAdminUser.php`** : teste `isAdmin()`.
- **`app/Http/Middleware/EnsureGalleryGuest.php`** : teste `isGuest()` et
  l'absence de bannissement ; la mise à jour horaire de `last_seen_at` est
  conservée.
- **`app/Http/Controllers/GalleryAuthController.php`** :
  - `register()` valide `token`, `name`, `email`, `password`. L'email doit être
    unique sur `users` : un invité ne peut donc pas réutiliser l'adresse des
    mariés, et le message d'erreur doit le dire sans révéler que cette adresse
    est celle d'un compte d'administration. Crée l'utilisateur avec
    `role = guest`, ouvre la session et renvoie l'utilisateur.
  - `login()` valide `email` et `password`, refuse un compte qui n'est pas
    invité ou qui est banni, ouvre la session.
  - Les deux ouvrent la session avec `remember: true` — voir plus bas.
  - `logout()` passe de la suppression du jeton à l'invalidation de session.
- **`app/Http/Controllers/GalleryAdminController.php`** : `resetPin` devient
  `resetPassword` et renvoie un mot de passe temporaire affiché une seule fois ;
  `guests`, `ban`, `unban`, `destroyGuest` et `export` filtrent sur le rôle
  invité au lieu d'interroger `gallery_guests`.
- **`app/Http/Middleware/PreferAccessToken.php`** : supprimé, ainsi que son alias
  et son insertion dans la liste de priorité de `bootstrap/app.php`. Il
  n'existait que pour arbitrer entre un jeton invité et une session
  administrateur sur les routes galerie ; ce conflit disparaît avec un seul
  modèle et une seule mécanique d'authentification.
- **`routes/api.php`** : le groupe galerie perd `prefer.token`. Les limiteurs
  `gallery-register` et `gallery-login` sont conservés.

### La session courte ne doit pas gêner les invités

`SESSION_LIFETIME` vaut cinq minutes. Un invité connecté sur son téléphone
pendant une soirée ne doit pas le vivre. L'inscription et la connexion invité
ouvrent donc la session avec `remember: true` : le cookie de reconnexion vaut
une semaine, et la session courte devient invisible. Le middleware
`remember.rotate` s'applique aussi aux routes galerie, pour que le jeton tourne
à chaque réutilisation comme pour les administrateurs.

## Frontend

- **`resources/src/api/galleryClient.ts`** : les jetons Bearer et `localStorage`
  disparaissent, avec `getGalleryToken` et `setGalleryToken`. Le client passe en
  `withCredentials: true` et `withXSRFToken: true`, et reçoit **le même rejeu
  sur 419** que `client.ts` : sans lui, la première photo envoyée après une
  pause échouerait en « Page Expired », la reconnexion silencieuse ayant
  régénéré le jeton CSRF.
- **`resources/src/views/GalleryLoginView.vue`** : le formulaire d'inscription
  demande nom, email et mot de passe ; celui de connexion, email et mot de
  passe. Le message « Ce prénom est déjà pris » disparaît, l'unicité portant
  désormais sur l'email.
- **`resources/src/views/GalleryAdminView.vue`** : la réinitialisation du PIN
  devient une réinitialisation de mot de passe, et la liste affiche l'email.
- Il n'existe pas de store Pinia pour les invités : le jeton est manipulé
  directement dans `ShareView.vue`, `GalleryLoginView.vue`, `GalleryView.vue` et
  `galleryEcho.ts`. Ces quatre fichiers doivent cesser de le faire et s'appuyer
  sur `/api/gallery/me` pour savoir si une session est ouverte.
- **`resources/src/galleryEcho.ts`** mérite une attention particulière : il
  autorise le canal temps réel en posant lui-même un en-tête `Authorization`
  porteur du jeton. Il doit passer à l'envoi du cookie de session, comme le fait
  déjà `echo.ts` côté administration.

## Points de vigilance

- **Le rôle sans défaut est délibéré.** Une colonne `role` avec
  `default('admin')` transformerait tout oubli en faille ; sans défaut, l'oubli
  devient une erreur d'insertion visible en test.
- **L'unicité de l'email est globale.** Elle traverse les deux rôles : c'est
  voulu, un même email ne peut pas être à la fois administrateur et invité.
- **Les tests d'étanchéité doivent survivre.**
  `tests/Feature/GalleryAuthTest.php` contient déjà
  `test_gallery_guest_token_cannot_access_admin_api` et
  `test_admin_session_cannot_use_guest_gallery_routes`. Ils doivent être
  réécrits pour porter sur le rôle, et rester verts — ce sont eux qui
  démontrent que la demande est satisfaite.
- **Les appareils de confiance et le second facteur ne concernent pas les
  invités.** Aucun code à ajouter : `requiresTwoFactor()` renvoyant `false`
  suffit à les faire entrer directement après le mot de passe.
- **La vérification navigateur compte double ici.** Le passage du jeton en
  `localStorage` au cookie de session touche au chemin le plus utilisé de
  l'application le jour du mariage, sur des téléphones.

## Vérification

- Les deux tests d'étanchéité réécrits sur le rôle, plus un test qui prouve
  qu'un invité authentifié reçoit 403 sur une route d'administration et qu'un
  administrateur reçoit 403 sur une route galerie.
- Un test par canal de diffusion : un invité ne doit pas être autorisé sur
  `wedding`, un invité banni ne doit pas l'être sur `gallery`.
- Inscription complète : jeton d'invitation, nom, email, mot de passe, session
  ouverte, cookie de reconnexion posé.
- Email déjà pris, mot de passe trop court, jeton d'invitation invalide,
  inscriptions fermées : chacun produit une erreur lisible.
- Réinitialisation du mot de passe par l'administration, puis connexion avec le
  mot de passe temporaire.
- `php artisan test` complet, pour la non-régression des parcours
  d'administration et de la double authentification.
- Navigateur réel : inscription depuis un lien de partage, envoi d'une photo,
  déconnexion, reconnexion, et vérification qu'une route d'administration est
  refusée.
