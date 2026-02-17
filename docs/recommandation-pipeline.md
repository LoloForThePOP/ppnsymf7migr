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
- `ViewerSignalProvider`:
  - Central read-path resolver for viewer signals used by multiple rails.
  - Logged-in signals: `user_preferences` (cats/keywords) + recent views/follows/bookmarks (neighbor seeds).
  - Anonymous signals: `anon_pref_categories`, `anon_pref_keywords`, `anon_pref_recent_views`.
  - Note: this is read-only refactoring; existing signal writers/storage remain unchanged.
- `CategoryAffinityFeedBlockProvider`:
  - Logged-in source: `user_preferences.fav_categories` (categories only).
  - Logged-in fallback: categories from user's recent created projects.
  - Anonymous source: `anon_pref_categories` cookie (categories only).
- `NeighborAffinityFeedBlockProvider`:
  - Logged-in source: recent viewed projects (`presentation_event`), then recent follows/bookmarks as seed fallback.
  - Anonymous source: `anon_pref_recent_views` cookie (recent viewed project ids).
  - Retrieves candidates from `presentation_neighbors` and aggregates by seed recency + neighbor rank.
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
  - Guarded by feature flag `app.home_feed.trending.enabled` (currently disabled when set to `false`).
- `LatestPublishedFeedBlockProvider`:
  - General source: latest published projects fallback.
- `home_anon_preferences.js`:
  - Tracks anon 3P views (primary) and card clicks (secondary).
  - Stores category and keyword signals in localStorage.
  - Stores recent viewed project ids for anon neighbor seeding.
  - Syncs top categories into cookie `anon_pref_categories`.
  - Syncs top keywords into cookie `anon_pref_keywords`.
- `home_feed_metrics.js`:
  - Tracks homepage card impressions/clicks per block.
  - Sends telemetry to `pp_event` (`home_feed_impression`, `home_feed_click`) with block metadata.
  - Used for CTR by rail in admin monitoring.
- `MonitoringDashboardController`:
  - Reads homepage rail impressions/clicks grouped by block from `presentation_event.meta`.
  - Displays CTR table per block in `/admin/monitoring`.
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
- `PresentationRelatedFeedBuilder`:
  - Builds bottom rails for 3P pages.
  - Prioritizes `presentation_neighbors` (`Projets similaires`) for the active embedding model.
  - Falls back to category/keyword affinity rails when neighbor data is missing/insufficient.

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

1. `NeighborAffinityFeedBlockProvider` (personalized by nearest neighbors from recent seeds)
2. `CategoryAffinityFeedBlockProvider` (personalized by categories)
3. `NearbyLocationFeedBlockProvider` (location-based)
4. `TrendingFeedBlockProvider` (general)
5. `LatestPublishedFeedBlockProvider` (general fallback)
6. `FollowedProjectsFeedBlockProvider` (logged only, fallback rail)
7. `KeywordAffinityFeedBlockProvider` (personalized by keywords, fallback rail)

## 3P Bottom Rails

Entry point:

- `src/Service/HomeFeed/PresentationRelatedFeedBuilder.php`

Order (max 2 blocks):

1. `Projets similaires` (`related-neighbors`) from `presentation_neighbors` using current embedding model.
2. Category/keyword fallback (`Dans les mêmes catégories`, `Domaines proches`) with global dedupe.

Notes:

- Neighbor retrieval filters out unpublished/deleted projects at read time.
- If no row exists for the active model, retrieval falls back to any available model for resilience.

## Recommandation Blocks (Homepage)

This section describes what users see on homepage depending on audience state.

### Logged-in users

Expected blocks (in order, when data exists):

1. `Parce que vous avez consulté` (`neighbor-affinity`)
2. `Basé sur vos catégories` (`category-affinity`)
3. `Autour de vous` (`around-you`)
4. `Tendance sur Propon` (`trending`)
5. `Derniers projets présentés` (`latest`)
6. `Projets suivis` (`followed-projects`) (fallback rail)
7. `Domaines d’intérêt` (`domain-interest`) (fallback rail)

Notes:

- `category-affinity` uses `user_preferences.fav_categories` first.
- If `user_preferences` is empty, it falls back to categories from the user's recent created projects.
- Blocks are deduplicated globally by project id in `HomeFeedAssembler`.

### Anonymous opt-in (signals accepted)

Expected blocks (in order, when data exists):

1. `Parce que vous avez consulté` (`anon-neighbor-affinity`)
2. `Selon vos centres d’intérêt récents` (`anon-category-affinity`)
3. `Autour de vous` (`around-you`)
4. `Tendance sur Propon` (`trending`)
5. `Derniers projets présentés` (`latest`)
6. `Domaines d’intérêt` (`anon-domain-interest`) (fallback rail)

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
  - Candidate strategy: category windows (`offset` slices) + in-memory diversification
  - Provider: `CategoryAffinityFeedBlockProvider`
- `anon-category-affinity`
  - Audience: anonymous with hints
  - Feed source: `anon_pref_categories` cookie (fed from localStorage signals)
  - Candidate strategy: category windows (`offset` slices) + short TTL candidate-id cache (to reduce repeated SQL) + in-memory diversification
  - Provider: `CategoryAffinityFeedBlockProvider`
- `followed-projects`
  - Audience: logged-in
  - Feed source: `follow` table (`findLatestFollowedPresentations`)
  - Candidate strategy: recent follows + top-window shuffle to avoid fixed rails
  - Provider: `FollowedProjectsFeedBlockProvider`
- `domain-interest`
  - Audience: logged-in
  - Feed source: `user_preferences.fav_keywords` (weighted keyword overlap against a keyword-matching candidate pool merged with recent published)
  - Provider: `KeywordAffinityFeedBlockProvider`
- `neighbor-affinity`
  - Audience: logged-in
  - Feed source: recent views (`presentation_event`), then follow/bookmark fallback seeds
  - Candidate strategy: aggregate nearest neighbors from `presentation_neighbors` across seed projects, then shuffle top window
  - Provider: `NeighborAffinityFeedBlockProvider`
- `anon-domain-interest`
  - Audience: anonymous with hints
  - Feed source: `anon_pref_keywords` cookie (weighted overlap against a keyword-matching candidate pool merged with recent published)
  - Provider: `KeywordAffinityFeedBlockProvider`
- `anon-neighbor-affinity`
  - Audience: anonymous with recent views
  - Feed source: `anon_pref_recent_views` cookie
  - Candidate strategy: aggregate nearest neighbors from `presentation_neighbors` across seed projects, then shuffle top window
  - Provider: `NeighborAffinityFeedBlockProvider`
- `around-you`
  - Audience: logged-in + anonymous
  - Feed source: `search_pref_location` cookie (lat/lng/radius), then `places` geoloc filters
  - Candidate strategy: location-filtered pool + top-window shuffle
  - Provider: `NearbyLocationFeedBlockProvider`
- `trending`
  - Audience: logged-in + anonymous
  - Feed source: published projects + engagement/freshness scoring (likes/comments/views/time decay)
  - Candidate strategy: recent candidate pool + lower freshness weight + top-window shuffle
  - Provider: `TrendingFeedBlockProvider`
  - Runtime flag: rendered only when `app.home_feed.trending.enabled = true`
- `latest`
  - Audience: logged-in + anonymous
  - Feed source: latest published projects (excluding own projects for logged-in users)
  - Candidate strategy: larger recent pool + top-window shuffle
  - Provider: `LatestPublishedFeedBlockProvider`

### Recency/Staticity Guards

- Category rails (`category-affinity`, `anon-category-affinity`) now blend multiple recency windows (`offset` slices), then shuffle bounded pools.
- Anonymous category rail uses a short cache (`app.home_feed.category_affinity.anon_cache_ttl_seconds`) to avoid recomputing the same candidate pool on every request.
- Keyword rails (`domain-interest`, `anon-domain-interest`) use keyword-matching candidate pools merged with recent projects, then diversify.
- `trending` scores a bounded recent candidate pool with reduced freshness dominance and shuffled top window.
- `around-you`, `followed-projects`, and `latest` use bounded top-window shuffle to avoid fixed repeated first cards.
- Dedupe across rails remains in `HomeFeedAssembler`: a project shown in one rail is excluded from later rails on the same render.

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
- Browser stores recent viewed project ids in localStorage: `public/js/home_anon_preferences.js`.
- Primary signal is anonymous 3P page view (once per presentation per browser session).
- Secondary signal is anonymous card click (homepage + search overlay, low weight, once per card target per session).
- Top category slugs are mirrored into cookie `anon_pref_categories`.
- Top keyword hints are mirrored into cookie `anon_pref_keywords`.
- Recent viewed project ids are mirrored into cookie `anon_pref_recent_views`.
- Backend reads the cookie in `HomeController` and injects hints into `HomeFeedContext`.
- Category-affinity and keyword-affinity blocks use these hints when available.
- Neighbor-affinity block uses recent view id hints when available.
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

Current computation modes:

- `centroid` (default): builds each user vector as a weighted centroid of interacted project embeddings (`like`/`follow`/`bookmark` + recent `view` signals).
- `text` (compatibility mode): builds user vectors from cached `user_preferences` text (`categories + keywords`) through embedding API calls.

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
- `bin/console app:compute-user-embeddings [--mode=centroid|text] [--user-id=ID] [--limit=200] [--cooldown-hours=6] [--force]`
- `bin/console app:compute-presentation-embeddings [--limit=50] [--k=30] [--min-score=12] [--missing-only] [--force]`
  - Computes/updates `presentation_embeddings` (API calls) and refreshes neighbors for updated presentations.
- `bin/console app:compute-presentation-neighbors [--k=30] [--presentation-id=ID] [--limit=0]`
  - Recomputes `presentation_neighbors` from stored vectors only (no embedding API call), useful for 3P recommendation refresh.

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
