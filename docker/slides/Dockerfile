# Build stage
FROM node:20-slim AS build

WORKDIR /app

# Install dependencies first for better caching
COPY slides/package.json slides/package-lock.json* ./
RUN npm install

# Copy the rest of the slides files
COPY slides/ ./

# Build all .md files (excluding README.md)
# Each slide deck gets its own subdirectory: /<name>/
# Note: slidev clears dist/ on each run, so we build each to a unique temp dir first
RUN set -ex; \
    # Build root .md files first with unique output dirs
    for file in *.md; do \
        if [ "$file" != "README.md" ]; then \
            name="${file%.md}"; \
            echo "Building slide: $name from $file"; \
            npx slidev build "$file" --base "/$name/" --out "/tmp/slides/$name"; \
        fi; \
    done; \
    # Build pages/*.md files
    if [ -d "pages" ]; then \
        for file in pages/*.md; do \
            if [ -f "$file" ]; then \
                name="$(basename "$file" .md)"; \
                echo "Building slide: $name from $file"; \
                npx slidev build "$file" --base "/$name/" --out "/tmp/slides/$name"; \
            fi; \
        done; \
    fi; \
    # Copy all slides to final dist location
    mkdir -p /app/dist; \
    cp -r /tmp/slides/* /app/dist/ 2>/dev/null || true; \
    ls -la /app/dist/

# Final stage: Serve with Nginx
FROM nginx:stable-alpine

# Each slide deck is served at /<name>/ on the subdomain
COPY --from=build /app/dist /usr/share/nginx/html

# Expose Nginx port
EXPOSE 80
