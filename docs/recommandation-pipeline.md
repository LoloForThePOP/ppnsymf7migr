# Recommandation Pipeline (Phase 1)

This document describes the current homepage recommendation pipeline implemented in code.

## Scope

Phase 1 is a lightweight, server-side ranking pipeline for homepage cards:

- Logged user block: `À explorer pour vous`
- Anonymous block: `À découvrir`

It is currently used only on homepage templates.

## Entry Points

- Controller orchestration: `src/Controller/HomeController.php`
- Ranking service: `src/Service/Recommendation/RecommendationEngine.php`
- Result DTO: `src/Service/Recommendation/RecommendationResult.php`

Rendering:

- Logged users block: `templates/home/_connected_user_upper_display.html.twig`
- Anonymous block: `templates/home/homepage.html.twig`

## High-Level Flow

1. `HomeController::index()` builds `excludeRecommendationIds` from followed projects.
2. `HomeController::index()` calls:
   - `RecommendationEngine::recommendHomepage($viewer, 6, $excludeRecommendationIds)`
3. Service returns:
   - selected `PPBase[]`
   - likes/comments stats map
   - personalization flag
4. Templates render cards if list is non-empty.

## Candidate Generation

Candidate pool comes from latest published projects:

- Logged user:
  - `PPBaseRepository::findLatestPublishedExcludingCreator($viewer, 180)`
  - avoids recommending viewer’s own projects
- Anonymous:
  - `PPBaseRepository::findLatestPublished(180)`

Repository-level filters guarantee:

- `isPublished = true`
- `isDeleted IS NULL OR isDeleted = false`

Config defaults are now externalized in `config/services.yaml` (`app.recommendation.homepage`):

- `candidate_pool_limit: 180`
- `max_per_category: 2`
- `freshness_decay_days: 120`
- scoring weights (`similarity`, `personalized`, `non_personalized`, `engagement_raw`)

## Personalization Seed Profile

For logged users, seed profile is built from:

- own recent projects: `findLatestByCreator($viewer, 12)`
- recently followed projects: `findLatestFollowedPresentations($viewer, 24)`

Extracted signals:

- categories: normalized `category.uniqueName`
- keywords: normalized tokens parsed from `PPBase::keywords`

Token normalization:

- trim, strip tags, decode HTML entities
- collapse whitespace
- lowercase

Personalization is considered active when seed has at least one category or keyword.

## Feature Extraction

For each candidate project:

- `categorySimilarity`:
  - overlap ratio vs seed categories, capped at `1.0`
- `keywordSimilarity`:
  - overlap ratio vs seed keywords, capped at `1.0`
- `contentSimilarity`:
  - `0.75 * categorySimilarity + 0.25 * keywordSimilarity`
- engagement inputs:
  - likes/comments from `PPBaseRepository::getEngagementCountsForIds()`
  - follows from `FollowRepository::countByPresentationIds()`
  - bookmarks from `BookmarkRepository::countByPresentationIds()`
  - views from `PPBase->getExtra()->getViewsCount()`
- `engagementRaw`:
  - `1.0*likes + 1.4*comments + 1.8*follows + 1.1*bookmarks + 0.6*log(1+views)`
- `freshness`:
  - `exp(-ageDays / 120)`

Engagement is then normalized by dividing each `engagementRaw` by the max engagement among candidates.

## Final Scoring

If personalized (seed available):

- `score = 0.60*content + 0.25*engagement + 0.15*freshness`

If non-personalized:

- `score = 0.65*engagement + 0.35*freshness`

Candidates are sorted descending by score.

## Diversity Pass

After ranking, a diversity selector applies a per-category cap:

- up to `max_per_category` (default `2`) occurrences per category
- candidate is accepted if it still has at least one category under cap
- over-cap candidates are deferred
- if result size is still below limit, deferred rows are added back in score order

This prevents over-concentration while still filling the target number of cards.

## Output Contract

`RecommendationResult` contains:

- `items`: selected project entities
- `stats`: per-project likes/comments map for card badges
- `personalized`: bool flag

`HomeController` passes these to Twig as:

- `recommendedPresentations`
- `recommendedPresentationStats`

## Current Product Behavior

- One recommendation block per homepage context:
  - logged: `À explorer pour vous`
  - anonymous: `À découvrir`
- `followedPresentations` are excluded from recommendation block (logged mode).
- If no candidates, block is hidden.

## Instrumentation (CTR Baseline)

Homepage recommendation cards are instrumented with first-party events through `pp_event`:

- `rec_impression`: sent when a recommendation card enters viewport (IntersectionObserver, one-shot per card).
- `rec_click`: sent when user clicks the recommendation card (best-effort `sendBeacon`/`fetch`).

Meta payload currently includes:

- `placement`: recommendation surface id (`home_logged`, `home_anon`, etc.)
- `position`: card rank in the rendered block (1-based)

Server-side validation is strict in `PresentationEventController`:

- only `[a-z0-9_-]{1,40}` placement values
- position must be integer in `[1..48]`

Admin monitoring now includes placement-level feedback tables:

- impressions/clicks/CTR split by placement (`home_logged`, `home_anon`, etc.)

## QA Seeding Command

For local/dev testing when real user traffic is low, use:

```bash
php bin/console app:dev:seed-recommendation-qa
```

What it does:

- creates/updates deterministic QA accounts (`qa_rec_01`, `qa_rec_02`, ...)
- sets one shared password (configurable)
- seeds synthetic `follow` / `like` / `bookmark` interactions on published projects
- biases interactions by category buckets so personalization behavior is observable

Useful options:

- `--users=6`
- `--target=24`
- `--password=qa-rec-password`
- `--reset-interactions` (clear previous QA interactions before reseeding)

## Out of Scope (Phase 1)

Not implemented in this pipeline yet:

- `user_preferences` / `user_interactions` tables
- anonymous cookie/localStorage profile
- CTR/dwell-time feedback loop
- A/B testing
- embedding runtime calls for homepage ranking

## Existing Separate Path (Not Homepage Pipeline)

`src/Controller/ProjectPresentation/RecommendationController.php` exposes
`/project/{stringId}/recommendations`, based on `presentation_neighbors` and embedding model.
This is independent from the homepage Phase 1 ranking service.

## Extension Points

If we move to Phase 2+, keep this modular path:

1. Add new features (explicit interests, click feedback, view-history) in dedicated feature extractors.
2. Move weights/constants to config parameters for safer tuning.
3. Add monitoring logs per request:
   - candidate count
   - personalization on/off
   - fill ratio after diversity
4. Add tests specifically for scoring and diversity edge-cases.
