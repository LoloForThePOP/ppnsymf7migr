# Article UI Status

## Scope

This document tracks the current UI exposure of the internal Article feature and how to re-enable it quickly.

## Current Status (2026-02-13)

Article routes and backend logic are still present, but Article entry points are removed from the main UI.

### Removed from UI

- Navbar link to articles list removed:
  - `templates/_partials/header_navbar.html.twig`
- Homepage "Propon Articles" section removed:
  - `templates/home/homepage.html.twig`
- Homepage controller no longer fetches/passes article list:
  - `src/Controller/HomeController.php`

### Still Active (not exposed in main UI)

- Article routes/controllers remain available:
  - `src/Controller/ArticleController.php`
  - routes: `index_articles`, `show_article`, `edit_article`
- Article templates remain available:
  - `templates/article/index.html.twig`
  - `templates/article/show.html.twig`
  - `templates/article/edit.html.twig`
  - `templates/article/card.html.twig`
- Article image upload endpoint remains available:
  - `src/Controller/ArticleImageUploadController.php`

## Fast Re-enable Checklist

If we want to restore Article UI quickly:

1. Re-add navbar link:
   - Edit `templates/_partials/header_navbar.html.twig`
   - Reinsert link to `path('index_articles')`
2. Re-add homepage block:
   - Restore "Propon Articles" section in `templates/home/homepage.html.twig`
   - Re-enable article cards loop (`article/card.html.twig`)
3. Restore homepage data feed:
   - Re-add `ArticleRepository` injection in `src/Controller/HomeController.php`
   - Re-add article query and `'articles'` in render payload
4. Optional: restore editorial shortcut in footer:
   - `templates/_partials/footer.html.twig` currently contains a commented "Écrire un article" link

## Verification

After re-enable:

1. Load homepage and confirm Article block renders.
2. Check navbar link opens `/articles`.
3. Open one article and validate `/articles/show/{slug}`.
4. If edit UI is needed, verify `/articles/edit` permissions and image upload behavior.
