# JeVeuxAider Contact Display Archive

This note archives the move from embedded `business_cards` to source-page-only contact exposure for JeVeuxAider imports.

## Why

- Contact details imported into `business_cards` were visible directly in project pages.
- Product decision: for JeVeuxAider, contact details must be exposed via the source website link only.
- UI wording: use `Page JeVeuxAider.gouv (infos de contact)` in the websites component.

## What Changed

1. Ingestion rule (durable):
- File: `src/Service/NormalizedProjectPersister.php`
- JeVeuxAider imports now skip `attachBusinessCards(...)` entirely.
- JeVeuxAider source website label now defaults to:
  - `Page JeVeuxAider.gouv (infos de contact)`

2. Historical cleanup (one-shot):
- Command: `php bin/console app:cleanup:remove-jva-business-cards`
- Purpose:
  - remove existing JeVeuxAider `business_cards`
  - optionally annotate legacy website title to include ` (infos de contact)`

## Prompt Archive (Removed Instructions)

The following JeVeuxAider prompt directives were removed from local runtime prompt file
`var/collect/url_lists/je_veux_aider/prompt.txt` to align with the new privacy rule:

```text
- Dans "websites" le nom du source_url est "Page JeVeuxAider.gouv", inclus aussi les réseaux sociaux du projet s'il y en a ainsi que le site web officiel, par contre rien de plus.
- business_cards : extraire les infos de contact sous <h3 class="text-2xl font-bold">Informations</h3>
  "business_cards": [
    {
      "title": "string|null", // Souvent c'est "Siège de l'association"
      "tel1": "string|null", // à trouver dans "numéro de téléphone de l'association"
      "email1": "string|null", // à trouver dans "email de l'association" (ou mailto). Ne pas prendre les emails génériques de la plateforme.
      "postalMail": "string|null", // à trouver dans siège de l'association balise <address>. Si on a une adresse avec nom de rue ou boulevard et autres alors dans ce cas sépare les grands éléments de l'adresse avec un saut de ligne.
    }
  ]
- Pour postalMail : ne pas inclure les arrondissements (ex: "10e Arrondissement") — les supprimer même s'ils sont présents dans la page.
```

Current replacement in that same prompt:

```text
- Dans "websites", le nom du source_url est "Page JeVeuxAider.gouv (infos de contact)". Inclure aussi les réseaux sociaux du projet s'il y en a ainsi que le site web officiel, rien de plus.
- Ne pas remplir business_cards dans le JSON.
```

## Operational Runbook

1. Apply code deploy with the ingestion rule above.
2. Run cleanup command on existing data:
   - preview: `php bin/console app:cleanup:remove-jva-business-cards --dry-run`
   - apply: `php bin/console app:cleanup:remove-jva-business-cards`
3. Spot-check JeVeuxAider projects:
   - no `business_cards` section rendered
   - website entry shows `Page JeVeuxAider.gouv (infos de contact)`

## Rollback (if ever needed)

- Re-enable JeVeuxAider handling in `attachBusinessCards(...)` inside `src/Service/NormalizedProjectPersister.php`.
- If desired, change website title back to `Page JeVeuxAider.gouv`.
- Re-import data for affected projects (cleanup is destructive for removed cards).
