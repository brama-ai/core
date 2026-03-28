# Kubernetes Installation Guide

## Overview

This guide covers installing Brama on a Kubernetes cluster using the official
Helm chart located at `deploy/charts/brama/`.

> **Status**: Initial packaging skeleton. The chart defines the operator contract for configuration,
> secrets, migrations, probes, and ingress. Image publishing and a hosted chart repository are
> planned for a future release. For now, install from the local chart path.

This guide is split into four practical sections:

- local quickstart
- production-style installation via values
- day-2 operations
- troubleshooting

That layout is closer to operator-facing documentation used by projects like LangChain/LangSmith
and Apache Airflow: first the shortest path to a working environment, then the stable deployment
path for a real cluster.

## Deployment Modes

The platform supports two official deployment modes:

| Mode | Best for | Packaging |
|------|----------|-----------|
| **Docker Compose** | Local dev, hobby, single-host production | `compose.yaml` + Makefile |
| **Kubernetes** | Cluster-native operators, managed infrastructure | Helm chart |

This guide covers the Kubernetes path. For Docker, see
[`docs/guides/deployment/en/deployment.md`](./deployment.md).

## Prerequisites

- Kubernetes 1.27+
- Helm 3.12+ (must be installed on the machine where you run `helm` commands)
- `kubectl` configured for your target cluster
- An ingress controller (Traefik is bundled with K3s; nginx-ingress recommended for other clusters)
- cert-manager (optional, for TLS automation)
- Access to a container registry where platform images are published

### For the local K3s/dev helper flow

If you bring up Brama locally through the workspace Make targets, you also need:

- Docker for local image builds
- Rancher Desktop or a compatible K3s setup with `rdctl`
- the local chart path at `brama-core/deploy/charts/brama`

> `make k8s-load` currently imports images via `rdctl shell sudo k3s ctr images import -`.
> That means this helper flow is specifically designed for local K3s in Rancher Desktop.
> For `kind`, `minikube`, or a remote cluster, prefer direct `helm upgrade --install` and your own
> image delivery workflow.

### For a remote K3s server

If you deploy to a remote VPS/server running K3s, you need:

- SSH access to the server
- Helm installed **on the server** (K3s does not include Helm):
  ```bash
  curl -fsSL https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash
  ```
- `KUBECONFIG` set when running helm/kubectl on the server:
  ```bash
  export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
  ```
- A way to build and load images into K3s containerd (see [Remote K3s deployment](#remote-k3s-deployment) below)

> **Cross-architecture note**: If your development machine is ARM (Apple Silicon Mac) and the
> server is x86_64, you **cannot** simply `docker save | ssh | k3s ctr images import`. The
> images must be built for the target architecture. The simplest approach is to build directly
> on the server using `nerdctl` (see below).

## Quickstart: local K3s/dev

This is the shortest path when you want the platform running locally, not a production deployment.

### What the dev profile deploys

The current local profile deploys:

- `core`
- `core-scheduler`
- `hello-agent`
- PostgreSQL
- Redis
- RabbitMQ

### 1. Verify cluster context

```bash
make k8s-ctx
```

### 2. Run the full bootstrap

```bash
make k8s-setup
```

This runs, in order:

1. `make k8s-build`
2. `make k8s-load`
3. `make k8s-secrets`
4. `make k8s-deploy`

### 3. Check cluster state

```bash
make k8s-status
```

### 4. Open the service locally

```bash
make k8s-port-forward svc=core port=8080:80
curl -sf http://localhost:8080/health
```

### 5. Inspect logs if something fails

```bash
make k8s-logs svc=core
make k8s-logs svc=core-scheduler
make k8s-logs-all
```

### 6. Remove the release

```bash
make k8s-destroy
```

### Quickstart commands worth memorizing

| Command | Purpose |
|---------|---------|
| `make k8s-ctx` | Show current cluster context |
| `make k8s-build` | Build local Docker images |
| `make k8s-load` | Import images into local K3s containerd |
| `make k8s-secrets` | Create the baseline core secret |
| `make k8s-deploy` | Run `helm upgrade --install` |
| `make k8s-status` | Show pods, services, ingress, and Helm release |
| `make k8s-shell svc=core` | Open a shell in a pod |
| `make k8s-port-forward svc=core port=8080:80` | Access a service locally |

## Remote K3s deployment

This section covers deploying to a remote VPS running K3s. This is the recommended path for
staging/demo environments and small-scale production when you don't have a managed Kubernetes
cluster.

### 1. Prepare the server

Install K3s on a fresh Ubuntu 24.04+ server:

```bash
ssh root@YOUR_SERVER_IP

# Install K3s
curl -sfL https://get.k3s.io | sh -

# Verify
kubectl get nodes
# NAME              STATUS   ROLES           AGE   VERSION
# your-server       Ready    control-plane   30s   v1.34.x+k3s1

# Install Helm (K3s does not include it)
curl -fsSL https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash

# Set KUBECONFIG for this session (add to ~/.bashrc for persistence)
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
```

### 2. Build images on the server

If your dev machine and the server have different CPU architectures (e.g. ARM Mac vs x86_64 server),
build images directly on the server. Install `nerdctl` and `buildkit`:

```bash
# Install nerdctl
NERDCTL_VERSION=$(curl -fsSL https://api.github.com/repos/containerd/nerdctl/releases/latest \
  | grep tag_name | cut -d'"' -f4 | tr -d v)
curl -fsSL "https://github.com/containerd/nerdctl/releases/download/v${NERDCTL_VERSION}/nerdctl-${NERDCTL_VERSION}-linux-amd64.tar.gz" \
  | tar -xz -C /usr/local/bin nerdctl

# Install buildkit
BUILDKIT_VERSION=$(curl -fsSL https://api.github.com/repos/moby/buildkit/releases/latest \
  | grep tag_name | cut -d'"' -f4 | tr -d v)
curl -fsSL "https://github.com/moby/buildkit/releases/download/v${BUILDKIT_VERSION}/buildkit-v${BUILDKIT_VERSION}.linux-amd64.tar.gz" \
  | tar -xz -C /usr/local

# Start buildkit as a systemd service
cat > /etc/systemd/system/buildkit.service <<'EOF'
[Unit]
Description=BuildKit
After=network.target

[Service]
ExecStart=/usr/local/bin/buildkitd
Restart=always

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload && systemctl enable --now buildkit

# Link K3s containerd socket to the default path
mkdir -p /run/containerd
ln -sf /run/k3s/containerd/containerd.sock /run/containerd/containerd.sock
```

Copy source files and build:

```bash
# From your dev machine / devcontainer:
SSH_CMD="ssh -F /path/to/ssh-config"
$SSH_CMD server "mkdir -p /opt/brama-build/brama-core/src /opt/brama-build/hello-agent"

rsync -az --delete -e "$SSH_CMD" brama-core/src/ server:/opt/brama-build/brama-core/src/
rsync -az -e "$SSH_CMD" docker/brama-core/Dockerfile server:/opt/brama-build/Dockerfile
rsync -az --delete -e "$SSH_CMD" brama-agents/hello-agent/ server:/opt/brama-build/hello-agent/

# On the server:
cd /opt/brama-build
nerdctl --address /run/k3s/containerd/containerd.sock -n k8s.io \
  build -t brama/brama-core:dev -f Dockerfile .

cd /opt/brama-build/hello-agent
nerdctl --address /run/k3s/containerd/containerd.sock -n k8s.io \
  build -t brama/hello-agent:dev .
```

> **Same-architecture shortcut**: If both machines are x86_64, you can build locally and transfer:
> ```bash
> docker save brama/brama-core:dev | ssh root@SERVER "k3s ctr images import -"
> ```

### 3. Copy Helm chart and deploy

```bash
# From dev machine:
rsync -az --delete -e "$SSH_CMD" \
  brama-core/deploy/charts/brama/ server:/opt/brama-build/charts/brama/

# On the server:
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml

# Create namespace and secrets
kubectl create namespace brama

kubectl create secret generic brama-core-secrets -n brama \
  --from-literal=APP_SECRET="$(openssl rand -hex 16)" \
  --from-literal=EDGE_AUTH_JWT_SECRET="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:app@brama-postgresql:5432/ai_community_platform?serverVersion=16&charset=utf8" \
  --from-literal=REDIS_URL="redis://brama-redis-master:6379" \
  --from-literal=RABBITMQ_URL="amqp://app:app@brama-rabbitmq:5672" \
  --from-literal=POSTGRES_PROVISIONER_URL="postgresql://app:app@brama-postgresql:5432/ai_community_platform?serverVersion=16&charset=utf8"

# Install
cd /opt/brama-build/charts/brama
helm dependency update .
helm upgrade --install brama . \
  --namespace brama \
  -f values-k3s-dev.yaml \
  --set ingress.hosts.core=YOUR_SERVER_IP.nip.io \
  --wait --timeout 10m
```

### 4. Run migrations

If this is a fresh install, run database migrations:

```bash
kubectl exec -n brama deploy/brama-core -- \
  php bin/console doctrine:migrations:migrate --no-interaction
```

> **Note**: The Helm chart includes a migration job hook, but if the first install times out
> (e.g. due to slow image pulls), the migration job may not complete. Always verify migrations
> ran by checking `kubectl get jobs -n brama`.

### 5. Verify

```bash
# Check pods
kubectl get pods -n brama

# Health check (from the server)
curl -sf -H "Host: YOUR_SERVER_IP.nip.io" http://localhost/health

# Health check (from anywhere)
curl -sf http://YOUR_SERVER_IP.nip.io/health
```

## Service Topology

### Mandatory application services

| Service | Description | Replicas |
|---------|-------------|----------|
| `core` | Main platform (PHP/Symfony) | 1+ |
| `core-scheduler` | Background scheduler | 1 (fixed) |

### Optional agents (enable per environment)

| Agent | Default | Port |
|-------|---------|------|
| `knowledge` | enabled | 8083 |
| `hello` | enabled | 8085 |
| `newsMaker` | disabled | 8087 |

### Infrastructure dependencies

| Dependency | Bundled by default | Recommended for production |
|------------|-------------------|---------------------------|
| PostgreSQL | Yes (sub-chart) | External managed (RDS, Cloud SQL, etc.) |
| Redis | Yes (sub-chart) | External managed (ElastiCache, Memorystore, etc.) |
| OpenSearch | No | External managed or omit |
| RabbitMQ | No | External managed or omit |

## Step 1: Prepare Secrets

Create Kubernetes Secrets before installing the chart. The chart does not create secrets — it
references them by name.

### Core secrets

```bash
kubectl create namespace brama

kubectl create secret generic core-secrets \
  --namespace brama \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)" \
  --from-literal=EDGE_AUTH_JWT_SECRET="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/ai_community_platform?serverVersion=16&charset=utf8" \
  --from-literal=LANGFUSE_PUBLIC_KEY="lf_pk_your_key" \
  --from-literal=LANGFUSE_SECRET_KEY="lf_sk_your_key"
```

### LiteLLM secrets

```bash
kubectl create secret generic litellm-secrets \
  --namespace brama \
  --from-literal=LITELLM_MASTER_KEY="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/litellm?serverVersion=16&charset=utf8" \
  --from-literal=OPENROUTER_API_KEY="sk-or-your-key"
```

### Agent secrets (repeat for each enabled agent)

```bash
kubectl create secret generic knowledge-agent-secrets \
  --namespace brama \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/knowledge_agent?serverVersion=16&charset=utf8"
```

> **Security note**: In production, prefer an external secret operator (External Secrets Operator,
> Sealed Secrets, Vault Agent Injector) over `kubectl create secret` to avoid secrets in shell
> history.

### Secrets created by the local helper flow

`make k8s-secrets` creates a `brama-core-secrets` secret in the `brama` namespace with:

- `APP_SECRET`
- `EDGE_AUTH_JWT_SECRET`
- `DATABASE_URL`
- `REDIS_URL`
- `RABBITMQ_URL`
- `POSTGRES_PROVISIONER_URL`

That is fine for local development. For production, prefer:

- per-service secrets
- no ad-hoc shell-generated secrets in operator history
- managed secret delivery through External Secrets / Vault / Sealed Secrets

## Step 2: Prepare Values

Copy the example values file and customize it:

```bash
cp deploy/charts/brama/values-prod.example.yaml values-prod.yaml
```

Edit `values-prod.yaml` with your environment-specific settings:

- Set `ingress.hosts.*` to your actual domain names
- Set `secretRef` fields to match the secret names you created
- Set image tags to the target release version
- Disable bundled sub-charts if using external managed services:
  ```yaml
  postgresql:
    enabled: false
  redis:
    enabled: false
  externalDependencies:
    postgres:
      external: true
      host: your-postgres-host
    redis:
      external: true
      host: your-redis-host
  ```

### Which values file to start from

| Scenario | Starting file |
|----------|---------------|
| Local K3s / demo | `deploy/charts/brama/values-k3s-dev.yaml` |
| Production-like cluster | `deploy/charts/brama/values-prod.example.yaml` |

Practical rule:

- use `values-k3s-dev.yaml` for fast local bring-up
- use `values-prod.example.yaml` as the baseline for real cluster rollout
- do not try to grow the dev values into production without revisiting secrets, ingress, and persistence

## Step 3: Install the Chart

```bash
helm upgrade --install brama \
  ./deploy/charts/brama \
  --namespace brama \
  --create-namespace \
  -f values-prod.yaml \
  --wait \
  --timeout 15m
```

The `--wait` flag causes Helm to wait until all Deployments and Jobs reach a ready state before
returning. The migration job runs as a `post-install` hook before the application pods start.

## Step 4: Verify the Installation

### Check pod status

```bash
kubectl get pods -n brama
```

All pods should reach `Running` state. The migration job pod will show `Completed`.

### Check migration job

```bash
kubectl get jobs -n brama
kubectl logs job/brama-migrate-1 -n brama
```

The migration job logs should end with `==> Migrations complete`.

### Check rollout status

```bash
kubectl rollout status deploy/brama-core -n brama
```

### Check ingress

```bash
kubectl get ingress -n brama
```

### Test health endpoint

```bash
# Replace with your actual domain or use port-forward
curl -sf https://platform.example.com/health
```

Or with port-forward:

```bash
kubectl port-forward -n brama svc/brama-core 8080:80
curl -sf http://localhost:8080/health
```

### Check Helm release status

```bash
helm status brama -n brama
```

## Step 5: Post-Install Verification

Minimum smoke checks after a fresh install:

- [ ] Platform URL loads and shows the login page
- [ ] Admin login works
- [ ] At least one agent health endpoint responds
- [ ] LiteLLM UI is accessible (if enabled)
- [ ] Migration job completed without errors

## Day-2 operations

### Update chart dependencies

```bash
cd deploy/charts/brama
helm dependency update
```

Or through the workspace helper:

```bash
make k8s-deps
```

### Review changes before upgrade

```bash
make k8s-diff
```

### Upgrade the release

```bash
make k8s-upgrade
```

### Inspect workloads

```bash
make k8s-status
kubectl get pods -n brama -o wide
```

### Tail service logs

```bash
make k8s-logs svc=core
make k8s-logs svc=core-scheduler
make k8s-logs svc=agent-hello
```

### Open a shell in a pod

```bash
make k8s-shell svc=core
```

### Port-forward a service

```bash
make k8s-port-forward svc=core port=8080:80
```

## Operator checklist before production rollout

- [ ] Images are published to a registry reachable by the cluster
- [ ] Namespace and ingress policy are agreed
- [ ] Secrets are managed outside the shell workflow
- [ ] Persistence policy is defined for stateful components
- [ ] `postgresql.enabled` and `redis.enabled` are disabled when using external managed services
- [ ] **Storage verification gate passed** — see [Storage Verification Procedures](./k3s-storage-verification.md)
  - [ ] PostgreSQL PVC is `Bound`
  - [ ] PostgreSQL pod-restart test confirms data survives
  - [ ] Pre-upgrade PostgreSQL backup taken — see [Backup and Restore Runbook](./k3s-storage-backup.md)
- [ ] Rollback is tested with `helm rollback`
- [ ] Post-deploy smoke checks exist for `/health`, login, and scheduler behavior

## Configuration Reference

### Key values

| Value | Description | Default |
|-------|-------------|---------|
| `core.image.tag` | Core app image tag | `0.1.0` |
| `core.secretRef` | Secret name for core env vars | `""` |
| `core.replicaCount` | Core replicas | `1` |
| `ingress.enabled` | Enable ingress | `true` |
| `ingress.tls.enabled` | Enable TLS | `false` |
| `migrations.enabled` | Run migration job on install/upgrade | `true` |
| `postgresql.enabled` | Bundle PostgreSQL sub-chart | `true` |
| `redis.enabled` | Bundle Redis sub-chart | `true` |

See `deploy/charts/brama/values.yaml` for the full reference with all defaults.

## Probe Behavior

Every HTTP service exposes a `/health` endpoint wired to Kubernetes probes:

| Probe | Purpose | Failure action |
|-------|---------|----------------|
| `startupProbe` | Allows slow startup before liveness kicks in | Restart after 24 failures (2 min) |
| `readinessProbe` | Gates traffic until the app is ready | Remove from load balancer |
| `livenessProbe` | Restarts unhealthy containers | Restart container |

The scheduler uses an `exec` liveness probe instead of HTTP.

## Migration Behavior

Migrations run as a Kubernetes Job with Helm hook annotations:

```yaml
helm.sh/hook: pre-upgrade,post-install
helm.sh/hook-weight: "-5"
helm.sh/hook-delete-policy: before-hook-creation,hook-succeeded
```

This means:
- On fresh install: migration job runs after chart resources are created
- On upgrade: migration job runs before the new application pods start
- Completed jobs are cleaned up automatically on the next release

If the migration job fails, the Helm release will be marked as failed. Do not proceed with traffic
validation until migrations complete successfully.

> **First install note**: If the initial `helm install` times out (e.g. due to slow image pulls
> or infrastructure startup), the `post-install` migration hook may not run. In this case, run
> migrations manually with `kubectl exec` and then `helm upgrade` to stabilize the release.

## Troubleshooting

### Pod stuck in Pending

```bash
kubectl describe pod <pod-name> -n brama
```

Common causes: insufficient cluster resources, missing PVC, missing secret.

### Migration job failed

```bash
kubectl logs job/brama-migrate-1 -n brama
```

Check database connectivity issues or schema conflicts.

### Core pod in CrashLoopBackOff

```bash
kubectl logs deploy/brama-core -n brama --previous
```

Common causes: missing secret reference, invalid `DATABASE_URL`, failed migration.

### `make k8s-load` fails

Most often this means your local environment does not provide `rdctl`, or the cluster is not Rancher
Desktop K3s.

What to do:

- verify `rdctl version`
- or skip the helper load step and use registry-published images
- or adapt the workflow for your runtime (`kind load docker-image`, `minikube image load`, etc.)

### Helm release exists but the service is unreachable

Check these in order:

1. `kubectl get ingress -n brama`
2. `kubectl get svc -n brama`
3. `kubectl describe ingress <name> -n brama`
4. `kubectl port-forward -n brama svc/brama-core 8080:80`

If `/health` works through port-forward, the problem is almost certainly in ingress, DNS, or TLS.

Check for database connectivity issues or schema conflicts. Fix the root cause before retrying.

### Core pod CrashLoopBackOff

```bash
kubectl logs deploy/brama-core -n brama --previous
```

Common causes: missing secret reference, wrong DATABASE_URL, failed migration.

### Ingress not routing

```bash
kubectl describe ingress brama -n brama
kubectl get events -n brama --sort-by='.lastTimestamp'
```

Verify the ingress controller is installed and the `ingressClassName` matches.
For K3s, the default ingress class is `traefik`, not `nginx`.

### Agent pod returns 500 on `/health` — "Unable to write in the cache directory"

The Symfony cache directory must be writable. Ensure the agent Dockerfile creates the `var/`
directory with appropriate permissions:

```dockerfile
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && mkdir -p var/cache var/log \
    && chmod -R 777 var/
```

### `helm upgrade --install` on K3s server: "Kubernetes cluster unreachable"

Helm cannot find the kubeconfig. On a K3s server, set:

```bash
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
```

Add this to `~/.bashrc` for persistence.

### Cross-architecture: `ImagePullBackOff` or `exec format error`

If you build images on an ARM Mac (Apple Silicon) and push them to an x86_64 K3s server,
the images will fail to run. Solutions:

1. **Build on the server** using `nerdctl` (recommended, see [Remote K3s deployment](#remote-k3s-deployment))
2. **Cross-compile** with `docker buildx build --platform linux/amd64`
3. **Use a CI pipeline** that builds for the target architecture

### `docker buildx` fails with "changes out of order"

This is a known buildx issue on macOS with case-insensitive filesystems (HFS+/APFS). If your
source tree has files that differ only by case, buildx will fail. Workarounds:

1. Build on a Linux machine or the target server (recommended)
2. Create a case-sensitive APFS volume for the workspace
3. Add a `.dockerignore` to reduce the build context

### Migrations did not run on first install

If the first `helm install` times out, the migration Helm hook (`post-install`) may not complete.
Run migrations manually:

```bash
kubectl exec -n brama deploy/brama-core -- \
  php bin/console doctrine:migrations:migrate --no-interaction
```

Then run `helm upgrade` to mark the release as deployed.

## k3s Single-Node Deployment on Hetzner VPS

This section covers migrating from Docker Compose to k3s on a Hetzner CX32 VPS
(4 vCPU / 8 GB RAM). This is the recommended production path for single-operator deployments.

### Prerequisites

- Hetzner CX32 VPS (or equivalent) running Ubuntu 24.04+
- SSH access as root
- Domain name pointing to the VPS IP
- GitHub Actions secrets configured: `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`

### RAM Budget

The full stack fits within 8 GB RAM with conservative resource requests (~2.7 Gi total):

| Service | Requests | Limits |
|---------|----------|--------|
| PostgreSQL | 256 Mi | 512 Mi |
| Redis | 64 Mi | 128 Mi |
| OpenSearch | 768 Mi | 1536 Mi |
| RabbitMQ | 128 Mi | 256 Mi |
| Core | 256 Mi | 512 Mi |
| Core Scheduler | 128 Mi | 256 Mi |
| LiteLLM | 256 Mi | 384 Mi |
| Knowledge Agent + Worker | 256 Mi | 512 Mi |
| Hello Agent | 64 Mi | 128 Mi |
| Wiki Agent | 64 Mi | 128 Mi |
| News Maker Agent | 128 Mi | 256 Mi |
| Dev Reporter Agent | 64 Mi | 128 Mi |
| Langfuse (web+worker) | 256 Mi | 512 Mi |
| **Total** | **~2.7 Gi** | **~5.0 Gi** |

> **dev-agent** is disabled by default in `values-hetzner.yaml` — it is heavy (git + gh CLI).
> Enable it only if needed and monitor RAM usage with `kubectl top nodes`.

### Step 1: Backup PostgreSQL (Docker Compose)

Before stopping Docker Compose, back up all data:

```bash
ssh root@YOUR_VPS_IP

cd /root/app/brama-core  # or wherever your compose stack lives
docker compose exec postgres pg_dumpall -U app > /root/pg-backup-$(date +%Y%m%d).sql
ls -lh /root/pg-backup-*.sql  # verify backup has size > 0
```

### Step 2: Stop Docker Compose

```bash
docker compose down
docker ps  # verify no containers running
```

### Step 3: Install k3s

```bash
# Install k3s (keeps built-in Traefik ingress controller)
curl -sfL https://get.k3s.io | sh -

# Set KUBECONFIG for this session (add to ~/.bashrc for persistence)
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
echo 'export KUBECONFIG=/etc/rancher/k3s/k3s.yaml' >> ~/.bashrc

# Verify
kubectl get nodes
# NAME       STATUS   ROLES                  AGE   VERSION
# your-vps   Ready    control-plane,master   30s   v1.34.x+k3s1
```

### Step 4: Install Helm

```bash
curl -fsSL https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash
helm version
```

### Step 5: Deploy Local Container Registry

```bash
# Apply registry resources
kubectl apply -f - <<'EOF'
apiVersion: apps/v1
kind: Deployment
metadata:
  name: registry
  namespace: kube-system
spec:
  replicas: 1
  selector:
    matchLabels:
      app: registry
  template:
    metadata:
      labels:
        app: registry
    spec:
      hostNetwork: true
      containers:
        - name: registry
          image: registry:2
          ports:
            - containerPort: 5000
              hostPort: 5000
          volumeMounts:
            - name: data
              mountPath: /var/lib/registry
      volumes:
        - name: data
          hostPath:
            path: /var/lib/registry
            type: DirectoryOrCreate
EOF

# Configure k3s to trust the local registry
cat > /etc/rancher/k3s/registries.yaml <<'EOF'
mirrors:
  "registry.localhost:5000":
    endpoint:
      - "http://registry.localhost:5000"
EOF

# Restart k3s to pick up registries.yaml
systemctl restart k3s
sleep 10
kubectl get nodes  # should still be Ready

# Test registry
curl -s http://registry.localhost:5000/v2/_catalog
# {"repositories":[]}
```

### Step 6: Install cert-manager

```bash
helm repo add jetstack https://charts.jetstack.io
helm repo update
helm install cert-manager jetstack/cert-manager \
  --namespace cert-manager \
  --create-namespace \
  --set crds.enabled=true

# Wait for cert-manager pods
kubectl wait --for=condition=ready pod -l app.kubernetes.io/instance=cert-manager \
  -n cert-manager --timeout=120s

# Create Let's Encrypt ClusterIssuer
kubectl apply -f - <<'EOF'
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: your-email@example.com
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
      - http01:
          ingress:
            class: traefik
EOF
```

### Step 7: Build and Push Images

```bash
cd /root/app  # workspace root (parent of brama-core)

# Build all 7 platform images and push to local registry
bash brama-core/deploy/build-and-push.sh

# Verify all images are available
curl -s http://registry.localhost:5000/v2/_catalog
# {"repositories":["acp/brama-core","acp/knowledge-agent","acp/hello-agent",...]}
```

See [`deploy/build-and-push.sh`](../../../../deploy/build-and-push.sh) for usage details.

### Step 8: Create Namespace and Secrets

```bash
kubectl create namespace brama

# Core secrets
kubectl create secret generic core-secrets -n brama \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)" \
  --from-literal=EDGE_AUTH_JWT_SECRET="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@brama-postgresql:5432/ai_community_platform?serverVersion=16&charset=utf8" \
  --from-literal=LANGFUSE_PUBLIC_KEY="lf_pk_..." \
  --from-literal=LANGFUSE_SECRET_KEY="lf_sk_..."

# PostgreSQL password secret (used by Bitnami sub-chart)
kubectl create secret generic postgresql-secrets -n brama \
  --from-literal=postgres-password="POSTGRES_ADMIN_PASSWORD" \
  --from-literal=password="APP_PASSWORD"

# LiteLLM secrets
kubectl create secret generic litellm-secrets -n brama \
  --from-literal=LITELLM_MASTER_KEY="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@brama-postgresql:5432/litellm?serverVersion=16&charset=utf8" \
  --from-literal=OPENROUTER_API_KEY="sk-or-..."

# Agent secrets
kubectl create secret generic knowledge-agent-secrets -n brama \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@brama-postgresql:5432/knowledge_agent?serverVersion=16&charset=utf8"

kubectl create secret generic hello-agent-secrets -n brama \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)"

# Langfuse secrets
kubectl create secret generic langfuse-secrets -n brama \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@brama-postgresql:5432/langfuse?serverVersion=16&charset=utf8" \
  --from-literal=NEXTAUTH_SECRET="$(openssl rand -hex 32)" \
  --from-literal=NEXTAUTH_URL="https://langfuse.brama.example.com" \
  --from-literal=SALT="$(openssl rand -hex 16)"

# RabbitMQ password secret
kubectl create secret generic rabbitmq-secrets -n brama \
  --from-literal=rabbitmq-password="RABBITMQ_PASSWORD"
```

### Step 9: Deploy with Helm

Edit `deploy/charts/brama/values-hetzner.yaml` to set your actual domain names, then:

```bash
cd /root/app/brama-core

# Update sub-chart dependencies
helm dependency update ./deploy/charts/brama

# Deploy
helm upgrade --install brama ./deploy/charts/brama \
  --namespace brama \
  -f deploy/charts/brama/values-hetzner.yaml \
  --wait \
  --timeout 15m
```

### Step 10: Restore PostgreSQL Data

```bash
# Wait for PostgreSQL pod
kubectl wait --for=condition=ready pod -l app.kubernetes.io/name=postgresql \
  -n brama --timeout=120s

# Get PostgreSQL pod name
PG_POD=$(kubectl get pod -n brama -l app.kubernetes.io/name=postgresql \
  -o jsonpath='{.items[0].metadata.name}')

# Copy backup and restore
kubectl cp /root/pg-backup-*.sql brama/${PG_POD}:/tmp/backup.sql
kubectl exec -n brama ${PG_POD} -- psql -U app -f /tmp/backup.sql

# Restart application pods to pick up restored data
kubectl rollout restart deploy -n brama
```

### Step 11: Verify

```bash
# All pods should be Running or Completed
kubectl get pods -n brama

# Check ingress
kubectl get ingress -n brama

# Test health endpoints
kubectl port-forward -n brama svc/brama-core 8080:80 &
curl -sf http://localhost:8080/health  # {"status":"ok"}

# Check migration job
kubectl get jobs -n brama
kubectl logs job/brama-migrate-1 -n brama  # should end with "Migrations complete"

# Check TLS (after DNS propagation)
curl -sf https://brama.example.com/health
```

### Rollback to Docker Compose

If the k3s deployment fails:

```bash
helm uninstall brama -n brama
systemctl stop k3s
docker compose up -d  # PostgreSQL data intact in Docker volumes
```

## Next Steps

- [Upgrade runbook](./kubernetes-upgrade.md) — how to upgrade to a new release
- [Deployment topology matrix](./deployment-topology.md) — supported topologies and trade-offs
- [Docker deployment guide](./deployment.md) — Docker Compose path
- [k3s Storage Architecture](./k3s-storage-architecture.md) — storage durability tiers and PVC strategy
- [Storage Verification Procedures](./k3s-storage-verification.md) — PVC and pod-restart verification
- [PostgreSQL Backup and Restore Runbook](./k3s-storage-backup.md) — backup and restore procedures
