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
<img class="icon-themed icon-strong" src="{{ asset('share.svg', 'misc') }}" alt="share icon">
```

## Theme control

Theme filters are defined in `public/css/app.css`:

- `--theme-icon-filter`
- `--theme-icon-filter-strong`

Adjust them per theme if a stronger or different contrast is needed.

## Project Presentation Architecture

To keep theming predictable in project presentation screens, we split icon rendering by context:

- 3P interaction icons (`like`, `bookmark`, `share`, `follow`, `comment`): inline SVG with `currentColor`.
- Card stats icons (`like`, `comment`, `views`): SVG assets via `<img>` + `icon-themed`.

This avoids mixed behavior in one UI area and keeps color decisions explicit:

- inline SVG relies on CSS variables and text color inheritance.
- asset icons rely on the theme filter pipeline (`--theme-icon-filter`).

## Card Stat Assets

The card stat assets are normalized to monochrome `currentColor` sources:

- `public/media/static/images/icons/miscellaneous/thumb_up.svg`
- `public/media/static/images/icons/miscellaneous/comment.svg`
- `public/media/static/images/icons/miscellaneous/eye.svg`

Keep these files single-color only. Avoid editor metadata and hardcoded brand colors.

## Release Visual Checklist

Run this check before releasing theme/icon changes:

1. Verify 3P interaction icons color-alignment in `retro-game`, `mario-love`, `dark`.
2. Verify card stats icons are legible and balanced in the same themes.
3. Verify active/inactive states: `bookmark`, `like`, `follow`.
4. Verify share icon is not inheriting default link blue.
5. Verify mobile + desktop layouts (cards and 3P header/misc blocks).
