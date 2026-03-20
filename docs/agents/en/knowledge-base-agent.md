# PRD: Knowledge Base Agent

## 1. Summary

The Knowledge Base Agent is a comprehensive knowledge management system that automatically extracts, structures, and indexes valuable information from community chat messages. It provides semantic search capabilities, a public web encyclopedia, and administrative tools for knowledge curation.

## 2. Goal

Transform scattered community knowledge from chat conversations into a searchable, structured knowledge base that serves as a permanent reference for the Ukrainian tech community.

## 3. Users and Jobs-to-be-Done

### Community Members
- **Find answers quickly**: Search for technical solutions, best practices, and community decisions
- **Browse knowledge**: Navigate through categorized knowledge via the web encyclopedia
- **Access source context**: Follow links back to original chat messages for full context

### Community Moderators/Admins
- **Curate knowledge**: Review, edit, and organize extracted knowledge entries
- **Configure extraction**: Adjust agent instructions and security settings
- **Monitor processing**: Track extraction pipeline health and handle failed messages

### Platform Integrators
- **Query knowledge**: Use A2A API to search knowledge from other platform components
- **Trigger extraction**: Submit message batches for knowledge extraction
- **Access structured data**: Retrieve knowledge entries via REST API

## 4. Scope

### In Scope
- Automatic knowledge extraction from Telegram message batches
- Hybrid search (BM25 + semantic vector search) in Ukrainian language
- Public web encyclopedia with hierarchical navigation
- Admin panel for knowledge management and agent configuration
- A2A integration for platform-wide knowledge access
- Rate limiting and fault-tolerant processing pipeline
- Dead letter queue monitoring and recovery

### Out of Scope
- Real-time message processing (batch-based only)
- Multi-language support (Ukrainian only in MVP)
- Complex user permissions (admin/public only)
- Automatic content moderation (manual review required)
- Integration with external knowledge bases

## 5. Inputs

### Message Batches
- **Format**: JSON array of message objects
- **Fields**: `text`, `from`/`username`, `message_id`, `chat_id`, `timestamp`
- **Chunking**: 15-minute time windows, max 50 messages, 5-message overlap
- **Source**: Telegram chat history via platform core

### Admin Configuration
- **Base Instructions**: Custom system prompt for knowledge extraction
- **Encyclopedia Toggle**: Enable/disable public web access
- **Security Instructions**: Immutable safety guidelines (read-only)

## 6. Outputs

### Knowledge Entries
- **Title**: Concise Ukrainian-language summary (5-10 words)
- **Body**: Structured Markdown content with proper formatting
- **Tags**: Relevant keywords for discovery
- **Category**: Technology, Business, Community, Events, Resources, Other
- **Tree Path**: Hierarchical classification (e.g., "Technology/PHP/Symfony")
- **Metadata**: Source message links, creation timestamp, author

### Search Results
- **Hybrid Scoring**: Combined BM25 and semantic similarity
- **Relevance Ranking**: Normalized scores with configurable weights
- **Source Attribution**: Direct links to original Telegram messages

## 7. UX / Commands

### Web Encyclopedia (`/wiki`)
- **Tree Navigation**: Left sidebar with expandable categories
- **Search Interface**: Top search bar with real-time results
- **Entry Display**: Markdown rendering with source links
- **Responsive Design**: Mobile-friendly layout

### Admin Panel (`/admin/knowledge`)
- **Knowledge CRUD**: Create, edit, delete knowledge entries
- **Settings Management**: Toggle encyclopedia, edit instructions
- **DLQ Monitoring**: View and requeue failed messages
- **Preview Mode**: Test extraction without saving

### A2A API Integration
```json
{
  "intent": "search_knowledge",
  "payload": {"query": "Symfony CORS налаштування", "limit": 5}
}
```

## 8. Data Model Usage

### Reads
- **agent_settings**: Base instructions, encyclopedia toggle
- **processed_chunks**: Deduplication and retry tracking
- **rate_limiter_buckets**: Token bucket state for rate limiting

### Writes
- **OpenSearch knowledge_entries_v1**: Indexed knowledge with embeddings
- **processed_chunks**: Processing status and attempt counts
- **agent_settings**: Admin configuration updates

## 9. Rules / Heuristics

### Knowledge Extraction
- **Valuable Content**: Technical solutions, decisions, best practices, useful resources
- **Skip Content**: Casual conversation, greetings, off-topic discussions
- **Language**: All extracted content must be in Ukrainian
- **Privacy**: Exclude personal data (emails, phones, addresses)

### Processing Pipeline
- **Rate Limiting**: 60 LLM calls per minute (configurable)
- **Retry Logic**: Max 3 attempts before dead letter queue
- **Concurrency**: Configurable worker processes (default: 2)
- **Deduplication**: SHA256 hash of sorted message IDs

### Search Behavior
- **Hybrid Weights**: BM25 (40%) + Semantic (60%)
- **Ukrainian Analysis**: Custom OpenSearch analyzer for Ukrainian text
- **Result Limits**: Default 20 results, max 100 per query

## 10. Failure Modes

### LLM Service Unavailable
- **Detection**: HTTP timeouts or 5xx responses from LiteLLM
- **Mitigation**: Message requeuing with exponential backoff
- **Recovery**: Automatic retry when service restored

### OpenSearch Connectivity Issues
- **Detection**: Connection failures or index unavailable
- **Mitigation**: Health check endpoints report degraded status
- **Recovery**: Manual intervention required for index recreation

### Rate Limit Exceeded
- **Detection**: Token bucket exhaustion
- **Mitigation**: Automatic waiting with exponential backoff
- **Recovery**: Processing resumes when tokens available

### Dead Letter Queue Accumulation
- **Detection**: DLQ message count monitoring
- **Mitigation**: Admin alerts and manual requeue capability
- **Recovery**: Fix underlying issues and reprocess messages

## 11. Success Metrics

### Knowledge Quality
- **Extraction Rate**: % of message batches yielding valuable knowledge
- **Search Relevance**: User engagement with search results
- **Content Accuracy**: Manual review scores for extracted knowledge

### System Performance
- **Processing Latency**: Time from message batch to indexed knowledge
- **Search Response Time**: P95 latency for hybrid search queries
- **Uptime**: Encyclopedia and API availability percentage

### User Adoption
- **Encyclopedia Usage**: Daily active users and page views
- **Search Volume**: Queries per day and unique search terms
- **A2A Integration**: API calls from other platform components

### Operational Health
- **Processing Success Rate**: % of chunks processed without errors
- **DLQ Size**: Number of messages requiring manual intervention
- **Rate Limit Efficiency**: Token utilization and wait times