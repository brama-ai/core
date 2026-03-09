import uuid

from app.database import SessionLocal
from app.models.models import AgentSettings, NewsSource, RawNewsItem
from app.services import crawler
from app.services.crawler import _extract_article, _extract_links


def _html_with_links(*hrefs: str) -> str:
    links_html = "".join(f'<a href="{href}">link</a>' for href in hrefs)
    return f"<html><body>{links_html}</body></html>"


def _ensure_settings(max_depth: int, max_links_per_depth: int) -> None:
    with SessionLocal() as db:
        settings_row = db.query(AgentSettings).first()
        if not settings_row:
            settings_row = AgentSettings()
            db.add(settings_row)
            db.flush()

        settings_row.crawl_max_depth = max_depth
        settings_row.crawl_max_links_per_depth = max_links_per_depth
        settings_row.raw_item_ttl_hours = 72
        settings_row.proxy_enabled = False
        db.commit()


def _valid_article(url: str) -> dict[str, str | None]:
    return {
        "title": f"Title for {url}",
        "raw_text": "x" * 160,
        "excerpt": "x" * 120,
        "canonical_url": url,
        "language": "en",
        "published_at_source": None,
    }


def _reset_source(base_url: str) -> None:
    with SessionLocal() as db:
        source_ids = [row.id for row in db.query(NewsSource.id).filter(NewsSource.base_url == base_url).all()]
        if source_ids:
            db.query(RawNewsItem).filter(RawNewsItem.source_id.in_(source_ids)).delete(synchronize_session=False)
            db.query(NewsSource).filter(NewsSource.id.in_(source_ids)).delete(synchronize_session=False)
            db.commit()


def test_extract_article_uses_trafilatura_bare_extraction() -> None:
    html = (
        "<html><head><title>Test article</title></head>"
        "<body><article><h1>Test article</h1>"
        "<p>This is a long enough article body for extraction. "
        "It contains multiple sentences so text length clearly exceeds one hundred characters. "
        "The crawler should parse this content successfully.</p>"
        "</article></body></html>"
    )

    article = _extract_article(html, "https://example.org/article")

    assert article is not None
    assert "title" in article
    assert len(article["raw_text"]) >= 100


def test_extract_links_falls_back_to_html_anchors() -> None:
    source_html = (
        "<html><body>"
        '<a href="/post-1">post one</a>'
        '<a href="https://example.org/post-2">post two</a>'
        '<a href="#ignored">anchor</a>'
        "</body></html>"
    )

    links = _extract_links(source_html, "https://example.org/news")

    assert "https://example.org/post-1" in links
    assert "https://example.org/post-2" in links


def test_extract_links_filters_static_and_offsite_urls() -> None:
    source_html = (
        "<html><body>"
        '<a href="/article/one">article one</a>'
        '<a href="/assets/app.css">css asset</a>'
        '<a href="https://cdn.example.net/file.js">external js</a>'
        '<a href="https://example.org/">home</a>'
        "</body></html>"
    )

    links = _extract_links(source_html, "https://example.org/news")

    assert links == ["https://example.org/article/one"]


def test_extract_links_keeps_only_reddit_post_links() -> None:
    source_html = (
        "<html><body>"
        '<a href="/r/ClaudeAI/">subreddit root</a>'
        '<a href="/r/ClaudeAI/comments/abc123/good_post/">post</a>'
        '<a href="https://www.redditstatic.com/style.css">static css</a>'
        "</body></html>"
    )

    links = _extract_links(source_html, "https://www.reddit.com/r/ClaudeAI/")

    assert links == ["https://www.reddit.com/r/ClaudeAI/comments/abc123/good_post/"]


def test_run_crawl_recurses_two_levels_and_deduplicates(monkeypatch) -> None:
    source_name = f"Crawler Test Deep {uuid.uuid4().hex[:8]}"
    base_url = "https://deep.example.com"
    page_1 = f"{base_url}/article-1"
    page_2 = f"{base_url}/article-2"
    shared_page = f"{base_url}/shared-article"

    _ensure_settings(max_depth=2, max_links_per_depth=10)
    _reset_source(base_url)

    with SessionLocal() as db:
        source = NewsSource(name=source_name, base_url=base_url, topic_scope="ai", enabled=True)
        db.add(source)
        db.commit()
        source_id = source.id

    html_by_url = {
        base_url: _html_with_links("/article-1", "/article-2"),
        page_1: _html_with_links("/shared-article"),
        page_2: _html_with_links("/shared-article"),
        shared_page: _html_with_links(),
    }

    monkeypatch.setattr(crawler, "_fetch_html", lambda url, proxy_url=None: html_by_url.get(url))
    monkeypatch.setattr(crawler, "_extract_article", lambda html, url: _valid_article(url))

    crawler.run_crawl()

    with SessionLocal() as db:
        rows = db.query(RawNewsItem).filter(RawNewsItem.source_id == source_id).all()

    by_url = {row.source_url: row for row in rows}
    assert set(by_url) == {page_1, page_2, shared_page}
    assert by_url[page_1].crawl_depth == 1
    assert by_url[page_2].crawl_depth == 1
    assert by_url[shared_page].crawl_depth == 2
    assert by_url[page_1].discovered_from_url == base_url
    assert by_url[page_2].discovered_from_url == base_url
    assert by_url[shared_page].discovered_from_url == page_1


def test_run_crawl_respects_max_depth_does_not_visit_level_three(monkeypatch) -> None:
    source_name = f"Crawler Test Depth Limit {uuid.uuid4().hex[:8]}"
    base_url = "https://depth.example.com"
    page_1 = f"{base_url}/l1"
    page_2 = f"{base_url}/l2"
    page_3 = f"{base_url}/l3"

    _ensure_settings(max_depth=2, max_links_per_depth=10)
    _reset_source(base_url)

    with SessionLocal() as db:
        source = NewsSource(name=source_name, base_url=base_url, topic_scope="ai", enabled=True)
        db.add(source)
        db.commit()
        source_id = source.id

    html_by_url = {
        base_url: _html_with_links("/l1"),
        page_1: _html_with_links("/l2"),
        page_2: _html_with_links("/l3"),
        page_3: _html_with_links(),
    }
    fetched_urls: list[str] = []

    def _fetch(url: str, proxy_url: str | None = None) -> str | None:
        fetched_urls.append(url)
        return html_by_url.get(url)

    monkeypatch.setattr(crawler, "_fetch_html", _fetch)
    monkeypatch.setattr(crawler, "_extract_article", lambda html, url: _valid_article(url))

    crawler.run_crawl()

    with SessionLocal() as db:
        rows = db.query(RawNewsItem).filter(RawNewsItem.source_id == source_id).all()

    crawled_urls = {row.source_url for row in rows}
    assert crawled_urls == {page_1, page_2}
    assert page_3 not in fetched_urls


def test_run_crawl_keeps_domain_scope_for_nested_links(monkeypatch) -> None:
    source_name = f"Crawler Test Domain Scope {uuid.uuid4().hex[:8]}"
    base_url = "https://scope.example.com"
    page_1 = f"{base_url}/first"
    page_2 = f"{base_url}/second"
    offsite_page = "https://outside.example.net/offsite"

    _ensure_settings(max_depth=2, max_links_per_depth=10)
    _reset_source(base_url)

    with SessionLocal() as db:
        source = NewsSource(name=source_name, base_url=base_url, topic_scope="ai", enabled=True)
        db.add(source)
        db.commit()
        source_id = source.id

    html_by_url = {
        base_url: _html_with_links("/first"),
        page_1: _html_with_links("/second", offsite_page),
        page_2: _html_with_links(),
        offsite_page: _html_with_links(),
    }
    fetched_urls: list[str] = []

    def _fetch(url: str, proxy_url: str | None = None) -> str | None:
        fetched_urls.append(url)
        return html_by_url.get(url)

    monkeypatch.setattr(crawler, "_fetch_html", _fetch)
    monkeypatch.setattr(crawler, "_extract_article", lambda html, url: _valid_article(url))

    crawler.run_crawl()

    with SessionLocal() as db:
        rows = db.query(RawNewsItem).filter(RawNewsItem.source_id == source_id).all()

    crawled_urls = {row.source_url for row in rows}
    assert crawled_urls == {page_1, page_2}
    assert offsite_page not in crawled_urls
    assert offsite_page not in fetched_urls
