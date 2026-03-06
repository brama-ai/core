from fastapi import APIRouter

router = APIRouter()


@router.get("/api/v1/manifest")
def get_manifest() -> dict:
    return {
        "name": "news-maker-agent",
        "version": "0.1.0",
        "description": "AI-powered news curation and publishing",
        "url": "http://news-maker-agent:8000/api/v1/a2a",
        "provider": {
            "organization": "AI Community Platform",
            "url": "https://github.com/nmdimas/ai-community-platform",
        },
        "capabilities": {"streaming": False, "pushNotifications": False},
        "defaultInputModes": ["text"],
        "defaultOutputModes": ["text"],
        "skills": [
            {
                "id": "news.publish",
                "name": "News Publish",
                "description": "Publish curated news content",
                "tags": ["news", "publish"],
            },
            {
                "id": "news.curate",
                "name": "News Curate",
                "description": "Curate and summarize news articles",
                "tags": ["news", "curation"],
            },
        ],
        "health_url": "http://news-maker-agent:8000/health",
    }
