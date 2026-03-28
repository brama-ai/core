# Cloudflare Turnstile Setup Guide

Cloudflare Turnstile is a privacy-friendly CAPTCHA alternative that protects the edge auth login
form from brute-force and credential-stuffing attacks.

## What is Turnstile?

- Free tier: 1 million verifications per month
- Privacy-focused — no Google tracking
- Better UX than reCAPTCHA (managed/invisible challenge)
- Simple frontend widget + server-side verification

**Docs:** https://developers.cloudflare.com/turnstile/

## How It Works

1. **Frontend:** Turnstile widget is rendered in the login form
2. **User interaction:** Cloudflare runs an invisible or visible challenge
3. **Form submission:** A `cf-turnstile-response` token is included in the POST body
4. **Backend verification:** The platform POSTs the token to the Cloudflare API
5. **Result:** Login proceeds only if verification succeeds

## Setup Steps

### 1. Create a Turnstile Site in Cloudflare

1. Log in to [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Navigate to **Turnstile** in the left sidebar
3. Click **Add Site**
4. Fill in:
   - **Site name:** `Brama Platform - Production` (or any label)
   - **Domain:** your production domain (e.g. `platform.example.com`)
   - **Widget mode:** `Managed` (recommended — Cloudflare decides challenge level)
5. Click **Create**
6. Copy the **Site Key** (public) and **Secret Key** (private)

> For local development, use Cloudflare's always-passing test keys (see below).

### 2. Configure Environment Variables

**Local development (`.env.deployment`):**

```bash
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=1x00000000000000000000AA       # Test key — always passes
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA  # Test key — always passes
```

Set `TURNSTILE_ENABLED=false` to disable the widget entirely during development.

**Production (Kubernetes secret):**

```bash
kubectl create secret generic core-secrets \
  --from-literal=TURNSTILE_SITE_KEY=<your-real-site-key> \
  --from-literal=TURNSTILE_SECRET_KEY=<your-real-secret-key> \
  -n brama \
  --dry-run=client -o yaml | kubectl apply -f -
```

The Helm chart (`values-prod.example.yaml`) already sets `TURNSTILE_ENABLED: "true"` and
references `core-secrets` via `secretRef`.

### 3. Verify the Setup

1. Navigate to the edge auth login page: `http://localhost/edge/auth/login`
2. You should see the Turnstile widget below the password field
3. Test the flow:
   - ✅ Widget solved → login proceeds normally
   - ❌ Widget not solved → error message: "Не вдалося пройти перевірку CAPTCHA. Спробуйте ще раз."

## Test Keys (Development)

Cloudflare provides special keys for testing that always pass verification:

| Key type   | Value                                          |
|------------|------------------------------------------------|
| Site key   | `1x00000000000000000000AA`                     |
| Secret key | `1x0000000000000000000000000000000AA`          |

These keys are pre-configured in `.env.deployment.example`.

## Troubleshooting

**Widget not showing:**
- Check browser console for JavaScript errors
- Verify `TURNSTILE_ENABLED=true` in your environment
- Confirm `TURNSTILE_SITE_KEY` is set and non-empty

**Verification always failing:**
- Verify `TURNSTILE_SECRET_KEY` matches the site key in Cloudflare dashboard
- Confirm the platform server can reach `challenges.cloudflare.com` (outbound HTTPS)
- Check Cloudflare dashboard → Turnstile → Analytics for failed verification logs

**Rate limiting:**
- Free tier: 1 million verifications per month
- Upgrade your Cloudflare plan if you hit the limit

## Security Notes

- The **secret key** is never exposed to the browser — verification is server-side only
- Failed verification returns HTTP 401 Unauthorized
- The client IP is forwarded to Cloudflare for improved fraud detection
- If the Cloudflare API is unreachable, verification fails closed (login is blocked)
- Set `TURNSTILE_ENABLED=false` only in trusted internal environments

## Related Files

| File | Purpose |
|------|---------|
| `brama-core/src/src/Controller/EdgeAuth/LoginController.php` | Server-side verification logic |
| `brama-core/src/templates/edge_auth/login.html.twig` | Frontend widget integration |
| `brama-core/src/config/services.yaml` | Service parameters for Turnstile |
| `.env.deployment.example` | Environment variable reference |
| `brama-core/deploy/charts/brama/values-prod.example.yaml` | Kubernetes/Helm configuration |
