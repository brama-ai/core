# Knowledge Base Agent Development Plan

## Overview

This document outlines the development approach for the Knowledge Base Agent, a comprehensive knowledge management system that extracts, structures, and indexes valuable information from community chat messages.

## Architecture

### Core Components

```mermaid
graph TB
    A[Telegram Messages] --> B[Core Platform]
    B --> C[Knowledge API]
    C --> D[RabbitMQ Queue]
    D --> E[Knowledge Worker]
    E --> F[neuron-ai Workflow]
    F --> G[OpenSearch Index]
    G --> H[Web Encyclopedia]
    G --> I[Admin Panel]
    G --> J[A2A API]
```

### Technology Stack

- **Backend**: PHP 8.2 + Symfony 7
- **AI Framework**: neuron-ai (Workflow abstraction)
- **Search Engine**: OpenSearch 2.x with Ukrainian analyzer
- **Message Queue**: RabbitMQ 3.x with dead letter queues
- **Database**: PostgreSQL (metadata and settings)
- **LLM Provider**: LiteLLM proxy (OpenAI, Anthropic, etc.)
- **Frontend**: Twig templates with vanilla JavaScript

## Development Phases

### Phase 1: Infrastructure Setup ✅
- [x] OpenSearch and RabbitMQ services in Docker Compose
- [x] Symfony application scaffold with neuron-ai integration
- [x] Database migrations for settings and chunk tracking
- [x] Basic service configuration and dependency injection

### Phase 2: Core Extraction Pipeline ✅
- [x] neuron-ai workflow with three nodes:
  - AnalyzeMessages: Determine if chunk contains valuable knowledge
  - ExtractKnowledge: Extract structured data (title, body, tags, etc.)
  - EnrichMetadata: Add source links and provenance data
- [x] RabbitMQ publisher and consumer with retry logic
- [x] OpenSearch index management and document indexing
- [x] Embedding generation and hybrid search implementation

### Phase 3: API Layer ✅
- [x] REST API endpoints for CRUD operations
- [x] Hybrid search API with BM25 + semantic similarity
- [x] Knowledge tree aggregation for hierarchical navigation
- [x] A2A integration for platform-wide knowledge access
- [x] OpenAPI specification and documentation

### Phase 4: Web Encyclopedia ✅
- [x] Public wiki interface at `/wiki`
- [x] Tree-based navigation with category counts
- [x] Search functionality with result highlighting
- [x] Entry detail pages with source message links
- [x] Responsive design for mobile access

### Phase 5: Admin Panel ✅
- [x] Knowledge CRUD interface with inline editing
- [x] Settings management (encyclopedia toggle, base instructions)
- [x] Security instructions display (read-only)
- [x] DLQ monitoring and message requeue functionality
- [x] Preview mode for testing extraction without saving

### Phase 6: Production Readiness 🚧
- [x] Rate limiting with token bucket algorithm
- [x] Configurable worker concurrency
- [x] Health check endpoints for monitoring
- [x] Comprehensive unit and integration tests
- [ ] End-to-end tests with Playwright
- [ ] Performance optimization and load testing

## Key Design Decisions

### 1. neuron-ai Framework Choice
**Decision**: Use neuron-ai PHP framework for LLM workflows
**Rationale**: 
- Native PHP integration with Symfony
- Workflow abstraction for complex multi-step processing
- Built-in provider switching and monitoring capabilities
- Avoids Python service dependencies

### 2. OpenSearch over PostgreSQL
**Decision**: Use OpenSearch for knowledge storage and search
**Rationale**:
- Native hybrid search (BM25 + kNN vectors)
- Ukrainian language analyzer support
- Horizontal scaling capabilities
- Better search performance than PostgreSQL full-text

### 3. RabbitMQ for Async Processing
**Decision**: Use RabbitMQ for message queuing
**Rationale**:
- Decouples ingestion from processing
- Built-in retry and dead letter queue support
- Better monitoring and observability than database queues
- Industry standard for reliable message processing

### 4. Chunk-Based Processing
**Decision**: Process messages in time-windowed chunks
**Rationale**:
- Provides context for better knowledge extraction
- Enables deduplication and idempotent processing
- Balances processing efficiency with context preservation
- Supports overlap for conversation continuity

## Testing Strategy

### Unit Tests ✅
- Workflow node testing with mocked LLM responses
- Service layer testing with dependency injection
- Repository testing with test doubles
- Utility function testing for edge cases

### Integration Tests ✅
- Full workflow testing with sample message chunks
- OpenSearch integration with test indices
- RabbitMQ integration with test queues
- Database integration with test transactions

### Functional Tests ✅
- API endpoint testing with HTTP clients
- Authentication and authorization testing
- Error handling and validation testing
- Search functionality with various query types

### End-to-End Tests 🚧
- Web encyclopedia navigation and search
- Admin panel CRUD operations
- Settings management and preview functionality
- Cross-browser compatibility testing

## Deployment Considerations

### Environment Configuration
```env
# Core services
OPENSEARCH_URL=http://opensearch:9200
RABBITMQ_URL=amqp://app:app@rabbitmq:5672
DATABASE_URL=postgresql://user:pass@postgres:5432/knowledge_agent

# LLM integration
LITELLM_BASE_URL=http://litellm:4000
LITELLM_API_KEY=your-api-key
EMBEDDING_MODEL=text-embedding-3-small

# Performance tuning
KNOWLEDGE_WORKER_CONCURRENCY=2
KNOWLEDGE_RATE_LIMIT_MAX_TOKENS=60
KNOWLEDGE_RATE_LIMIT_REFILL_RATE=60
```

### Scaling Considerations
- **Horizontal scaling**: Multiple worker processes across containers
- **Rate limiting**: Shared token bucket across worker instances
- **OpenSearch sharding**: Configure based on knowledge volume
- **RabbitMQ clustering**: For high availability in production

### Monitoring and Observability
- Health check endpoints for service monitoring
- OpenSearch logging with structured data
- RabbitMQ queue depth and processing rate metrics
- LLM usage tracking and cost monitoring

## Security Considerations

### Data Privacy
- Personal data exclusion in extraction prompts
- Source message link validation
- Admin-only access to sensitive operations
- Secure token-based API authentication

### LLM Safety
- Immutable security instructions
- Content filtering and validation
- Rate limiting to prevent abuse
- Audit logging for all LLM interactions

### Infrastructure Security
- Network isolation between services
- Encrypted communication channels
- Regular security updates and patches
- Access control for admin interfaces

## Future Enhancements

### Short Term
- Multi-language support (English, Polish)
- Advanced search filters and faceting
- Knowledge entry versioning and history
- Automated content quality scoring

### Medium Term
- Real-time message processing pipeline
- Machine learning for extraction quality improvement
- Integration with external knowledge bases
- Advanced analytics and reporting dashboard

### Long Term
- Federated search across multiple communities
- AI-powered knowledge recommendations
- Collaborative editing and community curation
- Mobile application for knowledge access

## Success Criteria

### Technical Metrics
- **Processing Latency**: < 30 seconds from message to indexed knowledge
- **Search Performance**: < 200ms P95 for hybrid search queries
- **System Uptime**: > 99.5% availability for encyclopedia and API
- **Error Rate**: < 1% of message chunks fail processing

### Business Metrics
- **Knowledge Quality**: > 80% of extracted entries rated as valuable
- **User Adoption**: > 100 daily active users within 3 months
- **Search Success**: > 70% of searches result in user engagement
- **Content Growth**: > 500 knowledge entries within 6 months

## Risk Mitigation

### Technical Risks
- **LLM Service Outages**: Implement circuit breakers and fallback strategies
- **OpenSearch Performance**: Monitor query performance and optimize indices
- **Message Queue Backlog**: Implement auto-scaling and alerting
- **Data Corruption**: Regular backups and data validation checks

### Business Risks
- **Low Adoption**: User feedback collection and UX improvements
- **Content Quality Issues**: Manual review processes and quality metrics
- **Privacy Concerns**: Clear data handling policies and opt-out mechanisms
- **Cost Overruns**: LLM usage monitoring and budget controls