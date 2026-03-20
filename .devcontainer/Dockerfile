FROM mcr.microsoft.com/devcontainers/base:ubuntu

# Ensure /tmp is writable
RUN chmod 1777 /tmp

ENV DEBIAN_FRONTEND=noninteractive
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

# Install Docker CLI for Docker-in-Docker
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       ca-certificates curl gnupg lsb-release \
    && install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
       $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null \
    && apt-get update \
    && apt-get install -y --no-install-recommends docker-ce-cli docker-compose-plugin \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Node.js LTS
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# Install PHP 8.5 from ondrej/php PPA
RUN apt-get update \
    && apt-get install -y --no-install-recommends software-properties-common \
    && add-apt-repository ppa:ondrej/php \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
       php8.5-cli php8.5-common php8.5-curl php8.5-mbstring php8.5-xml \
       php8.5-zip php8.5-intl php8.5-pgsql php8.5-redis \
       php8.5-xdebug php8.5-bcmath php8.5-gd \
       php8.5-apcu php8.5-rdkafka php8.5-amqp php8.5-mongodb \
       php8.5-memcached php8.5-mysql php8.5-sqlite3 php8.5-soap \
       php8.5-imagick php8.5-protobuf php8.5-opentelemetry \
       php8.5-yaml php8.5-uuid php8.5-msgpack \
       php8.5-lz4 php8.5-zstd php8.5-pcov php8.5-ssh2 php8.5-http php8.5-ds \
       sox \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PostgreSQL client, Redis CLI, tmux
RUN apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-16 redis-tools tmux \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# Install Go dynamically based on architecture
RUN ARCH=$(dpkg --print-architecture) && \
    if [ "$ARCH" = "amd64" ]; then GOARCH="amd64"; else GOARCH="arm64"; fi && \
    curl -fsSL "https://go.dev/dl/go1.24.1.linux-${GOARCH}.tar.gz" | tar -C /usr/local -xzf -
ENV PATH="/usr/local/go/bin:${PATH}"

# Install global npm tools
RUN npm install -g opencode-ai bun typescript ts-node @fission-ai/openspec intelephense @anthropic-ai/claude-code

# Preinstall Playwright browser dependencies and binaries so they cache in the image.
COPY tests/e2e/package.json tests/e2e/package-lock.json /tmp/tests-e2e/
RUN cd /tmp/tests-e2e \
    && npm ci \
    && npx playwright install --with-deps \
    && rm -rf /tmp/tests-e2e

# Ensure Claude config dir exists with correct ownership (volume mounts as root otherwise)
RUN mkdir -p /home/vscode/.claude && chown vscode:vscode /home/vscode/.claude \
    && mkdir -p "${PLAYWRIGHT_BROWSERS_PATH}" \
    && chown -R vscode:vscode "${PLAYWRIGHT_BROWSERS_PATH}"
