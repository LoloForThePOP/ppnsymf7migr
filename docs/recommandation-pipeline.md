# Recommandation Pipeline

This document describes the current homepage recommendation pipeline and its extension points.

## Simple Map (Who Does What)

Use this section as the quick reference before reading details.

- `HomeController`:
  - Creates `HomeFeedContext` for current visitor.
  - Reads anon cookies `anon_pref_categories` and `anon_pref_keywords` when user is not logged in.
  - Delegates homepage location hint parsing/summary to `HomepageLocationContextResolver`.
  - Calls feed assembly and passes blocks to homepage Twig.
- `HomeFeedContext`:
  - Carries visitor type (logged-in or anon), cards/block, max blocks, anon category hints, anon keyword hints.
- `HomeFeedAssembler`:
  - Calls each block provider in priority order.
  - Keeps only valid blocks with items.
  - Deduplicates projects across blocks.
  - Applies creator cap only if enabled in config.
- `CategoryAffinityFeedBlockProvider`:
  - Logged-in source: `user_preferences.fav_categories` (categories only).
  - Logged-in fallback: categories from user's recent created projects.
  - Anonymous source: `anon_pref_categories` cookie (categories only).
- `KeywordAffinityFeedBlockProvider`:
  - Logged-in source: `user_preferences.fav_keywords` (normalized keyword scores).
  - Anonymous source: `anon_pref_keywords` cookie (recent keyword hints).
  - Renders only when enough keyword overlap candidates exist.
- `HomepageLocationContextResolver`:
  - Reads `search_pref_location` + `search_pref_location_label` cookies.
  - Validates location bounds and radius.
  - Builds human-readable inline/info hints for `Autour de vous` header.
- `NearbyLocationFeedBlockProvider`:
  - Logged-in + anonymous source: validated location hint from controller context (cookie-backed).
  - Fetches projects with places in a geographic bounding box.
- `location_picker.js` + `search/_location_picker.html.twig`:
  - One shared location picker UI/state module.
  - Reused in search overlay and homepage `Autour de vous` modal.
  - Persists `search_pref_location` (cookie + localStorage cache).
- `FollowedProjectsFeedBlockProvider`:
  - Logged-in source: follow table (`follow`).
- `TrendingFeedBlockProvider`:
  - General source: recent published projects ranked by engagement + freshness.
- `LatestPublishedFeedBlockProvider`:
  - General source: latest published projects fallback.
- `home_anon_preferences.js`:
  - Tracks anon 3P views (primary) and card clicks (secondary).
  - Stores category and keyword signals in localStorage.
  - Syncs top categories into cookie `anon_pref_categories`.
  - Syncs top keywords into cookie `anon_pref_keywords`.
- `UserCategoryPreferenceSignalUpdater`:
  - Applies incremental category/keyword score deltas for logged users (like/follow/bookmark/view).
  - Applies lightweight time decay before each update so old signals fade naturally.
- `UserPreferenceUpdater`:
  - Rebuilds `user_preferences` cache from source-of-truth interactions.
  - Stores both category and keyword scores.
  - Used as batch/safety recompute path (including keyword scoring for future rails).
- `KeywordNormalizer`:
  - Normalizes keywords for matching (lowercase, accents stripped, simple singularization, stopwords, aliases).
  - Used in recommendation keyword scoring to improve match quality with low compute cost.

## Goals

- Keep a modular homepage feed with multiple blocks (YouTube-like rails).
- Serve both anonymous and logged users.
- Keep recommendation logic simple and evolutive.
- Keep raw interactions as source of truth, with cached profiles for speed.

## Homepage Block Framework

Main entry points:

- `src/Controller/HomeController.php`
- `src/Service/HomeFeed/HomeFeedAssembler.php`
- `src/Service/HomeFeed/HomeFeedBlockProviderInterface.php`
- `src/Service/HomeFeed/Block/*`

Flow:

1. Controller builds `HomeFeedContext`.
2. `HomeFeedAssembler` asks each provider for one block.
3. Assembler deduplicates projects across blocks.
4. Assembler loads engagement stats for card rendering.
5. Twig renders block list in homepage templates.

Current providers (priority order):

1. `CategoryAffinityFeedBlockProvider` (personalized by categories)
2. `KeywordAffinityFeedBlockProvider` (personalized by keywords)
3. `NearbyLocationFeedBlockProvider` (location-based)
4. `FollowedProjectsFeedBlockProvider` (logged only)
5. `TrendingFeedBlockProvider` (general)
6. `LatestPublishedFeedBlockProvider` (general fallback)

## Recommandation Blocks (Homepage)

This section describes what users see on homepage depending on audience state.

### Logged-in users

Expected blocks (in order, when data exists):

1. `Basé sur vos catégories` (`category-affinity`)
2. `Domaines d’intérêt` (`domain-interest`)
3. `Autour de vous` (`around-you`)
4. `Projets suivis` (`followed-projects`)
5. `Tendance sur Propon` (`trending`)
6. `Derniers projets présentés` (`latest`)

Notes:

- `category-affinity` uses `user_preferences.fav_categories` first.
- If `user_preferences` is empty, it falls back to categories from the user's recent created projects.
- Blocks are deduplicated globally by project id in `HomeFeedAssembler`.

### Anonymous opt-in (signals accepted)

Expected blocks (in order, when data exists):

1. `Selon vos centres d’intérêt récents` (`anon-category-affinity`)
2. `Domaines d’intérêt` (`anon-domain-interest`)
3. `Autour de vous` (`around-you`)
4. `Tendance sur Propon` (`trending`)
5. `Derniers projets présentés` (`latest`)

Definition of opt-in here:

- Anonymous category hints are present (localStorage + `anon_pref_categories` cookie available).
- Hints are generated from 3P views (primary) and card clicks (secondary) via `home_anon_preferences.js`.

### Anonymous opt-out (no personalization signals)

Expected blocks:

1. `Autour de vous` (`around-you`) if `search_pref_location` exists
2. `Tendance sur Propon` (`trending`)
3. `Derniers projets présentés` (`latest`)

Definition of opt-out here:

- No anonymous category hints are available (`anon_pref_categories` absent/empty).
- A location block can still appear if a search location cookie exists (`search_pref_location`).
- Otherwise homepage keeps only general, non-personalized blocks.

### Block feed summary

- `category-affinity`
  - Audience: logged-in
  - Feed source: `user_preferences.fav_categories`, fallback to creator recent categories
  - Provider: `CategoryAffinityFeedBlockProvider`
- `anon-category-affinity`
  - Audience: anonymous with hints
  - Feed source: `anon_pref_categories` cookie (fed from localStorage signals)
  - Provider: `CategoryAffinityFeedBlockProvider`
- `followed-projects`
  - Audience: logged-in
  - Feed source: `follow` table (`findLatestFollowedPresentations`)
  - Provider: `FollowedProjectsFeedBlockProvider`
- `domain-interest`
  - Audience: logged-in
  - Feed source: `user_preferences.fav_keywords` (weighted keyword overlap against latest pool)
  - Provider: `KeywordAffinityFeedBlockProvider`
- `anon-domain-interest`
  - Audience: anonymous with hints
  - Feed source: `anon_pref_keywords` cookie (weighted overlap against latest pool)
  - Provider: `KeywordAffinityFeedBlockProvider`
- `around-you`
  - Audience: logged-in + anonymous
  - Feed source: `search_pref_location` cookie (lat/lng/radius), then `places` geoloc filters
  - Provider: `NearbyLocationFeedBlockProvider`
- `trending`
  - Audience: logged-in + anonymous
  - Feed source: published projects + engagement/freshness scoring (likes/comments/views/time decay)
  - Provider: `TrendingFeedBlockProvider`
- `latest`
  - Audience: logged-in + anonymous
  - Feed source: latest published projects (excluding own projects for logged-in users)
  - Provider: `LatestPublishedFeedBlockProvider`

## Location UX Flow

- First-time location choice is guided from homepage prompt to search overlay (`data-search-trigger`).
- Homepage rail header action (`Modifier`) opens the dedicated modal for updates (`data-home-location-trigger`).
- Search overlay and homepage modal use the same picker component (`search/_location_picker.html.twig` + `public/js/location_picker.js`).
- When user applies or resets location, picker updates `search_pref_location`.
- Picker reverse-geocodes coordinates (Google Geocoder) to persist a readable place label when possible.
- Homepage server reads that location signal via `HomepageLocationContextResolver`.
- Homepage rail adds a lightweight context hint (`Basé sur votre localisation récente ... · Rayon ... km`) to avoid ambiguity.
- Homepage modal (`home_location_modal.js`) reloads page after apply/reset so rail updates immediately.

## Anonymous Personalization

Anonymous hints are intentionally lightweight:

- Browser stores category signals in localStorage: `public/js/home_anon_preferences.js` (loaded for anon users from `templates/base.html.twig`).
- Browser stores keyword signals in localStorage: `public/js/home_anon_preferences.js`.
- Primary signal is anonymous 3P page view (once per presentation per browser session).
- Secondary signal is anonymous card click (homepage + search overlay, low weight, once per card target per session).
- Top category slugs are mirrored into cookie `anon_pref_categories`.
- Top keyword hints are mirrored into cookie `anon_pref_keywords`.
- Backend reads the cookie in `HomeController` and injects hints into `HomeFeedContext`.
- Category-affinity and keyword-affinity blocks use these hints when available.
- Category-affinity rails (anonymous and logged-in) now use a diversified recent pool (shuffled within bounded windows) to avoid static repeated cards.

If no anon hints exist, homepage still renders general blocks (`trending`, `latest`).

## Logged Personalization: Source of Truth vs Cache

Source of truth stays in interaction tables:

- `follow`
- `project_like`
- `bookmark`
- `presentation_event` (views/shares)

Cache table:

- `user_preferences` (one row per user)
  - `fav_categories` (JSON map slug => score)
  - `fav_keywords` (JSON map keyword => score)
  - `updated_at`

This cache can always be rebuilt from source-of-truth events/interactions.

## User Embeddings

Optional embedding cache is now available:

- `user_embeddings` (composite key: `user_id`, `model`)
  - `dims`, `normalized`, `vector`, `content_hash`, `created_at`, `updated_at`

The schema mirrors `presentation_embeddings` format to keep both pipelines aligned.

## Recompute Services and Commands

Recompute service:

- `src/Service/Recommendation/UserPreferenceUpdater.php`
  - Aggregates likes/follows/bookmarks/views with weights.
  - Writes normalized top category/keyword scores to `user_preferences`.
  - Uses `KeywordNormalizer` for robust keyword canonicalization.
- `src/Service/Recommendation/UserCategoryPreferenceSignalUpdater.php`
  - Updates `fav_categories` and `fav_keywords` incrementally on each logged interaction signal.
  - Applies a simple half-life decay before each write.

Commands:

- `bin/console app:recompute-user-preferences [--user-id=ID] [--limit=200] [--all] [--batch-size=500]`
- `bin/console app:compute-user-embeddings [--user-id=ID] [--limit=200] [--cooldown-hours=6] [--force]`

Interactive actions:

- Like/Follow/Bookmark controllers apply incremental category updates for the acting user.
- Logged-in 3P first view per session applies an incremental category update.
- Full keyword/category rebuild remains available via recompute command (batch safety pass).
- Production schedule helper: `bin/nightly_recompute_user_preferences.sh` (see `docs/recompute-user-preferences-schedule.md`).

## Creator Cap (Disabled by Default)

Creator diversity cap is available but disabled by default:

- `app.home_feed.creator_cap.enabled: false`
- `app.home_feed.creator_cap.per_block: 2`

When enabled, it limits how many cards from the same creator can appear in a block.
With one-creator datasets, keep it disabled.

## Config Knobs

Defined in `config/services.yaml`:

- `app.home_feed.cards_per_block` (clamped to 8..12 at runtime)
- `app.home_feed.max_blocks.logged`
- `app.home_feed.max_blocks.anon`
- `app.home_feed.creator_cap.enabled`
- `app.home_feed.creator_cap.per_block`
