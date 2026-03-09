"""Crawler adapter: fetches source pages and extracts article candidates."""

import hashlib
import html
import logging
import re
import uuid
from collections import deque
from datetime import datetime, timedelta, timezone
from time import monotonic
from typing import cast
from urllib.parse import urljoin, urlparse

import requests
import trafilatura

from app.config import settings
from app.database import SessionLocal
from app.models.models import AgentSettings, NewsSource, RawNewsItem, SchedulerRun

logger = logging.getLogger(__name__)

CRAWL_TIMEOUT = 20
USER_AGENT = "Mozilla/5.0 (compatible; AICommunityBot/1.0)"
HREF_PATTERN = re.compile(r"""href=["']([^"']+)["']""", re.IGNORECASE)
MAX_LINKS_PER_SOURCE = settings.crawl_max_links_per_source
MAX_DEPTH = settings.crawl_max_depth
MAX_LINKS_PER_DEPTH = settings.crawl_max_links_per_depth
SOURCE_TIMEBOX_SECONDS = settings.crawl_source_timebox_seconds
RUN_TIMEBOX_SECONDS = settings.crawl_run_timebox_seconds
STATIC_EXTENSIONS = {
    ".css",
    ".js",
    ".mjs",
    ".map",
    ".png",
    ".jpg",
    ".jpeg",
    ".gif",
    ".webp",
    ".svg",
    ".ico",
    ".woff",
    ".woff2",
    ".ttf",
    ".otf",
    ".eot",
    ".pdf",
    ".zip",
    ".gz",
    ".mp4",
    ".mp3",
    ".json",
}
STATIC_PATH_MARKERS = ("/assets/", "/static/", "/images/", "/img/", "/css/", "/js/", "/fonts/")
BLOCKED_HOST_MARKERS = ("redditstatic.com", "gstatic.com", "doubleclick.net", "googletagmanager.com")


def _fetch_html(url: str, proxy_url: str | None = None) -> str | None:
    proxies = {"http": proxy_url, "https": proxy_url} if proxy_url else None
    try:
        resp = requests.get(
            url,
            timeout=CRAWL_TIMEOUT,
            headers={"User-Agent": USER_AGENT},
            proxies=proxies,
        )
        resp.raise_for_status()
        content_type = resp.headers.get("content-type", "").lower()
        if "text/html" not in content_type and "application/xhtml+xml" not in content_type:
            logger.debug("Skipping non-HTML response for %s (content_type=%s)", url, content_type or "unknown")
            return None
        logger.debug("Fetched URL %s (status=%s, bytes=%d)", url, resp.status_code, len(resp.text))
        return resp.text
    except Exception as exc:
        logger.warning("Failed to fetch %s: %s", url, exc)
        return None


def _normalized_host(url: str) -> str:
    host = urlparse(url).netloc.lower()
    return host[4:] if host.startswith("www.") else host


def _is_same_site_or_subdomain(link: str, base_url: str) -> bool:
    link_host = _normalized_host(link)
    base_host = _normalized_host(base_url)
    return link_host == base_host or link_host.endswith(f".{base_host}")


def _reject_reason(link: str, base_url: str) -> str | None:
    parsed = urlparse(link)
    host = parsed.netloc.lower()
    path = parsed.path.lower()

    if not _is_same_site_or_subdomain(link, base_url):
        return "offsite"

    if any(marker in host for marker in BLOCKED_HOST_MARKERS):
        return "blocked_host"

    if any(path.endswith(ext) for ext in STATIC_EXTENSIONS):
        return "static_extension"

    if any(marker in path for marker in STATIC_PATH_MARKERS):
        return "static_path"

    if "reddit.com" in host and "/comments/" not in path:
        return "reddit_non_post"

    if path in ("", "/"):
        return "homepage"

    return None


def _extract_article(html: str, url: str) -> dict | None:
    """Use trafilatura to extract article content from HTML."""
    extracted = trafilatura.bare_extraction(
        html,
        url=url,
        include_links=False,
        include_images=False,
        with_metadata=True,
    )
    if not extracted:
        return None

    if isinstance(extracted, dict):
        result = extracted
    elif hasattr(extracted, "as_dict"):
        result = extracted.as_dict()
    else:
        return None

    text = result.get("text", "") or ""
    title = result.get("title", "") or ""
    if len(text) < 100:
        return None

    return {
        "title": title[:512],
        "raw_text": text,
        "excerpt": text[:512],
        "canonical_url": result.get("url") or url,
        "language": result.get("language"),
        "published_at_source": None,
    }


def _dedup_hash(url: str) -> str:
    return hashlib.sha256(url.encode()).hexdigest()


def run_crawl() -> int:
    """Main crawl job: fetch all enabled sources and store raw items."""
    db = SessionLocal()
    run = SchedulerRun(job_name="crawl")
    db.add(run)
    db.commit()

    try:
        existing_run = (
            db.query(SchedulerRun)
            .filter(
                SchedulerRun.job_name == "crawl",
                SchedulerRun.status == "running",
                SchedulerRun.finished_at.is_(None),
                SchedulerRun.id != run.id,
            )
            .first()
        )
        if existing_run:
            logger.warning(
                "Skipping crawl run %s: another crawl run is already running (id=%s, started_at=%s)",
                run.id,
                existing_run.id,
                existing_run.started_at,
            )
            run.status = "failed"
            run.error_message = "Another crawl run is already in progress"
            run.finished_at = datetime.now(timezone.utc)
            db.commit()
            return 0

        settings_row = db.query(AgentSettings).first()
        proxy_url = settings_row.proxy_url if settings_row and settings_row.proxy_enabled else None
        ttl_hours = settings_row.raw_item_ttl_hours if settings_row else 72
        max_depth = max(1, min(2, settings_row.crawl_max_depth)) if settings_row else MAX_DEPTH
        max_links_per_depth = settings_row.crawl_max_links_per_depth if settings_row else MAX_LINKS_PER_DEPTH
        max_links_per_depth = max(1, max_links_per_depth)
        source_timebox_seconds = SOURCE_TIMEBOX_SECONDS
        effective_links_per_depth = max_links_per_depth if max_depth > 1 else MAX_LINKS_PER_SOURCE
        expires_at = datetime.now(timezone.utc) + timedelta(hours=ttl_hours)
        run_deadline = monotonic() + RUN_TIMEBOX_SECONDS

        sources = (
            db.query(NewsSource)
            .filter(NewsSource.enabled == True)  # noqa: E712
            .order_by(NewsSource.crawl_priority.desc())
            .all()
        )
        logger.info(
            (
                "Crawl run started: enabled_sources=%d ttl_hours=%d "
                "max_depth=%d max_links_per_depth=%d source_timebox=%ds run_timebox=%ds"
            ),
            len(sources),
            ttl_hours,
            max_depth,
            effective_links_per_depth,
            source_timebox_seconds,
            RUN_TIMEBOX_SECONDS,
        )

        items_seen = 0
        crawl_run_id = uuid.uuid4()

        for source in sources:
            if monotonic() > run_deadline:
                logger.warning("Stopping crawl early: run timebox (%ds) exceeded", RUN_TIMEBOX_SECONDS)
                break

            logger.info("Crawling source '%s' (%s)", source.name, source.base_url)
            source_html = _fetch_html(source.base_url, proxy_url)
            if not source_html:
                source.last_error_at = datetime.now(timezone.utc)
                db.commit()
                logger.warning("Source '%s' fetch failed", source.name)
                continue

            links, link_stats = cast(
                tuple[list[str], dict[str, int]],
                _extract_links(source_html, source.base_url, with_stats=True),
            )
            links = links[:effective_links_per_depth]
            logger.info(
                (
                    "Source '%s' depth=1 produced %d candidate links "
                    "(href=%d accepted=%d filtered=%d offsite=%d static=%d "
                    "blocked=%d duplicate=%d invalid=%d)"
                ),
                source.name,
                len(links),
                link_stats["href_total"],
                link_stats["accepted"],
                link_stats["filtered"],
                link_stats["offsite"],
                link_stats["static"],
                link_stats["blocked"],
                link_stats["duplicate"],
                link_stats["invalid"],
            )
            source_added = 0
            source_existing = 0
            source_fetch_failed = 0
            source_extract_failed = 0
            source_deadline = monotonic() + source_timebox_seconds
            http_requests_by_depth: dict[int, int] = {0: 1}
            processed_links_by_depth: dict[int, int] = {}
            seen_links: set[str] = set()
            queue: deque[tuple[str, int, str]] = deque()

            for link in links:
                seen_links.add(link)
                queue.append((link, 1, source.base_url))

            while queue:
                link, depth, discovered_from_url = queue.popleft()
                if depth > max_depth:
                    continue

                processed_count = processed_links_by_depth.get(depth, 0)
                if processed_count >= effective_links_per_depth:
                    continue
                processed_links_by_depth[depth] = processed_count + 1

                if monotonic() > source_deadline:
                    logger.warning("Source '%s' stopped by source timebox (%ds)", source.name, source_timebox_seconds)
                    break

                dedup = _dedup_hash(link)
                exists = db.query(RawNewsItem).filter(RawNewsItem.dedup_hash == dedup).first()
                if exists and depth >= max_depth:
                    source_existing += 1
                    continue

                article_html = _fetch_html(link, proxy_url)
                http_requests_by_depth[depth] = http_requests_by_depth.get(depth, 0) + 1
                if not article_html:
                    source_fetch_failed += 1
                    continue

                if exists:
                    source_existing += 1
                else:
                    article = _extract_article(article_html, link)
                    if not article:
                        source_extract_failed += 1
                    else:
                        item = RawNewsItem(
                            source_id=source.id,
                            source_url=link,
                            canonical_url=article["canonical_url"],
                            title=article["title"],
                            raw_text=article["raw_text"],
                            excerpt=article["excerpt"],
                            language=article["language"],
                            published_at_source=article["published_at_source"],
                            dedup_hash=dedup,
                            crawl_depth=depth,
                            discovered_from_url=discovered_from_url,
                            crawl_run_id=crawl_run_id,
                            expires_at=expires_at,
                            status="new",
                        )
                        db.add(item)
                        items_seen += 1
                        source_added += 1

                if depth < max_depth:
                    nested_links, nested_stats = cast(
                        tuple[list[str], dict[str, int]],
                        _extract_links(
                            article_html,
                            link,
                            scope_url=source.base_url,
                            with_stats=True,
                        ),
                    )
                    nested_links = nested_links[:effective_links_per_depth]
                    logger.debug(
                        (
                            "Source '%s' depth=%d parent=%s produced %d candidate links "
                            "(href=%d accepted=%d filtered=%d offsite=%d static=%d blocked=%d duplicate=%d invalid=%d)"
                        ),
                        source.name,
                        depth + 1,
                        link,
                        len(nested_links),
                        nested_stats["href_total"],
                        nested_stats["accepted"],
                        nested_stats["filtered"],
                        nested_stats["offsite"],
                        nested_stats["static"],
                        nested_stats["blocked"],
                        nested_stats["duplicate"],
                        nested_stats["invalid"],
                    )
                    for nested_link in nested_links:
                        if nested_link in seen_links:
                            continue
                        seen_links.add(nested_link)
                        queue.append((nested_link, depth + 1, link))

            source.last_success_at = datetime.now(timezone.utc)
            db.commit()
            logger.info(
                ("Source '%s' done: added=%d existing=%d fetch_failed=%d extract_failed=%d requests_by_depth=%s"),
                source.name,
                source_added,
                source_existing,
                source_fetch_failed,
                source_extract_failed,
                http_requests_by_depth,
            )

        run.items_seen = items_seen
        run.status = "completed"
        run.finished_at = datetime.now(timezone.utc)
        db.commit()
        logger.info("Crawl run complete: %d items seen", items_seen)
        return items_seen

    except Exception as exc:
        logger.exception("Crawl run failed")
        run.status = "failed"
        run.error_message = str(exc)
        run.finished_at = datetime.now(timezone.utc)
        db.commit()
        return 0
    finally:
        db.close()


def _extract_links(
    html: str,
    base_url: str,
    *,
    scope_url: str | None = None,
    with_stats: bool = False,
) -> list[str] | tuple[list[str], dict[str, int]]:
    """Extract article-like links from a source page."""
    scope = scope_url or base_url
    links_from_html, stats = _extract_links_from_html(html, base_url, scope)
    if links_from_html:
        result = links_from_html[:MAX_LINKS_PER_SOURCE]
        stats["accepted"] = len(result)
        return (result, stats) if with_stats else result

    try:
        from trafilatura.spider import focused_crawler

        _, links = focused_crawler(base_url, max_seen_urls=30, max_known_urls=50)
        filtered = []
        for link in list(links):
            reason = _reject_reason(link, scope)
            if reason:
                stats["filtered"] += 1
                if reason == "offsite":
                    stats["offsite"] += 1
                elif reason in ("static_extension", "static_path"):
                    stats["static"] += 1
                elif reason in ("blocked_host", "reddit_non_post"):
                    stats["blocked"] += 1
                continue
            filtered.append(link)

        result = filtered[:MAX_LINKS_PER_SOURCE]
        stats["accepted"] = len(result)
        return (result, stats) if with_stats else result
    except Exception:
        return ([], stats) if with_stats else []


def _extract_links_from_html(page_html: str, page_url: str, scope_url: str) -> tuple[list[str], dict[str, int]]:
    links: list[str] = []
    seen: set[str] = set()
    stats = {
        "href_total": 0,
        "accepted": 0,
        "filtered": 0,
        "offsite": 0,
        "static": 0,
        "blocked": 0,
        "duplicate": 0,
        "invalid": 0,
    }

    for match in HREF_PATTERN.finditer(page_html):
        stats["href_total"] += 1
        href = html.unescape(match.group(1).strip())
        if (
            not href
            or href.startswith("#")
            or href.startswith("javascript:")
            or href.startswith("mailto:")
            or href.startswith("tel:")
        ):
            stats["invalid"] += 1
            continue

        absolute = urljoin(page_url, href).split("#", 1)[0]
        parsed = urlparse(absolute)
        if parsed.scheme not in ("http", "https"):
            stats["invalid"] += 1
            continue

        if absolute in seen:
            stats["duplicate"] += 1
            continue
        seen.add(absolute)

        reason = _reject_reason(absolute, scope_url)
        if reason:
            stats["filtered"] += 1
            if reason == "offsite":
                stats["offsite"] += 1
            elif reason in ("static_extension", "static_path"):
                stats["static"] += 1
            elif reason in ("blocked_host", "reddit_non_post"):
                stats["blocked"] += 1
            continue

        links.append(absolute)
        stats["accepted"] += 1

    return links, stats


def run_cleanup() -> int:
    """Remove expired raw items."""
    db = SessionLocal()
    run = SchedulerRun(job_name="cleanup")
    db.add(run)
    db.commit()

    try:
        now = datetime.now(timezone.utc)
        deleted = (
            db.query(RawNewsItem)
            .filter(RawNewsItem.expires_at < now, RawNewsItem.status.in_(["new", "scored", "discarded", "expired"]))
            .delete(synchronize_session=False)
        )
        db.commit()

        run.items_seen = deleted
        run.status = "completed"
        run.finished_at = datetime.now(timezone.utc)
        db.commit()
        logger.info("Cleanup run complete: %d items removed", deleted)
        return deleted

    except Exception as exc:
        logger.exception("Cleanup run failed")
        run.status = "failed"
        run.error_message = str(exc)
        run.finished_at = datetime.now(timezone.utc)
        db.commit()
        return 0
    finally:
        db.close()
