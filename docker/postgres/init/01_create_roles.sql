-- Create dedicated roles for agents that own their own databases.
-- The default 'app' role is created by POSTGRES_USER and owns ai_community_platform + litellm.

DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'knowledge_agent') THEN
        CREATE ROLE knowledge_agent WITH LOGIN PASSWORD 'knowledge_agent';
    END IF;

    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'news_maker_agent') THEN
        CREATE ROLE news_maker_agent WITH LOGIN PASSWORD 'news_maker_agent';
    END IF;

    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'dev_reporter_agent') THEN
        CREATE ROLE dev_reporter_agent WITH LOGIN PASSWORD 'dev_reporter_agent';
    END IF;

    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'dev_agent') THEN
        CREATE ROLE dev_agent WITH LOGIN PASSWORD 'dev_agent';
    END IF;
END
$$;
