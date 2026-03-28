## 1. Extend Helm Chart — Add Missing Templates and Services

Add all missing Kubernetes resources so the chart covers the full docker-compose stack.

- [x] 1.1 Create `templates/litellm/deployment.yaml` — LiteLLM Deployment with health probes on port 4000, secretRef, configMapRef
- [x] 1.2 Create `templates/litellm/service.yaml` — ClusterIP service for LiteLLM
- [x] 1.3 Create `templates/litellm/configmap.yaml` — LiteLLM config.yaml as ConfigMap (conditional on `litellm.configMapRef`)
- [x] 1.4 Create `templates/agents/worker-deployment.yaml` — knowledge-worker Deployment (same image as knowledge-agent, command: `php bin/console messenger:consume async`, no HTTP port, exec liveness probe)
- [x] 1.5 Add `wiki`, `devReporter`, `devAgent` agent sections to `values.yaml` (following existing agent pattern: image, replicaCount, service.port, resources, probes, secretRef)
- [x] 1.6 Modify `templates/agents/deployment.yaml` — add `env` block support (inline env vars, matching core deployment pattern)
- [x] 1.7 Create `templates/openclaw/deployment.yaml` + `templates/openclaw/service.yaml` — OpenClaw gateway (conditional on `openclaw.enabled`)
- [x] 1.8 Create `templates/langfuse/deployment.yaml` + `templates/langfuse/service.yaml` — Langfuse web + worker (conditional on `langfuse.enabled`)
- [x] 1.9 Add OpenSearch and RabbitMQ as optional sub-chart dependencies in `Chart.yaml`
- [x] 1.10 Update `values.yaml` with `opensearch`, `rabbitmq`, `openclaw`, `langfuse` configuration sections
- [x] 1.11 Update `templates/ingress.yaml` — add host rules for Langfuse and OpenClaw (conditional)
- [x] 1.12 Extend `templates/jobs/migration.yaml` — support agent-specific migrations (knowledge-agent DB)

**Verification:**
```bash
helm dependency update ./deploy/charts/brama
helm lint ./deploy/charts/brama
helm template acp ./deploy/charts/brama \
  -f deploy/charts/brama/values-hetzner.yaml \
  --debug | kubectl apply --dry-run=client -f -
```

## 2. Create values-hetzner.yaml — Resource Budget for CX32

Production values file optimized for single-node Hetzner CX32 (4 vCPU / 8 GB RAM).

- [x] 2.1 Create `deploy/charts/brama/values-hetzner.yaml` with:
  - `ingress.className: traefik`
  - All services enabled with tight resource requests (total requests < 3 Gi)
  - Image repositories pointing to `registry.localhost:5000/acp/*`
  - Bundled sub-charts: postgresql, redis, opensearch, rabbitmq enabled
  - OpenSearch heap: `-Xms512m -Xmx512m`
  - secretRef fields for all services
  - cert-manager annotations for TLS
  - Actual domain names for ingress hosts
- [x] 2.2 Add dev-agent as disabled by default (heavy: git + gh CLI)

**Verification:**
```bash
helm template acp ./deploy/charts/brama \
  -f deploy/charts/brama/values-hetzner.yaml --debug
# Manually verify: total resource requests < 3 Gi, total limits < 5.5 Gi
```

## 3. Prepare VPS — Backup, Install k3s, Deploy Local Registry

SSH into Hetzner VPS and set up k3s infrastructure. Planned downtime begins here.

- [ ] 3.1 Backup PostgreSQL data:
  ```bash
  docker compose exec postgres pg_dumpall -U app > /root/pg-backup-$(date +%Y%m%d).sql
  ls -lh /root/pg-backup-*.sql  # verify backup exists and has size > 0
  ```
- [ ] 3.2 Stop Docker Compose stack:
  ```bash
  docker compose down
  docker ps  # verify no containers running
  ```
- [ ] 3.3 Install k3s (keep built-in Traefik):
  ```bash
  curl -sfL https://get.k3s.io | sh -
  export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
  ```
- [ ] 3.4 Install Helm:
  ```bash
  curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash
  helm version
  ```
- [ ] 3.5 Deploy local container registry:
  - Apply registry Deployment + Service + PVC (port 5000, NodePort or HostNetwork)
  - Configure `/etc/rancher/k3s/registries.yaml` to trust `registry.localhost:5000`
  - Restart k3s to pick up registries.yaml: `systemctl restart k3s`
- [ ] 3.6 Install cert-manager for TLS:
  ```bash
  helm repo add jetstack https://charts.jetstack.io
  helm install cert-manager jetstack/cert-manager --namespace cert-manager \
    --create-namespace --set crds.enabled=true
  kubectl apply -f cluster-issuer.yaml  # Let's Encrypt ClusterIssuer
  ```

**Verification:**
```bash
kubectl get nodes                              # STATUS: Ready
kubectl get pods -A                            # system pods all Running
docker pull nginx:alpine && \
  docker tag nginx:alpine registry.localhost:5000/test:v1 && \
  docker push registry.localhost:5000/test:v1
curl http://registry.localhost:5000/v2/_catalog  # {"repositories":["test"]}
kubectl get pods -n cert-manager               # cert-manager pods Running
```

## 4. Build and Push All Platform Images

Build all platform Dockerfiles on the VPS and push to the local registry.

- [x] 4.1 Create `deploy/build-and-push.sh` script:
  - Reads image list (service name → Dockerfile path → registry tag)
  - Builds each with `docker build`
  - Tags as `registry.localhost:5000/acp/<service>:<version>`
  - Pushes to local registry
  - Reports build status for each image
- [ ] 4.2 Clone/pull latest code on VPS
- [ ] 4.3 Run `deploy/build-and-push.sh` to build and push all 7 platform images:
  - core, knowledge-agent, hello-agent, wiki-agent, news-maker-agent, dev-reporter-agent, dev-agent

**Verification:**
```bash
curl http://registry.localhost:5000/v2/_catalog
# Expected: {"repositories":["acp/brama-core","acp/knowledge-agent","acp/hello-agent","acp/wiki-agent","acp/news-maker-agent","acp/dev-reporter-agent","acp/dev-agent"]}
# Verify each image has the expected tag:
for img in core knowledge-agent hello-agent wiki-agent news-maker-agent dev-reporter-agent dev-agent; do
  curl -s http://registry.localhost:5000/v2/acp/$img/tags/list
done
```

## 5. Deploy — Create Secrets, Restore DB, Helm Install

Create Kubernetes secrets, deploy via Helm, and restore PostgreSQL data.

- [ ] 5.1 Create namespace:
  ```bash
  kubectl create namespace acp
  ```
- [ ] 5.2 Create Kubernetes secrets (extract values from current `.env` files on server):
  - `core-secrets` — APP_SECRET, EDGE_AUTH_JWT_SECRET, DATABASE_URL, LANGFUSE keys
  - `litellm-secrets` — LITELLM_MASTER_KEY, DATABASE_URL, OPENROUTER_API_KEY
  - `knowledge-agent-secrets` — APP_SECRET, DATABASE_URL
  - Other agent secrets as needed
- [ ] 5.3 Run Helm dependency update and install:
  ```bash
  helm dependency update ./deploy/charts/ai-community-platform
  helm upgrade --install acp ./deploy/charts/ai-community-platform \
    --namespace acp -f deploy/charts/ai-community-platform/values-hetzner.yaml \
    --wait --timeout 15m
  ```
- [ ] 5.4 Wait for PostgreSQL pod to be ready, then restore backup:
  ```bash
  kubectl wait --for=condition=ready pod -l app.kubernetes.io/name=postgresql -n acp --timeout=120s
  kubectl cp /root/pg-backup-*.sql acp/<pg-pod>:/tmp/backup.sql
  kubectl exec -n acp <pg-pod> -- psql -U app -f /tmp/backup.sql
  ```
- [ ] 5.5 Restart application pods to pick up restored data:
  ```bash
  kubectl rollout restart deploy -n acp
  ```

**Verification:**
```bash
kubectl get pods -n acp                    # All pods Running or Completed
kubectl get ingress -n acp                 # Hosts configured with Traefik
# Port-forward and test:
kubectl port-forward -n acp svc/acp-core 8080:80 &
curl -sf http://localhost:8080/health      # {"status":"ok"}
# Or via domain (if DNS already points to VPS):
curl -sf https://<domain>/health
# Check agent health:
kubectl port-forward -n acp svc/acp-agent-hello 8085:8085 &
curl -sf http://localhost:8085/health
# Check LiteLLM:
kubectl port-forward -n acp svc/acp-litellm 4000:4000 &
curl -sf http://localhost:4000/health
# Check migration job:
kubectl get jobs -n acp
kubectl logs job/acp-migrate -n acp        # Should end with "Migrations complete"
```

## 6. Update CI/CD — deploy.yml for k3s

Modify the GitHub Actions deploy workflow to use Helm instead of Docker Compose.

- [x] 6.1 Update `.github/workflows/deploy.yml`:
  - SSH into VPS
  - `git pull` to get latest code
  - Run `deploy/build-and-push.sh` for changed services
  - Run `helm upgrade --install acp ... --wait`
  - Check rollout status
- [x] 6.2 Add `KUBECONFIG` path to SSH commands (`/etc/rancher/k3s/k3s.yaml`)

**Verification:**
```bash
# Trigger workflow manually via GitHub Actions UI with services: all
# Or push a trivial change to main and observe:
# - Workflow runs successfully
# - New images are pushed to local registry
# - helm upgrade succeeds
# - All pods restart with new images
kubectl rollout status deploy/acp-core -n acp
```

## 7. Documentation

- [x] 7.1 Update `docs/guides/deployment/en/kubernetes-install.md` — add k3s single-node section with Hetzner-specific instructions
- [x] 7.2 Update `docs/guides/deployment/ua/kubernetes-install.md` — Ukrainian mirror
- [x] 7.3 Document the `deploy/build-and-push.sh` script usage in `deploy/README.md`

## 8. Quality Checks

- [x] 8.1 `helm lint ./deploy/charts/brama` — no warnings
- [x] 8.2 `helm template` with values-hetzner.yaml — all resources render correctly (59 resources)
- [ ] 8.3 All pods in `acp` namespace are Running
- [ ] 8.4 Core `/health` endpoint responds 200
- [ ] 8.5 At least one agent `/health` endpoint responds 200
- [ ] 8.6 LiteLLM `/health` endpoint responds 200
- [ ] 8.7 PostgreSQL data is intact (admin login works, existing data visible)
- [ ] 8.8 TLS certificate is provisioned (HTTPS works on the domain)
- [ ] 8.9 CI/CD workflow successfully deploys via Helm
