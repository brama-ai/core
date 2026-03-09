# Slidev Presentations

## Available Slides

| File | URL | Description |
|------|-----|-------------|
| `main.md` | `/slides/slide/main/` | Main platform presentation |
| `pages/adr.md` | `/slides/slide/adr/` | Architecture Decision Records |
| `pages/hld.md` | `/slides/slide/hld/` | High-Level Design |

## Development

```bash
cd slides
pnpm install
pnpm dev
# visit http://localhost:3030
```

## Build for Production

```bash
# Slides are built automatically via docker/slides/Dockerfile
# Each .md file becomes /slides/slide/<name>/
```

Learn more about Slidev at [the documentation](https://sli.dev/).
