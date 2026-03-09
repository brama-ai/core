from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse

router = APIRouter(prefix="/__e2e", tags=["e2e"])

LONG_TEXT = (
    "AI systems continue evolving rapidly across research and product engineering. "
    "Teams now rely on retrieval, orchestration, and evaluation loops as the default "
    "delivery model for production assistants. This mock article is intentionally long "
    "to satisfy extraction heuristics and validate end-to-end parser behavior in tests. "
    "Reliable fixtures reduce flaky tests and remove dependency on external websites. "
    "The platform should be able to fetch this page, extract title and text, and store "
    "a raw item during crawl runs triggered from admin controls."
)


@router.get("/mock-source", response_class=HTMLResponse)
def mock_source(request: Request) -> str:
    article_url = str(request.url_for("mock_article"))
    return (
        "<html><head><title>Mock Source</title></head>"
        "<body>"
        "<h1>Mock source feed</h1>"
        f'<a href="{article_url}">Open mock article</a>'
        "</body></html>"
    )


@router.get("/mock-article", response_class=HTMLResponse, name="mock_article")
def mock_article() -> str:
    return (
        "<html><head><title>Mock AI Article</title></head>"
        "<body>"
        "<article>"
        "<h1>Mock AI Article</h1>"
        f"<p>{LONG_TEXT}</p>"
        "</article>"
        "</body></html>"
    )
