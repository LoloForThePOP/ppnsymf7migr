# Theme Management

This doc explains how theme selection, storage, and CSS variables work in the app.

## Theme attributes

Theme state is exposed as data attributes on `<html>` and `<body>`:

- `data-theme`: the active theme id (ex: `classic`, `dark`, `mint`).
- `data-theme-tone`: `light` or `dark` (used to scope dark-friendly overrides).
- `body[data-theme-scope]`: `presentation` when the user is on a 3P page, `default` elsewhere.

## Source of truth

### Server-side (initial render)

`templates/base.html.twig` sets:

- `data-theme` based on the user profile or `classic`.
- `data-theme-tone` based on a `darkThemes` list (same list is reused on the client).

It also has a small script for anonymous users that reads `localStorage` and replays
the same attributes before the page fully loads.

### Client-side (switcher)

`public/js/theme_selector.js` is the runtime controller:

- Normalizes `light` => `classic`.
- Sets `data-theme` and `data-theme-tone`.
- Updates the theme selector UI.
- Persists the choice to `localStorage` for anonymous users.
- Sends the choice to the backend for authenticated users (via `user_theme_update`).

`templates/_partials/theme_selector.html.twig` provides the theme list and marks each
option with a `data-theme-tone="light|dark"` attribute.

### Hidden but supported themes

Some themes can stay supported in CSS/backend but be intentionally hidden from the
selector UI. Current case:

- `eco-green` (`Nature végétale`): hidden in selector, still accepted as a stored
  theme value and still rendered by CSS tokens.

## CSS variables

The core theme variables live in `public/css/app.css`:

- Base defaults are defined under `:root`.
- Each theme overrides its palette under `:root[data-theme="..."]`.

Examples:

- `--theme-bg`, `--theme-surface`, `--theme-text`, `--theme-border`
- `--theme-accent`, `--theme-accent-contrast`
- `--pp-*` variables for project presentation pages

Use these variables instead of hard-coded colors whenever possible.

## Color ownership rules

To avoid color conflicts when themes evolve:

- Do not use Bootstrap `text-*` utility classes (`text-white`, `text-dark`, `text-secondary`, etc.) as the color source on themed interactive components.
- Avoid color variant utility classes (`btn-success`, `btn-danger`, etc.) for themed CTAs unless the intent is explicitly non-themed status signaling.
- Prefer semantic classes per component (`.create-presentation-button`, `.news-card-edit-link`, etc.) and map colors from theme tokens (`--theme-*`).
- Keep utility classes for layout/spacing (`d-*`, `mb-*`, `text-center`, `text-decoration-none`, etc.).

Rationale: utility color classes and token-driven theming can override each other unpredictably (especially with selector specificity and `!important`), which causes regressions during refactors.

## Typography readability baseline

Some fonts render visually smaller than others at the same `font-size` (low x-height).
To avoid per-page patches, the app uses a global theme-level readability baseline in
`public/css/app.css`.

### Global tokens

- `--theme-font-size-adjust-body`: applied on `body` via `font-size-adjust`.
- `--theme-font-size-adjust-heading`: applied on headings (`h1..h6`, `.pp-struct-title`).
- `--theme-font-size-fallback-scale`: fallback root scale when `font-size-adjust` is not supported.

### Global application

- `body` uses:
  - `font-size-adjust: var(--theme-font-size-adjust-body, none);`
- Headings use:
  - `font-size-adjust: var(--theme-font-size-adjust-heading, none);`
- Fallback:
  - `@supports not (font-size-adjust: 1) { :root { font-size: var(--theme-font-size-fallback-scale, 100%); } }`

### Theme tuning workflow

When introducing a theme or changing its body font:

1. Set `--theme-font-body` / `--theme-font-heading`.
2. If readability is too small, set:
   - `--theme-font-size-adjust-body` (example: `0.56`)
   - optional `--theme-font-size-adjust-heading`
   - matching fallback `--theme-font-size-fallback-scale` (example: `112%`)
3. Validate across key pages (3P, cards, forms, auth pages) on desktop + mobile.

Current Alegreya-based themes (`eco-green`, `deep-ocean`) use this mechanism.

## Assets

Optional theme assets live in static images:

- Theme previews: `public/media/static/images/theme_thumbnails/{theme}.avif`
- Palette icons: `public/media/static/images/color_palettes/color-palette--{theme}.svg`

If a palette icon is missing, the generic fallback is used.

## Adding a new theme

1) **Define CSS variables**
   - Add a new block in `public/css/app.css`:
     - `:root[data-theme="my-theme"] { ... }`
2) **Register the theme in the selector**
   - Add the theme entry in `templates/_partials/theme_selector.html.twig`.
   - Set a preview palette and `data-theme-tone` (light/dark).
3) **Update the dark theme list**
   - If the theme is dark, add it to the `darkThemes` list in:
     - `templates/base.html.twig`
     - `templates/_partials/theme_selector.html.twig`
4) **Add assets (optional)**
   - `theme_thumbnails/my-theme.avif`
   - `color-palette--my-theme.svg`
5) **Theme-specific overrides (optional)**
   - Use `html[data-theme="my-theme"]` or `html[data-theme-tone="dark"]` as needed.

## Debug checklist

- Inspect `<html>`: `data-theme`, `data-theme-tone`.
- Check that `:root[data-theme="..."]` overrides are loaded (app.css).
- Confirm `public/js/theme_selector.js` runs after toggling a theme.

## Related docs

- `docs/article-ui-status.md`: current UI exposure status for Articles and quick restore steps.
- `docs/README.md`: documentation index.
