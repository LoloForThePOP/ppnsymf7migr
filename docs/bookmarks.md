# Bookmarks (Favoris)

## Objectif

Permettre à un utilisateur connecté d'enregistrer des présentations projet en marque-pages, puis de les retrouver dans une page dédiée.

## Schéma DB

- Table: `bookmark`
- Colonnes:
  - `id`
  - `user_id` (FK vers `user.id`, `ON DELETE CASCADE`)
  - `project_presentation_id` (FK vers `ppbase.id`, `ON DELETE CASCADE`)
  - `created_at` (`datetime_immutable`)
- Contraintes:
  - `UNIQUE(user_id, project_presentation_id)`
  - index `user_id`
  - index `project_presentation_id`

Migration: `migrations/Version20260203164000.php`

## Backend

- Entité: `src/Entity/Bookmark.php`
- Repository: `src/Repository/BookmarkRepository.php`
- Toggle AJAX:
  - Route: `ajax_bookmark_pp`
  - URL: `POST /project/{stringId}/bookmark`
  - Contrôleur: `src/Controller/ProjectPresentation/BookmarkController.php`
  - Sécurité:
    - `ROLE_USER` requis
    - CSRF token: `bookmark{stringId}`
    - rate limiter: `bookmark_toggle_user`
- Page "Mes marque-pages":
  - Route: `user_bookmarks_index`
  - URL: `/my-bookmarks`
  - Contrôleur: `src/Controller/UserBookmarksController.php`
  - Template: `templates/user/bookmarks/index.html.twig`

## Intégration UI

- Bouton bookmark sur les cards:
  - `templates/project_presentation/cards/card.html.twig`
  - CSS: `public/css/project_card.css`
- Lien vers "Mes marque-pages":
  - Menu utilisateur navbar: `templates/_partials/header_navbar.html.twig`
  - Page profil (si c'est son propre profil): `templates/user/profile/index.html.twig`

## Entités enrichies

- `src/Entity/User.php`
  - relation `bookmarks`
  - helper `hasBookmarkedProject(PPBase $project): bool`
- `src/Entity/PPBase.php`
  - relation `bookmarks`
  - helper `isBookmarkedBy(User $user): bool`

## Commandes utiles

```bash
php bin/console doctrine:migrations:migrate
```
