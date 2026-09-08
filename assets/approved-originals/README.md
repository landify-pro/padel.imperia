# Approved image handoff

This directory contains source images for the final Padel site. These are reference assets, not live image paths. Upload the five approved JPEGs here with exactly these names:

- `hero-desktop.jpg` — approved desktop hero without snowy mountains.
- `hero-mobile.jpg` — approved portrait hero without snowy mountains.
- `video-cover-1.jpg` — approved cover for the beginner lesson.
- `video-cover-2.jpg` — approved cover for the serve lesson.
- `video-cover-3.jpg` — approved cover for the glass lesson.
- `padel-final-approved-16x9.png` — approved full-quality drone image for the final booking banner.

The production-ready 1600×900 JPEG derived from the approved drone image is stored at
`assets/images/padel-final-approved-16x9.jpg`.

Codex: read these files from the current repository checkout. Do not depend on another Git branch or a chat attachment. Preserve the original image bytes during transfer into the production asset directory. Confirm that each file decodes correctly, check dimensions and actual image quality, and verify the rendered site on desktop and mobile. Do not generate replacements. Keep the existing site, PHP admin, content.json and telephone-only booking. Do not merge unfinished changes into main. Report missing assets or test failures accurately.
