# Image Resize Pipeline

This document describes how images are resized server-side when they are uploaded or imported.

## Entry point

- Subscriber: `src/EventSubscriber/ImageResizeSubscriber.php`
- Event: `Vich\UploaderBundle\Event\Events::PRE_UPLOAD`
- Engine: Imagine (Imagick driver by default)

The resize runs **before persistence** for files handled by Vich mappings.

## Active resizing rules

Current mapping rules:

- `presentation_slide_file` → max `1920x1080`, quality `80`
- `project_logo_image` → max `1400x1400`, quality `82`
- `project_custom_thumbnail_image` → max `1400x1400`, quality `82`
- `profile_image` → max `900x900`, quality `82`
- `news_image` → max `1920x1080`, quality `80`

## Important behavior

- Resize is skipped if the image is already within max dimensions.
- For `project_logo_image`, files `<= 350 KB` are skipped (`LOGO_RESIZE_MIN_BYTES`).
- On resize failure, the original logo content is restored when possible, and a warning is logged.

## Scope and limitations

- This pipeline applies only to files passing through Vich upload flow (`PRE_UPLOAD`).
- Files written directly to disk (outside Vich) bypass this resize pipeline.
- Liip filters are derivative/view-time processing; they do not replace this ingestion-time resize.

