-- Create production databases for all services.
-- ai_community_platform is created automatically by POSTGRES_DB env var.

-- Knowledge Agent
SELECT 'CREATE DATABASE knowledge_agent OWNER knowledge_agent'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'knowledge_agent')\gexec

GRANT ALL PRIVILEGES ON DATABASE knowledge_agent TO knowledge_agent;

-- News-Maker Agent
SELECT 'CREATE DATABASE news_maker_agent OWNER news_maker_agent'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'news_maker_agent')\gexec

GRANT ALL PRIVILEGES ON DATABASE news_maker_agent TO news_maker_agent;

-- Dev Reporter Agent
SELECT 'CREATE DATABASE dev_reporter_agent OWNER dev_reporter_agent'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'dev_reporter_agent')\gexec

GRANT ALL PRIVILEGES ON DATABASE dev_reporter_agent TO dev_reporter_agent;

-- Dev Agent
SELECT 'CREATE DATABASE dev_agent OWNER dev_agent'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'dev_agent')\gexec

GRANT ALL PRIVILEGES ON DATABASE dev_agent TO dev_agent;

-- LiteLLM
SELECT 'CREATE DATABASE litellm'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'litellm')\gexec

GRANT ALL PRIVILEGES ON DATABASE litellm TO app;
