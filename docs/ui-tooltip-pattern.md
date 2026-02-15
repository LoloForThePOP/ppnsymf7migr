# UI Tooltip Pattern

This document defines the shared custom tooltip baseline for inline info hints (the small `i` icon).

## Why

- Avoid native browser `title` tooltips (inconsistent style, no theme control).
- Keep one reusable, theme-aware behavior across homepage and other UI blocks.
- Keep keyboard accessibility (`focus` and `focus-visible`) in addition to hover.

## Shared Building Blocks

- Twig helper macro:
  - `templates/_partials/ui_helpers.html.twig`
  - Macro: `info_hint(text, options = {})`
- Global CSS utility:
  - `public/css/app.css`
  - Classes:
    - `.ui-info-hint`
    - `.ui-info-hint__icon`
    - `.ui-info-hint__tooltip`

## Usage

In Twig:

```twig
{% from '_partials/ui_helpers.html.twig' import info_hint %}
{{ info_hint('Votre texte d’aide', { class: 'my-context-class' }) }}
```

Optional local tuning:

- Add a context class in local CSS and override utility variables:
  - `--ui-info-hint-size`
  - `--ui-info-hint-font-size`
  - `--ui-info-hint-opacity`

Example (existing usage in collection headers):

- Twig: `templates/project_presentation/cards/list.html.twig`
- Local tuning CSS: `public/css/titled_project_collection.css` (`.collection-title-info`)

## Rules

- Prefer this pattern for inline informational hints.
- Do not use native `title` for these hints.
- Keep text concise; long explanations should move to popover/modal/help section.
