import logging
import uuid
from typing import Annotated
from urllib.parse import urlparse

from fastapi import APIRouter, Depends, Form, Request
from fastapi.responses import HTMLResponse, RedirectResponse
from sqlalchemy.orm import Session

from app.database import get_db
from app.models.models import CuratedNewsItem, NewsSource, RawNewsItem
from app.templates_config import templates

router = APIRouter(prefix="/admin/sources", tags=["admin-sources"])
logger = logging.getLogger(__name__)


def _is_valid_url(url: str) -> bool:
    try:
        result = urlparse(url)
        return result.scheme in ("http", "https") and bool(result.netloc)
    except Exception:
        return False


@router.get("", response_class=HTMLResponse)
def list_sources(request: Request, db: Annotated[Session, Depends(get_db)]):
    sources = db.query(NewsSource).order_by(NewsSource.crawl_priority.desc(), NewsSource.name).all()
    return templates.TemplateResponse(request, "admin/sources.html", {"sources": sources})


@router.post("/create")
def create_source(
    request: Request,
    db: Annotated[Session, Depends(get_db)],
    name: str = Form(...),
    base_url: str = Form(...),
    topic_scope: str = Form("ai"),
    crawl_priority: int = Form(5),
):
    if not _is_valid_url(base_url):
        sources = db.query(NewsSource).order_by(NewsSource.crawl_priority.desc(), NewsSource.name).all()
        return templates.TemplateResponse(
            request,
            "admin/sources.html",
            {"sources": sources, "error": "URL має починатися з http:// або https://"},
            status_code=400,
        )

    source = NewsSource(
        name=name,
        base_url=base_url,
        topic_scope=topic_scope,
        crawl_priority=crawl_priority,
    )
    db.add(source)
    db.commit()
    return RedirectResponse("/admin/sources", status_code=303)


@router.post("/{source_id}/toggle")
def toggle_source(source_id: str, db: Annotated[Session, Depends(get_db)]):
    source = db.query(NewsSource).filter(NewsSource.id == uuid.UUID(source_id)).first()
    if source:
        source.enabled = not source.enabled
        db.commit()
    return RedirectResponse("/admin/sources", status_code=303)


@router.post("/{source_id}/delete")
def delete_source(source_id: str, db: Annotated[Session, Depends(get_db)]):
    source = db.query(NewsSource).filter(NewsSource.id == uuid.UUID(source_id)).first()
    if source:
        raw_ids = [item_id for (item_id,) in db.query(RawNewsItem.id).filter(RawNewsItem.source_id == source.id).all()]
        curated_deleted = 0
        if raw_ids:
            curated_deleted = (
                db.query(CuratedNewsItem)
                .filter(CuratedNewsItem.raw_news_item_id.in_(raw_ids))
                .delete(synchronize_session=False)
            )
        raw_deleted = db.query(RawNewsItem).filter(RawNewsItem.source_id == source.id).delete(synchronize_session=False)
        db.delete(source)
        db.commit()
        logger.info(
            "Deleted source '%s' (%s): raw_deleted=%d curated_deleted=%d",
            source.name,
            source.id,
            raw_deleted,
            curated_deleted,
        )
    return RedirectResponse("/admin/sources", status_code=303)
