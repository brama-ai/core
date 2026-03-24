## 1. Foundation
- [ ] 1.1 Define the `Agent Project` data model and admin/domain boundaries in `apps/brama-core`
- [ ] 1.2 Define repository metadata fields for private GitHub, GitLab.com, and self-hosted GitLab-like remotes
- [ ] 1.3 Define secret/credential reference handling for clone, fetch, push, tag, and deploy flows
- [ ] 1.4 Define checkout/update contract into `projects/<project-slug>/`

## 2. Sandbox Profiles
- [ ] 2.1 Define the sandbox selection contract: `template`, `custom_image`, `compose_service`
- [ ] 2.2 Create the `php-symfony-agent` template from the current `hello-agent` stack
- [ ] 2.3 Create the `python-fastapi-agent` template from the current `news-maker-agent` stack
- [ ] 2.4 Create the `node-web-agent` template from the current `wiki-agent` stack
- [ ] 2.5 Define release/deploy sandbox isolation rules separate from normal coding stages

## 3. Migration Stages
- [ ] 3.1 Stage 1: introduce `Agent Project` as the new managed-agent flow while keeping existing bundled agents operational during transition
- [ ] 3.2 Stage 2: extract `hello-agent` into `https://github.com/nmdimas/a2a-hello-agent.git` and validate the PHP template
- [ ] 3.3 Stage 3: extract `news-maker-agent` into `https://github.com/nmdimas/a2a-news-maker-agent.git` and validate the Python template
- [ ] 3.4 Stage 4: extract `wiki-agent` into `https://github.com/nmdimas/a2a-wiki-agent.git` and validate the Node template

## 4. Documentation
- [ ] 4.1 Update external-agent onboarding docs to describe `Agent Project` records and remote-repo-only managed flows
- [ ] 4.2 Add template documentation for PHP/Symfony, Python/FastAPI, and Node/Web agent sandboxes
- [ ] 4.3 Document the migration sequence and rollback policy for bundled agent extraction

## 5. Validation
- [ ] 5.1 Run `openspec validate add-agent-projects-and-template-sandboxes --strict`
