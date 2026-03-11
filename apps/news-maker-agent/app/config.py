from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    database_url: str = "postgresql://app:app@postgres:5432/ai_news_maker"
    litellm_base_url: str = "http://litellm:4000"
    litellm_api_key: str = "dev-key"
    ranker_model: str = "minimax/minimax-m2.5"
    rewriter_model: str = "minimax/minimax-m2.5"
    platform_core_url: str = "http://core"
    app_internal_token: str = "dev-internal-token"
    openclaw_gateway_token: str = ""
    opensearch_url: str = "http://opensearch:9200"
    admin_public_url: str = "http://localhost:8084/admin/sources"
    enable_test_endpoints: bool = False
    crawl_max_links_per_source: int = 20
    crawl_max_depth: int = 1
    crawl_max_links_per_depth: int = 10
    crawl_source_timebox_seconds: int = 240
    crawl_run_timebox_seconds: int = 900

    model_config = {"env_file": ".env"}


settings = Settings()
