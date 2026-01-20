# Theme Management

This doc explains how theme selection, storage, and CSS variables work in the app.

## Theme attributes

Theme state is exposed as data attributes on `<html>` and `<body>`:

- `data-theme`: the active theme id (ex: `classic`, `dark`, `mint`).
- `data-theme-variant`: `classic` for the legacy theme, `custom` for all others.
- `data-theme-tone`: `light` or `dark` (used to scope dark-friendly overrides).
- `body[data-theme-scope]`: `presentation` when the user is on a 3P page, `default` elsewhere.

## Source of truth

### Server-side (initial render)

`templates/base.html.twig` sets:

- `data-theme` and `data-theme-variant` based on the user profile or `classic`.
- `data-theme-tone` based on a `darkThemes` list (same list is reused on the client).

It also has a small script for anonymous users that reads `localStorage` and replays
the same attributes before the page fully loads.

### Client-side (switcher)

`public/js/theme_selector.js` is the runtime controller:

- Normalizes `light` => `classic`.
- Sets `data-theme`, `data-theme-variant`, and `data-theme-tone`.
- Updates the theme selector UI.
- Persists the choice to `localStorage` for anonymous users.
- Sends the choice to the backend for authenticated users (via `user_theme_update`).

`templates/_partials/theme_selector.html.twig` provides the theme list and marks each
option with a `data-theme-tone="light|dark"` attribute.

## CSS variables

The core theme variables live in `public/css/app.css`:

- Base defaults are defined under `:root`.
- Each theme overrides its palette under `:root[data-theme="..."]`.

Examples:

- `--theme-bg`, `--theme-surface`, `--theme-text`, `--theme-border`
- `--theme-accent`, `--theme-accent-contrast`
- `--pp-*` variables for project presentation pages

Use these variables instead of hard-coded colors whenever possible.

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

- Inspect `<html>`: `data-theme`, `data-theme-variant`, `data-theme-tone`.
- Check that `:root[data-theme="..."]` overrides are loaded (app.css).
- Confirm `public/js/theme_selector.js` runs after toggling a theme.
