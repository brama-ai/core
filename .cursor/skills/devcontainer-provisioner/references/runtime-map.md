# Runtime & Package Manager Reference

Quick reference for installing software in the devcontainer.
All commands assume the `vscode` user (use `sudo` for system ops).

## Package Managers

### apt (System Packages)

```bash
# Update cache first (required before install)
sudo apt-get update -qq

# Install packages
sudo apt-get install -y -qq <package1> <package2>

# Search for a package
apt-cache search <keyword>

# Check if installed
dpkg -l | grep <package>
```

### pip3 (Python)

```bash
# Install package
pip3 install --break-system-packages -q <package>

# Install from requirements
pip3 install --break-system-packages -q -r requirements.txt

# Install specific version
pip3 install --break-system-packages -q <package>==<version>

# Check if installed
python3 -c "import <module>"
pip3 show <package>
```

**Important:** `--break-system-packages` is required because there is no
virtualenv in this devcontainer. Python 3.12's PEP 668 enforcement rejects
pip installs into the system site-packages without this flag.

### npm (Node.js)

```bash
# Global install
npm install -g <package>

# Project-local install
npm install <package>

# Dev dependency
npm install --save-dev <package>

# Check global
npm list -g <package>

# Check local
npm list <package>
```

### Composer (PHP)

```bash
# Install all deps from lock file
composer install --no-interaction --prefer-dist

# Add new dependency
composer require <vendor/package>

# Add dev dependency
composer require --dev <vendor/package>

# Check installed
composer show <vendor/package>
```

### Bun

```bash
# Install package
bun add <package>

# Global install
bun add -g <package>

# Install from lockfile
bun install

# Dev dependency
bun add -d <package>
```

### Go

```bash
# Install a Go tool
go install <package>@latest

# Example
go install golang.org/x/tools/gopls@latest

# Verify
which <binary>
```

## Pre-Installed PHP Extensions

The following PHP 8.5 extensions are already in the Dockerfile:

```
cli common curl mbstring xml zip intl pgsql redis xdebug bcmath gd
apcu rdkafka amqp mongodb memcached mysql sqlite3 soap imagick
protobuf opentelemetry yaml uuid msgpack lz4 zstd pcov ssh2 http ds
```

To install additional PHP extensions:
```bash
sudo apt-get update -qq && sudo apt-get install -y -qq php8.5-<name>
```

Verify: `php -m | grep -i <name>`

## Pre-Installed Global npm Packages

```
opencode-ai  bun  typescript  ts-node  @fission-ai/openspec  intelephense
```

## Pre-Installed System Tools

```
git  curl  wget  jq  tmux  sox  psql  redis-cli
docker (CLI only, via Docker-in-Docker socket)
```

## Common Python Packages by Agent

### news-maker-agent
```
fastapi uvicorn sqlalchemy alembic psycopg2-binary pydantic
pydantic-settings trafilatura requests apscheduler jinja2
pytest httpx aiofiles python-multipart openai ruff
```

Install all: `pip3 install --break-system-packages -q -r apps/news-maker-agent/requirements.txt`

## Common apt Packages for Development

| Package | Provides |
|---------|----------|
| `postgresql-16-pgvector` | pgvector extension for PostgreSQL |
| `libpq-dev` | PostgreSQL client library headers |
| `build-essential` | gcc, make, etc. |
| `python3-dev` | Python C headers |
| `python3-pip` | pip (if missing) |
| `unzip` | Archive extraction |
| `htop` | Process monitor |
| `strace` | System call tracer |
| `net-tools` | ifconfig, netstat |
| `dnsutils` | dig, nslookup |
| `iproute2` | ip, ss |

## Troubleshooting

### pip3 not found
```bash
sudo apt-get update -qq && sudo apt-get install -y -qq python3-pip
```

### `externally-managed-environment` error from pip
Always use `--break-system-packages` flag. This is expected in Python 3.12+
without a virtualenv.

### PHP extension conflicts
If `apt-get install php8.5-<ext>` fails with dependency issues, try:
```bash
sudo apt-get install -y -qq --fix-broken
sudo apt-get install -y -qq php8.5-<ext>
```

### npm permission errors
Global installs may need:
```bash
sudo npm install -g <package>
```
Or fix permissions:
```bash
sudo chown -R vscode:vscode /usr/lib/node_modules
```
