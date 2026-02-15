# Nightly User Preferences Recompute

This document explains how to run `user_preferences` recompute every night in production.

## What Runs

- Command: `app:recompute-user-preferences --all --batch-size=500`
- Runner script: `bin/nightly_recompute_user_preferences.sh`
- Safety:
  - lock file prevents concurrent runs: `var/lock/recompute_user_preferences.lock`
  - exits cleanly when a previous run is still active

## Cron (Recommended)

Edit crontab:

```bash
crontab -e
```

Add a nightly run at `02:20`:

```cron
20 2 * * * cd /path/to/project && /path/to/project/bin/nightly_recompute_user_preferences.sh >> /path/to/project/var/log/recompute_user_preferences.log 2>&1
```

Optional custom batch size:

```cron
20 2 * * * cd /path/to/project && BATCH_SIZE=800 /path/to/project/bin/nightly_recompute_user_preferences.sh >> /path/to/project/var/log/recompute_user_preferences.log 2>&1
```

## systemd Timer (Optional)

Use this when you prefer managed service/timer units over cron.

Service unit:

```ini
[Unit]
Description=Nightly recompute user preferences

[Service]
Type=oneshot
WorkingDirectory=/path/to/project
ExecStart=/path/to/project/bin/nightly_recompute_user_preferences.sh
```

Timer unit:

```ini
[Unit]
Description=Nightly recompute user preferences timer

[Timer]
OnCalendar=*-*-* 02:20:00
Persistent=true

[Install]
WantedBy=timers.target
```

Enable:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now recompute-user-preferences.timer
```

## Manual Test

Run once manually before enabling schedule:

```bash
bin/nightly_recompute_user_preferences.sh
```

## Notes

- Keep `UserPreferenceUpdater` as batch source-of-truth rebuild (categories + keywords).
- Runtime interactions keep category updates incremental via `UserCategoryPreferenceSignalUpdater`.
