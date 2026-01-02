# Theme Icon Helpers

Use these classes to keep SVG icons aligned with the active theme and avoid per-icon CSS overrides.

## Classes

- `icon-themed`: applies the theme icon filter (`--theme-icon-filter`).
- `icon-strong`: bumps contrast by switching to `--theme-icon-filter-strong`.

## When to use

- Monochrome SVGs (single-color fills or `currentColor`).
- UI icons like actions, toolbars, and section headers.

## When not to use

- Multi-color illustrations (logos, mascots, rich SVGs).
- Icons with intentional brand colors.

## Example

```html
<img class="icon-themed" src="{{ asset('search.svg', 'misc') }}" alt="search icon">
<img class="icon-themed icon-strong" src="{{ asset('share_light.svg', 'misc') }}" alt="share icon">
```

## Theme control

Theme filters are defined in `public/css/app.css`:

- `--theme-icon-filter`
- `--theme-icon-filter-strong`

Adjust them per theme if a stronger or different contrast is needed.
