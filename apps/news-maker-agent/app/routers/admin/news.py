import logging
import uuid
from typing import Annotated

from fastapi import APIRouter, Depends, Form, Request
from fastapi.responses import HTMLResponse, RedirectResponse
from sqlalchemy.orm import Session

from app.database import get_db
from app.models.models import CuratedNewsItem
from app.templates_config import templates

router = APIRouter(prefix="/admin/news", tags=["admin-news"])
logger = logging.getLogger(__name__)

ALLOWED_STATUSES = {"ready", "moderated", "deleted", "rejected", "duplicate"}
TERMINAL_STATUSES = {"published"}
PAGE_SIZE = 20


@router.get("", response_class=HTMLResponse)
def list_news(
    request: Request,
    db: Annotated[Session, Depends(get_db)],
    status: str = "",
    page: int = 1,
):
    query = db.query(CuratedNewsItem)
    if status:
        query = query.filter(CuratedNewsItem.status == status)
    total = query.count()
    items = query.order_by(CuratedNewsItem.created_at.desc()).offset((page - 1) * PAGE_SIZE).limit(PAGE_SIZE).all()
    total_pages = max(1, (total + PAGE_SIZE - 1) // PAGE_SIZE)
    return templates.TemplateResponse(
        request,
        "admin/news.html",
        {
            "items": items,
            "status_filter": status,
            "page": page,
            "total_pages": total_pages,
            "total": total,
            "all_statuses": ["ready", "moderated", "duplicate", "published", "rejected", "deleted", "draft"],
        },
    )


@router.post("/{item_id}/status")
def change_status(
    item_id: str,
    db: Annotated[Session, Depends(get_db)],
    new_status: str = Form(...),
    status_filter: str = Form(""),
):
    item = db.query(CuratedNewsItem).filter(CuratedNewsItem.id == uuid.UUID(item_id)).first()
    if not item:
        return RedirectResponse("/admin/news", status_code=303)

    if item.status in TERMINAL_STATUSES and new_status != "deleted":
        logger.warning(
            "Rejected status transition for item %s: %s → %s (published is terminal)",
            item_id,
            item.status,
            new_status,
        )
        return RedirectResponse(f"/admin/news?status={status_filter}", status_code=303)

    if new_status not in ALLOWED_STATUSES:
        logger.warning("Rejected invalid status '%s' for item %s", new_status, item_id)
        return RedirectResponse(f"/admin/news?status={status_filter}", status_code=303)

    old_status = item.status
    item.status = new_status
    db.commit()
    logger.info("Item %s status changed: %s → %s", item_id, old_status, new_status)
    return RedirectResponse(f"/admin/news?status={status_filter}", status_code=303)
