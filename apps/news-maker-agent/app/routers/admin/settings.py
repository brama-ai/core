import logging
from typing import Annotated

from fastapi import APIRouter, Depends, Form, Request
from fastapi.responses import HTMLResponse, RedirectResponse
from sqlalchemy.orm import Session

from app.database import get_db
from app.models.models import AgentSettings, SchedulerRun
from app.templates_config import templates

router = APIRouter(tags=["admin-settings"])
logger = logging.getLogger(__name__)


def _get_or_create_settings(db: Session) -> AgentSettings:
    s = db.query(AgentSettings).first()
    if not s:
        s = AgentSettings()
        db.add(s)
        db.commit()
        db.refresh(s)
    return s


@router.get("/admin/settings", response_class=HTMLResponse)
def show_settings(request: Request, db: Annotated[Session, Depends(get_db)]):
    s = _get_or_create_settings(db)
    runs = db.query(SchedulerRun).order_by(SchedulerRun.started_at.desc()).limit(20).all()
    return templates.TemplateResponse(request, "admin/settings.html", {"settings": s, "runs": runs})


@router.post("/admin/settings")
def update_settings(
    db: Annotated[Session, Depends(get_db)],
    ranker_prompt: str = Form(...),
    ranker_guardrail: str = Form(...),
    rewriter_prompt: str = Form(...),
    rewriter_guardrail: str = Form(...),
    crawl_cron: str = Form(...),
    cleanup_cron: str = Form(...),
    raw_item_ttl_hours: int = Form(...),
    crawl_max_depth: int = Form(...),
    crawl_max_links_per_depth: int = Form(...),
    proxy_enabled: str = Form(""),
    proxy_url: str = Form(""),
    ranker_model: str = Form(...),
    rewriter_model: str = Form(...),
):
    s = _get_or_create_settings(db)
    s.ranker_prompt = ranker_prompt
    s.ranker_guardrail = ranker_guardrail
    s.rewriter_prompt = rewriter_prompt
    s.rewriter_guardrail = rewriter_guardrail
    s.crawl_cron = crawl_cron
    s.cleanup_cron = cleanup_cron
    s.raw_item_ttl_hours = raw_item_ttl_hours
    s.crawl_max_depth = max(1, min(2, crawl_max_depth))
    s.crawl_max_links_per_depth = max(1, crawl_max_links_per_depth)
    s.proxy_enabled = proxy_enabled in ("true", "on", "1", "yes")
    s.proxy_url = proxy_url or None
    s.ranker_model = ranker_model
    s.rewriter_model = rewriter_model
    db.commit()
    return RedirectResponse("/admin/settings", status_code=303)


@router.post("/admin/trigger/crawl")
def trigger_crawl():
    from app.services.scheduler import trigger_crawl_now

    logger.info("Received manual crawl trigger from admin")
    accepted = trigger_crawl_now()
    if not accepted:
        logger.warning("Manual crawl trigger skipped: crawl pipeline is already running")
    return RedirectResponse("/admin/settings", status_code=303)


@router.post("/admin/trigger/cleanup")
def trigger_cleanup():
    from app.services.scheduler import trigger_cleanup_now

    logger.info("Received manual cleanup trigger from admin")
    trigger_cleanup_now()
    return RedirectResponse("/admin/settings", status_code=303)


@router.post("/admin/trigger/digest")
def trigger_digest():
    from app.services.scheduler import trigger_digest_now

    logger.info("Received manual digest trigger from admin")
    accepted = trigger_digest_now()
    if not accepted:
        logger.warning("Manual digest trigger skipped: digest is already running")
    return RedirectResponse("/admin/settings", status_code=303)
