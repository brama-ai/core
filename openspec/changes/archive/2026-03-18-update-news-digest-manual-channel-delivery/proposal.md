# Change: Update News Digest Manual Channel Delivery

## Why

The current news digest flow stores generated digests but does not guarantee one-click operator delivery into the community channel.

Operators need a deterministic manual action: click a button in admin, generate digest immediately, and publish it through the platform delivery path (`Core A2A -> OpenClaw -> Telegram`) without ad-hoc scripts.

## What Changes

- **Manual trigger completion path**: add a dedicated manual digest trigger in `news-maker-agent` admin that starts digest generation in the background.
- **Channel delivery on success**: after successful digest creation, send the digest message through Core A2A (`POST /api/v1/a2a/send-message`) using `tool=openclaw.send_message`.
- **Single-flight protection**: prevent duplicate concurrent manual digest runs from publishing duplicate digests.
- **Failure isolation**: digest persistence and item status transitions remain committed even if outbound channel delivery fails; delivery failure is logged with trace context.
- **Operational visibility**: expose trigger and delivery outcomes in logs/run history for operators.

## Impact

- Affected specs: `news-digest`, `news-digest-admin`
- Affected code:
  - `apps/news-maker-agent/app/routers/admin/settings.py`
  - `apps/news-maker-agent/templates/admin/settings.html`
  - `apps/news-maker-agent/app/services/scheduler.py`
  - `apps/news-maker-agent/app/services/digest.py`
  - `apps/news-maker-agent/app/config.py`
- Runtime/config impact:
  - `news-maker-agent` requires credentials/config to call Core A2A invoke endpoint securely
- No breaking API change for existing external endpoints
