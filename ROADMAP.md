# 🗺️ AI Community Platform Roadmap

## Vision & Mission

Building a universal, modular ecosystem where autonomous AI agents and classical microservices act as flexible "building blocks" to construct systems of any complexity. Our goal is to democratize AI agent development and provide enterprise-ready infrastructure for the next generation of digital employees.

## ⚠️ Current Status: MVP Development Phase

> **Important:** The platform is actively developing core features. We follow strict OpenSpec proposal workflow with mandatory migrations, TDD, and quality gates. Check [Workflow Guidelines](docs/plans/workflow-guidelines.md) for development practices.

---

## 🎯 Q1 2025 - Core Platform & Infrastructure

### ✅ Completed
- [x] Central Scheduler (`add-central-scheduler`)
- [x] Agent Refactoring to External Repositories (`refactor-agents-into-external-repositories`)
- [x] Marketplace Stale Agent Cleanup (`add-marketplace-stale-agent-cleanup`)
- [x] E2E Test Isolation Refactoring (`refactor-e2e-test-isolation`)
- [x] A2A Terminology Refactoring (`refactor-a2a-terminology`)
- [x] Wiki Agent Foundation (`add-wiki-agent-foundation`)
- [x] Knowledge Store Message Skill (`add-knowledge-store-message-skill`)
- [x] Dev Agent (`add-dev-agent`)
- [x] Core E2E Test Database Isolation (`add-core-e2e-test-database-isolation`)
- [x] Admin Chats Section (`add-admin-chats-section`)
- [x] News Digest Manual Channel Delivery (`update-news-digest-manual-channel-delivery`)
- [x] K3s Storage Architecture (`add-k3s-storage-architecture`) — 28/28 tasks — archived 2026-03-30
- [x] Kubernetes Agent Discovery (`add-kubernetes-agent-discovery`) — 52/52 tasks — archived 2026-03-30
- [x] Complete Admin Agent Registry (`complete-admin-agent-registry`) — 17/17 tasks — archived 2026-03-30
- [x] Fix PSR-4 Multitenant E2E Boot (`fix-psr4-multitenant-e2e-boot`) — 23/23 tasks — archived 2026-03-30
- [x] Fix Remaining E2E Failures (`fix-remaining-e2e-failures`) — 13/13 tasks — archived 2026-03-30
- [x] Refactor Foundry Runtime Entrypoint (`refactor-foundry-runtime-entrypoint`) — 19/19 tasks — archived 2026-03-30
- [x] Validate K3s Local Runtime (`validate-k3s-local-runtime`) — 18/18 tasks — archived 2026-03-30
- [x] Refactor Task-Centric Pipeline State (`refactor-task-centric-pipeline-state`) — 45/46 tasks (STATUS: COMPLETED) — archived 2026-03-30

### 🚧 In Progress
- [ ] **Tenant Management** (`add-tenant-management`) - 0/15 tasks - **P0 CRITICAL**
- [ ] Development Workflow Improvements (`improve-development-workflow`) - 24/26 tasks
- [ ] Close A2A Trace Quality Gates (`close-a2a-trace-quality-gates`) - 0/11 tasks
- [ ] Close Agent Discovery Gaps (`close-agent-discovery-gaps`) - 0/26 tasks
- [ ] Close Dev Reporter Quality Gates (`close-dev-reporter-quality-gates`) - 0/22 tasks
- [ ] Enable K3s Runtime (`enable-k3s-runtime`) - 0/19 tasks
- [ ] Migrate to K3s/Hetzner (`migrate-to-k3s-hetzner`) - 22/42 tasks
- [ ] Workspace Deployment Model (`workspace-deployment-model`) - 16/27 tasks
- [ ] K3s Test Workflow (`add-k3s-test-workflow`) - 0/12 tasks
- [ ] Ultraworks Worktree Isolation (`add-ultraworks-worktree-isolation`) - 0/15 tasks
- [ ] Refactor Unified Pipeline Agents (`refactor-unified-pipeline-agents`) - 0/15 tasks
- [ ] Archive Completed OpenSpec Changes (`archive-completed-openspec-changes`) - 0/26 tasks
- [ ] Sync Roadmap with OpenSpec State (`sync-roadmap-with-openspec-state`) - 0/6 tasks

### 📋 Planned for Q1
- [ ] E2E CUJ Coverage (`add-e2e-cuj-coverage`) - 0/25 tasks
- [ ] E2E Failure Investigation Routing (`add-e2e-failure-investigation-routing`) - 0/13 tasks
- [ ] Pipeline Task Diagnostics (`add-pipeline-task-diagnostics`) - 0 tasks defined
- [ ] Knowledge Base Agent Roadmap (`add-knowledge-base-agent-roadmap`) - 0/20 tasks
- [ ] Cloudflare Turnstile Captcha (`add-cloudflare-turnstile-captcha`) - 0/20 tasks

---

## 🚀 Q2 2025 - Agent Ecosystem & Scale

### Core Platform
- [ ] **Multi-tenant Support** - Physical isolation per tenant - **P1**
- [ ] **Advanced RBAC/ACL System** - Fine-grained permissions - **P2**
- [ ] **Web Admin Panel Full UI** - Complete management interface - **P2**
- [ ] **Agent Marketplace** - Distribution system (`add-agent-marketplace-and-deprovision`) - 0/23 tasks

### Agent Development
- [ ] Agent Projects and Template Sandboxes (`add-agent-projects-and-template-sandboxes`) - 0/17 tasks
- [ ] Platform Coder Agent (`add-platform-coder-agent`) - 0/68 tasks
- [ ] AI News Maker Agent (`add-ai-news-maker-agent`) - 0/42 tasks
- [ ] Agent Discovery Refactoring (`refactor-agent-discovery`) - 0/43 tasks

### Infrastructure
- [ ] A2A Streaming and Push (`add-a2a-streaming-and-push`) - 0/23 tasks
- [ ] Traefik Log Pipeline (`add-traefik-log-pipeline`) - 0/24 tasks

---

## 🔮 Q3 2025 - Intelligence & Integration

### AI Capabilities
- [ ] **LLM Integration** - Multi-provider support for summarization
- [ ] **Vector Search Infrastructure** - Semantic search capabilities
- [ ] **RAG Pipeline** - Knowledge augmentation for agents
- [ ] **Fine-tuning Support** - Custom model training

### Enterprise Features
- [ ] **SSO Integration** - SAML/OIDC support
- [ ] **Audit Logging** - Compliance-ready activity tracking
- [ ] **Usage Analytics** - Detailed metrics and billing
- [ ] **SLA Monitoring** - Service level tracking

---

## 🌟 Q4 2025 - Production Excellence

### Performance & Reliability
- [ ] **Horizontal Scaling** - Auto-scaling for high load
- [ ] **Database Sharding** - Scale beyond single DB
- [ ] **Distributed Caching** - Redis cluster implementation
- [ ] **Circuit Breakers** - Fault tolerance patterns

### Security & Compliance
- [ ] **End-to-End Encryption** - Message security
- [ ] **GDPR Compliance** - Data protection features
- [ ] **SOC2 Certification** - Security audit
- [ ] **Penetration Testing** - Security assessment

---

## 🔧 Technical Debt

### High Priority
- [ ] Upgrade to Symfony 7.1 when stable
- [ ] Improve test coverage to >80%
- [ ] Optimize database queries for scale
- [ ] Add comprehensive API documentation

### Medium Priority
- [ ] Refactor monolithic services to microservices
- [ ] Implement proper event sourcing
- [ ] Add distributed tracing (OpenTelemetry)
- [ ] Migrate from Doctrine ORM to DBAL for performance-critical paths

### Low Priority
- [ ] Code style consistency across all agents
- [ ] Deprecate legacy API endpoints
- [ ] Remove unused dependencies

---

## 🔴 Current Blockers

1. **Telegram Bot Integration** - Critical path for MVP, blocks user testing
2. **Multi-tenancy** - Blocks production deployment for multiple clients
3. **Test Coverage** - Currently below 50%, risks production stability

---

## 📊 Success Metrics

### Q1 2025 Goals
- ✅ 10+ completed OpenSpec proposals
- 🎯 80% test coverage (currently ~45%)
- 🎯 <100ms p95 response time
- 🎯 First pilot customer deployment

### Q2 2025 Goals
- 🎯 100+ active agents in registry
- 🎯 5+ production deployments
- 🎯 99.9% uptime
- 🎯 1000+ GitHub stars

### Year End 2025 Goals
- 🎯 1000+ active deployments
- 🎯 50+ community contributors
- 🎯 Enterprise tier with 10+ customers
- 🎯 $1M ARR

---

## 🤝 How to Update This Roadmap

### When Starting Work
1. Check for conflicts: `openspec list` and `cat ROADMAP.md`
2. Move item from "Planned" to "In Progress"
3. Update task count regularly
4. Commit changes with your work

### When Completing Work
1. Archive OpenSpec proposal: `openspec archive [change-id] --yes`
2. Move item to "Completed" in ROADMAP
3. Update metrics if applicable
4. Commit: `git commit -m "chore: update ROADMAP - completed [feature]"`

### Adding New Items
1. Create OpenSpec proposal first
2. Add to appropriate quarter with priority (P0-P3)
3. Link to OpenSpec change ID
4. Update in next commit

### Priority Levels
- **P0**: Critical - Blocks release/customers
- **P1**: High - Core functionality
- **P2**: Medium - Important features
- **P3**: Low - Nice to have

---

## 📅 Review Schedule

- **Daily**: Check task progress in active items
- **Weekly**: Team review every Monday
- **Monthly**: Priority adjustment and re-planning
- **Quarterly**: Major planning and archiving

---

## 🔗 Important Links

- [OpenSpec Changes](/openspec/changes/)
- [Workflow Guidelines](/docs/WORKFLOW_GUIDELINES.md)
- [Project Documentation](/docs/)
- [AGENTS.md](/openspec/AGENTS.md)
- [Contributing Guide](/CONTRIBUTING.md)

---

*This roadmap follows our strict development workflow. All features require OpenSpec proposals, database migrations, TDD, and passing quality gates. See [Workflow Guidelines](docs/WORKFLOW_GUIDELINES.md) for details.*

*Last updated: March 30, 2026*