# Build stage
FROM node:20-slim AS build

WORKDIR /app

# Install dependencies first for better caching
# We need to copy from the project root's context
COPY slides/package.json slides/package-lock.json* ./
RUN npm install

# Copy the rest of the slides files
COPY slides/ ./

# Build all .md files (excluding README.md)
# Each slide will be built with its own base path to support multiple slides under /slides/slide/<name>
RUN set -ex; \
    mkdir -p dist; \
    for file in *.md; do \
        if [ "$file" != "README.md" ]; then \
            name="${file%.md}"; \
            echo "Building slide: $name from $file"; \
            npx slidev build "$file" --base "/slides/slide/$name/" --out "dist/$name"; \
        fi; \
    done

# Final stage: Serve with Nginx
FROM nginx:stable-alpine

# Copy all built slides to the specific path Traefik will route to
# We put them in /usr/share/nginx/html/slides/slide/ so they are served correctly
COPY --from=build /app/dist /usr/share/nginx/html/slides/slide

# Expose Nginx port
EXPOSE 80

# No special CMD needed, Nginx starts by default
