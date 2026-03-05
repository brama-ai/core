-- Create dedicated database for LiteLLM UI/auth metadata
SELECT 'CREATE DATABASE litellm'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'litellm')\gexec

GRANT ALL PRIVILEGES ON DATABASE litellm TO app;
