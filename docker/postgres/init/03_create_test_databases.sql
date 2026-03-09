-- Create test databases for E2E isolation.
-- Convention: {name}_test suffix for all test databases.

-- Core E2E
SELECT 'CREATE DATABASE ai_community_platform_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'ai_community_platform_test')\gexec

GRANT ALL PRIVILEGES ON DATABASE ai_community_platform_test TO app;

-- Knowledge Agent E2E
SELECT 'CREATE DATABASE knowledge_agent_test OWNER knowledge_agent'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'knowledge_agent_test')\gexec

GRANT ALL PRIVILEGES ON DATABASE knowledge_agent_test TO knowledge_agent;

-- News-Maker Agent E2E
SELECT 'CREATE DATABASE news_maker_agent_test OWNER news_maker_agent'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'news_maker_agent_test')\gexec

GRANT ALL PRIVILEGES ON DATABASE news_maker_agent_test TO news_maker_agent;

-- Dev Reporter Agent E2E
SELECT 'CREATE DATABASE dev_reporter_agent_test OWNER dev_reporter_agent'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'dev_reporter_agent_test')\gexec

GRANT ALL PRIVILEGES ON DATABASE dev_reporter_agent_test TO dev_reporter_agent;

-- Dev Agent E2E
SELECT 'CREATE DATABASE dev_agent_test OWNER dev_agent'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'dev_agent_test')\gexec

GRANT ALL PRIVILEGES ON DATABASE dev_agent_test TO dev_agent;
