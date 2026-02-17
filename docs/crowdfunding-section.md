# Crowdfunding Section (3P)

This section documents the end-to-end handling of crowdfunding metadata in project presentations and how to extend it for other platforms.

## Data model

- `PPBase.fundingEndAt`: campaign end date (DateTime or null).
- `PPBase.fundingStatus`: normalized status (`ongoing`, `success`, `failed`, `cancelled`, `ended`).
- `PPBase.fundingPlatform`: human label (ex: `Ulule`).

## Ingestion flow

1) **Normalization payload**
   - Accepted keys:
     - `funding_end_at`, `funding_status`, `funding_platform`
     - or `funding: { end_at, end_date, status, platform }`
   - Parsing/normalization happens in:
     - `src/Service/NormalizedProjectPersister.php`
       - `parseFundingEndAt()` handles `DateTime`, Unix timestamp, or ISO 8601 strings.
       - `normalizeFundingStatus()` normalizes common synonyms to the canonical list.

2) **Ulule import**
   - `src/Controller/Admin/UluleImportController.php`
   - `applyFundingMetadata()` injects:
     - `funding_end_at` from Ulule `date_end`
     - `funding_status` derived from `goal_raised`, `finished`, `is_cancelled`, `is_online`
     - `funding_platform` = `Ulule`

## UI rendering

The crowdfunding row is rendered in the 3P upper box:
- `templates/project_presentation/edit_show/upper_box_structure.html.twig`
  - Macro: `fundingInfo(presentation, extraClasses)`
  - Shows: “Projet avec financement participatif (Platform) - Collecte … - …”
  - Adds a favicon badge when available.
  - The platform label (and icon) link to `ingestion.sourceUrl` if present.
  - Safety rule for stale scraped data:
    - If `funding_end_at` exists, display text from the date only (`terminée le ...` or `en cours jusqu'au ...`).
    - If no `funding_end_at`, fallback to normalized `funding_status`.
    - This avoids contradictory strings like `terminée` with a future date.

## Platform icon resolution

- Resolver service: `src/Service/PlatformIconResolver.php`
- Twig helper: `platform_icon()` in `src/Twig/PlatformIconExtension.php`
- Platform label helper: `platform_label()` in `src/Twig/PlatformIconExtension.php`
- Icon assets live in:
  - `public/media/static/images/icons/popular_websites/{slug}.png`

## How to add a new platform

1) **Add the icon**
   - Put a `png` in `public/media/static/images/icons/popular_websites/`
   - Use a short slug name (ex: `kickstarter.png`).

2) **Extend mappings**
   - Update `PlatformIconResolver::PLATFORM_ICON_MAP` and `HOST_ICON_MAP`.
   - Optionally add a host match in `isUluleHost()`-like logic if needed.

3) **Provide funding fields**
   - If importing from a new API:
     - Populate `funding_end_at`, `funding_status`, `funding_platform`.
   - If normalizing from AI:
     - Ensure the prompt includes these fields (see `prompts/normalize_project_*`).

## Notes

- Linking uses `safe_href` filter to ensure only http/https URLs are used.
- If no platform is provided, the row can still show using status/end date only.
