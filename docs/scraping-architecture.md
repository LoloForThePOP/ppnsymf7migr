# Scraping Architecture

This document summarizes the current scraping organization after the namespace/file cleanup.

## Service Layout

Scraping services are grouped by domain under `src/Service/Scraping/`:

- `src/Service/Scraping/Common/`
  - `ScraperUserResolver.php`: resolves the unique scraper user account.
  - `WorkerHeartbeatService.php`: worker heartbeat status (UI + monitoring).
- `src/Service/Scraping/Core/`
  - `ScraperIngestionService.php`: legacy prompt-driven ingestion (LLM normalization pass).
  - `ScraperPersistenceService.php`: persistence layer for legacy ingestion payloads.
- `src/Service/Scraping/UrlHarvest/`
  - `UrlHarvestRunner.php`: fetch + extract + AI normalization + dedup + persist pipeline.
  - `UrlHarvestListService.php`: `urls.csv`, prompt/config, queue state.
  - `UrlHarvestResultStore.php`: per-URL debug/result JSON snapshots.
- `src/Service/Scraping/JeVeuxAider/`
  - `JeVeuxAiderNuxtDataExtractor.php`: deterministic extraction from Nuxt payload.
  - `JeVeuxAiderFallbackImageResolver.php`: standard fallback image folder selection.
- `src/Service/Scraping/Ulule/`
  - `UluleApiClient.php`: Ulule API access wrapper.
  - `UluleCatalogRefresher.php`: refreshes Ulule catalog entries.
  - `UluleImportService.php`: imports one Ulule project into normalized/persisted data.
  - `UluleQueueStateService.php`: queue/filter state for Ulule import loop.

## Controllers / Handlers

- URL list scraping:
  - `src/Controller/Admin/UrlHarvestController.php`
  - `src/MessageHandler/UrlHarvestTickHandler.php`
- Ulule scraping:
  - `src/Controller/Admin/UluleCatalogController.php`
  - `src/Controller/Admin/UluleQueueController.php`
  - `src/Controller/Admin/UluleImportController.php`
  - `src/MessageHandler/UluleImportTickHandler.php`

## Runtime State and Files

- URL list sources: `var/collect/url_lists/<source>/`
  - `urls.csv`
  - `prompt.txt`
  - `config.json`
  - `results/*.json` (per-URL debug snapshot)
- Ulule queue state:
  - `var/collect/ulule_queue.json`
  - `var/collect/ulule_queue.lock`
- Worker heartbeat:
  - `var/collect/worker_heartbeat.json`

## Dedup and Re-import Behavior

- URL harvest dedup is based on `PPBase.ingestion.sourceUrl` (and hash) before persistence.
- Ulule dedup checks source URL against existing ingested projects.
- Queue processing generally retries `pending` and `error` items only.

## Why This Structure

- Clear boundaries per source/domain (Ulule, URL lists, JeVeuxAider specifics).
- Lower cognitive load for maintenance and debugging.
- Easier future extensions (new source = new folder + focused services).
