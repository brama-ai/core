# Production Domains for External Services on K3S

## Overview

This guide covers configuring production domain routing and edge authentication for external admin
services (Langfuse, LiteLLM, OpenClaw) when deploying the Brama platform on a K3S cluster.

In Docker Compose (local dev), these services are exposed via Traefik with `edge-auth@docker`
middleware. On Kubernetes, the equivalent setup requires:

1. A Traefik `Middleware` CRD for ForwardAuth
2. Ingress rules for each external service
3. Core service environment variables pointing to the correct public URLs

---

## Domain Strategy

### Option A — nip.io (quick staging, no DNS required)

Use `nip.io` to get wildcard DNS for any IP address without registering a domain:

| Service | URL |
|---------|-----|
| Core platform | `http://46.62.135.86.nip.io` |
| Langfuse | `http://langfuse.46.62.135.86.nip.io` |
| LiteLLM | `http://litellm.46.62.135.86.nip.io` |
| OpenClaw | `http://openclaw.46.62.135.86.nip.io` |

**Pros**: No DNS setup required, works immediately.  
**Cons**: Not suitable for TLS (cert-manager cannot issue certs for nip.io), not production-grade.

### Option B — Real domain (production-ready)

Register a domain and create DNS A records pointing to the server IP:

| Record | Value |
|--------|-------|
| `platform.example.com` | `46.62.135.86` |
| `langfuse.example.com` | `46.62.135.86` |
| `litellm.example.com` | `46.62.135.86` |
| `openclaw.example.com` | `46.62.135.86` |

**Pros**: TLS-ready, production-grade, stable URLs.  
**Cons**: Requires domain registration and DNS propagation time.

---

## External Services Deployment Strategy

External services (Langfuse, LiteLLM, OpenClaw) have complex infrastructure dependencies:

| Service | Dependencies |
|---------|-------------|
| Langfuse | PostgreSQL, Redis, ClickHouse, MinIO |
| LiteLLM | PostgreSQL |
| OpenClaw | OpenSearch |

**Recommended approach for K3S**: Run external services as Docker Compose on the same server,
expose them via Traefik ingress to the K3S cluster.

This avoids the complexity of packaging each service as a Kubernetes sub-chart while still
providing unified domain routing and edge authentication.

---

## Step 1: Deploy Edge Auth Middleware

Create a Traefik `Middleware` CRD in the `brama` namespace. This is the Kubernetes equivalent of
the `edge-auth@docker` middleware used in Docker Compose.

Create `edge-auth-middleware.yaml`:

```yaml
apiVersion: traefik.containo.us/v1alpha1
kind: Middleware
metadata:
  name: edge-auth
  namespace: brama
spec:
  forwardAuth:
    address: http://brama-core/edge/auth/verify
    trustForwardHeader: true
    authResponseHeaders:
      - X-Forwarded-User
```

Apply it:

```bash
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
kubectl apply -f edge-auth-middleware.yaml
kubectl get middleware -n brama
```

> **Note**: The middleware address `http://brama-core/edge/auth/verify` assumes the core service
> is named `brama-core` in the `brama` namespace. Adjust if your Helm release name differs.

---

## Step 2: Configure Ingress for External Services

Update your Helm values to add ingress routes for external services. The ingress template
(`templates/ingress.yaml`) routes traffic to in-cluster services. For Docker Compose services
running on the same host, you need `ExternalName` services or direct IP routing.

### Option A: ExternalName services (recommended)

Create Kubernetes `Service` objects of type `ExternalName` pointing to the host's Docker Compose
services:

```yaml
# external-services.yaml
apiVersion: v1
kind: Service
metadata:
  name: langfuse-external
  namespace: brama
spec:
  type: ExternalName
  externalName: host.k3s.internal  # K3S host gateway IP
  ports:
    - port: 3000
      targetPort: 3000
---
apiVersion: v1
kind: Service
metadata:
  name: litellm-external
  namespace: brama
spec:
  type: ExternalName
  externalName: host.k3s.internal
  ports:
    - port: 4000
      targetPort: 4000
---
apiVersion: v1
kind: Service
metadata:
  name: openclaw-external
  namespace: brama
spec:
  type: ExternalName
  externalName: host.k3s.internal
  ports:
    - port: 3001
      targetPort: 3001
```

> **K3S host IP**: In K3S, the host is typically accessible at `172.17.0.1` or via
> `host.k3s.internal`. Verify with:
> ```bash
> kubectl run -it --rm debug --image=busybox --restart=Never -- nslookup host.k3s.internal
> ```

### Option B: Direct IP in Ingress annotations

Use Traefik's `ServersTransport` or direct backend IP in ingress annotations (less portable).

---

## Step 3: Create Production Values File

Create `values-k3s-production.yaml` (do NOT commit — contains environment-specific config):

```yaml
# K3S Production Values
# Replace 46.62.135.86 with your actual server IP or domain

global:
  imagePullPolicy: IfNotPresent

core:
  enabled: true
  image:
    repository: brama/brama-core
    tag: "dev"
    pullPolicy: IfNotPresent
  replicaCount: 1
  env:
    APP_ENV: prod
    LANGFUSE_ENABLED: "true"
    EDGE_AUTH_COOKIE_NAME: ACP_EDGE_TOKEN
    EDGE_AUTH_TOKEN_TTL: "43200"
    # ⚠️ Set to your actual domain:
    EDGE_AUTH_LOGIN_BASE_URL: "http://46.62.135.86.nip.io"
    EDGE_AUTH_COOKIE_DOMAIN: ".46.62.135.86.nip.io"
    ADMIN_LANGFUSE_URL: "http://langfuse.46.62.135.86.nip.io/"
    ADMIN_LITELLM_URL: "http://litellm.46.62.135.86.nip.io/"
    ADMIN_OPENCLAW_URL: "http://openclaw.46.62.135.86.nip.io/"
  secretRef: brama-core-secrets

ingress:
  enabled: true
  className: traefik
  annotations:
    traefik.ingress.kubernetes.io/router.middlewares: brama-edge-auth@kubernetescrd
  hosts:
    core: 46.62.135.86.nip.io
    langfuse: langfuse.46.62.135.86.nip.io
    litellm: litellm.46.62.135.86.nip.io
    openclaw: openclaw.46.62.135.86.nip.io
  tls:
    enabled: false
```

> **Cookie domain**: Use `.46.62.135.86.nip.io` (with leading dot) to share the auth cookie
> across all subdomains. For real domains, use `.example.com`.

---

## Step 4: Deploy with Production Values

```bash
# On the server
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml

# Transfer chart (from dev machine)
tar czf /tmp/brama-chart.tar.gz -C brama-core/deploy/charts brama
scp -i ~/.ssh/ai_platform -F /dev/null -o IdentitiesOnly=yes \
    /tmp/brama-chart.tar.gz root@46.62.135.86:/tmp/

# On server — extract and deploy
ssh -i ~/.ssh/ai_platform -F /dev/null root@46.62.135.86 << 'EOF'
  export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
  mkdir -p /tmp/brama-deploy
  tar xzf /tmp/brama-chart.tar.gz -C /tmp/brama-deploy

  helm upgrade --install brama /tmp/brama-deploy/brama \
    --namespace brama \
    -f /tmp/brama-deploy/brama/values-k3s-production.yaml \
    --wait --timeout 5m
EOF
```

---

## Step 5: Verify Edge Authentication

After deployment, verify that edge auth is working correctly:

```bash
# Should redirect to login (HTTP 302 or 401)
curl -v http://langfuse.46.62.135.86.nip.io/
# Expected: redirect to http://46.62.135.86.nip.io/edge/auth/login

# Should redirect to login
curl -v http://litellm.46.62.135.86.nip.io/

# Should redirect to login
curl -v http://openclaw.46.62.135.86.nip.io/

# Core platform should be accessible (login page)
curl -sf http://46.62.135.86.nip.io/health
# Expected: {"status":"ok",...}
```

### Verify webhook bypass (OpenClaw)

OpenClaw's Telegram webhook endpoint must bypass edge auth:

```bash
# Should NOT redirect — must return 200 or 404 (not 302)
curl -v http://openclaw.46.62.135.86.nip.io/api/channels/
```

### Verify edge auth bypass for login

The login endpoint itself must bypass edge auth (otherwise login is impossible):

```bash
# Should return the login page HTML (not redirect)
curl -v http://46.62.135.86.nip.io/edge/auth/login
```

---

## Edge Authentication Reference

### How it works

```
Browser → Traefik → ForwardAuth middleware
                        ↓
                    POST http://brama-core/edge/auth/verify
                        ↓
                    Check cookie: ACP_EDGE_TOKEN (JWT)
                        ↓
                    Valid?  → 204 No Content → allow request
                    Invalid? → 401 → redirect to login page
```

### Environment variables

| Variable | Description | Example |
|----------|-------------|---------|
| `EDGE_AUTH_JWT_SECRET` | JWT signing secret — **change in production!** | `openssl rand -hex 32` |
| `EDGE_AUTH_COOKIE_NAME` | Cookie name | `ACP_EDGE_TOKEN` |
| `EDGE_AUTH_TOKEN_TTL` | Token lifetime in seconds | `43200` (12 hours) |
| `EDGE_AUTH_LOGIN_BASE_URL` | Base URL for login redirects | `http://46.62.135.86.nip.io` |
| `EDGE_AUTH_COOKIE_DOMAIN` | Cookie domain (with leading dot for subdomains) | `.46.62.135.86.nip.io` |

### Implementation files

| File | Purpose |
|------|---------|
| `brama-core/src/src/EdgeAuth/EdgeJwtService.php` | JWT creation and validation |
| `brama-core/src/src/Controller/EdgeAuth/VerifyController.php` | Auth verification endpoint |
| `brama-core/src/src/Controller/EdgeAuth/LoginController.php` | Login form and token creation |

---

## Validation Checklist

- [ ] Edge auth middleware deployed to K3S (`kubectl get middleware -n brama`)
- [ ] Ingress routes created for all external services (`kubectl get ingress -n brama`)
- [ ] Core service has correct `ADMIN_*_URL` environment variables
- [ ] `curl http://langfuse.46.62.135.86.nip.io/` redirects to login
- [ ] After login, Langfuse UI is accessible
- [ ] After login, LiteLLM UI is accessible
- [ ] After login, OpenClaw UI is accessible
- [ ] Edge auth cookie is set correctly for each subdomain
- [ ] OpenClaw webhook endpoint (`/api/channels/`) bypasses edge auth
- [ ] Edge auth login endpoint (`/edge/auth/`) bypasses edge auth

---

## Troubleshooting

### Middleware not found

```
Error: middleware "brama-edge-auth@kubernetescrd" not found
```

The Traefik Middleware CRD was not applied. Check:

```bash
kubectl get middleware -n brama
kubectl describe middleware edge-auth -n brama
```

### Cookie not propagating across subdomains

Ensure `EDGE_AUTH_COOKIE_DOMAIN` starts with a dot (`.46.62.135.86.nip.io`).
Without the leading dot, the cookie is host-only and won't be sent to subdomains.

> **Note**: `.localhost` and IP-based domains may have browser restrictions on cross-subdomain
> cookies. Use a real domain for production.

### External service not reachable from K3S

If using `ExternalName` services, verify the host IP is reachable from within the cluster:

```bash
kubectl run -it --rm debug --image=busybox --restart=Never -- \
  wget -O- http://host.k3s.internal:3000/health
```

### Ingress routes to wrong service

Check the ingress backend configuration:

```bash
kubectl describe ingress brama -n brama
```

---

## Related Guides

- [Kubernetes Installation Guide](./kubernetes-install.md) — full K3S setup
- [Production Deployment Guide (Docker)](./deployment.md) — Docker Compose path
- [Deployment Overview](./deployment-overview.md) — topology comparison
