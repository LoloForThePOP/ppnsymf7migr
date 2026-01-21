# Ulule Queue (Suivi Ulule)

This describes the automated Ulule import queue used in **Suivi Ulule**.

## What it does
- Refreshes the Ulule catalog from the API search (using the current filters).
- Processes eligible projects **one by one** via Messenger.
- Updates the UI via polling (status, eligible badge, created link).

## Queue State
Stored in `var/collect/ulule_queue.json`:
```json
{
  "queue": {
    "paused": false,
    "running": false,
    "remaining": null,
    "run_id": "abcd1234",
    "current_id": null
  },
  "filters": {
    "lang": "fr",
    "country": "FR",
    "status": "currently",
    "sort": "new",
    "page_start": 1,
    "page_count": 10,
    "min_description_length": 500,
    "exclude_funded": false,
    "include_video": true,
    "include_secondary_images": true,
    "extra_query": "",
    "prompt_extra": "",
    "eligible_only": true,
    "status_filter": "pending"
  }
}
```

- `run_id` guards against stale queued messages.
- `current_id` is used for the “en cours” indicator in the UI.
- `remaining` lets you limit imports; `null` = no limit.

## Worker
Messenger handles one project per tick:
```
php bin/console messenger:consume async -vv
```
Run this as a persistent service in production.

## Endpoints
- `admin_ulule_queue_start` → refreshes catalog and starts the queue.
- `admin_ulule_queue_pause`
- `admin_ulule_queue_resume`
- `admin_ulule_queue_status` → JSON for polling (queue + summary + row updates).

## Import logic
Imports are performed by `UluleImportService`, which applies the same rules as manual “Importer”.

Key files:
- `src/Service/UluleImportService.php`
- `src/Service/UluleCatalogRefresher.php`
- `src/Service/UluleQueueStateService.php`
- `src/Message/UluleImportTickMessage.php`
- `src/MessageHandler/UluleImportTickHandler.php`
- `src/Controller/Admin/UluleQueueController.php`
- `templates/admin/ulule_catalog.html.twig`
