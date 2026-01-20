# URL List Harvest Queue

This documents the "Collecter depuis une liste d’URLs" system (queue + polling UI).

## Overview
- **Purpose**: process a source list of URLs sequentially, normalize via LLM, optionally persist, and update UI live.
- **Queue model**: one URL per tick (Messenger message), auto-dispatch next until done or paused.
- **UI**: polling updates row status, payload quality, and JSON/debug links.

## Folder Layout
Each source lives under `var/collect/url_lists/<source>/`:
- `urls.csv`: list + status tracking.
- `prompt.txt`: per-source prompt addendum (appended to the base HTML normalize prompt).
- `config.json`: queue state + payload policy overrides.
- `results/`: JSON debug outputs per URL (see “Result Store”).
- `.queue.lock`: lock file preventing concurrent ticks.

## CSV Schema
`urls.csv` columns (header enforced on save):
- `url`
- `status` (`pending`, `queued`, `processing`, `done`, `normalized`, `error`, `skipped`)
- `last_run_at`
- `error`
- `notes`
- `created_string_id`
- `created_url`
- `payload_status` (`ok`, `weak`, `too_thin`)
- `payload_text_chars`
- `payload_links`
- `payload_images`

Status meanings:
- `pending`: never processed.
- `processing`: currently running.
- `done`: persisted and created a project.
- `normalized`: LLM ok but not persisted.
- `skipped`: duplicate or payload too thin.
- `error`: runtime failure.

## Prompt Handling
Pipeline prompt = `prompts/normalize_project_from_html.txt` + optional `prompt.txt` (source-specific).

## Queue State (config.json)
Stored in `config.json` under `queue`:
```json
{
  "queue": {
    "paused": false,
    "running": false,
    "persist": true,
    "remaining": null
  }
}
```
- `persist`: “Persister immédiatement” default for this source.
- `remaining`: optional batch limit.

Queue lock: `.queue.lock` (flock) ensures single active tick per source.

## Payload Gate
Each run evaluates payload size before persisting:
- text characters
- link count
- image count

Default policy:
```json
{
  "min_text_chars": 600,
  "warn_text_chars": 350,
  "min_assets": 2
}
```
Per-source overrides live in `config.json` under `payload`. Built-in defaults for `je_veux_aider` and `fondation_du_patrimoine` are defined in `UrlHarvestListService`.

If the payload is **too thin**, the run is marked `skipped` with an error note and no persistence.

## Result Store (JSON debug)
Each processed URL saves a debug JSON:
```
var/collect/url_lists/<source>/results/<sha1(url)>.json
```
The UI uses this for “Voir JSON”.

## Runtime Flow
1. UI triggers `run_source` → sets queue state (persist/batch) → dispatches `UrlHarvestTickMessage`.
2. `UrlHarvestTickHandler`:
   - locks source
   - picks next `pending`/`error` URL
   - marks `processing`
   - builds prompt
   - runs `UrlHarvestRunner`
   - updates CSV fields + stores result JSON
   - dispatches next tick if queue still active.

## Endpoints
- `admin_project_harvest_urls` (main UI)
- `admin_project_harvest_urls_status` (polling JSON for table refresh)
- `admin_project_harvest_urls_result` (debug JSON by key)

## Worker
Queue runs on Symfony Messenger:
```
php bin/console messenger:consume async -vv
```
Run as a persistent service (Supervisor/systemd) in production.

## Key Files
- `src/Controller/Admin/UrlHarvestController.php`
- `src/Service/UrlHarvestListService.php`
- `src/Service/UrlHarvestRunner.php`
- `src/Message/UrlHarvestTickMessage.php`
- `src/MessageHandler/UrlHarvestTickHandler.php`
- `src/Service/UrlHarvestResultStore.php`
- `templates/admin/project_harvest_urls.html.twig`
