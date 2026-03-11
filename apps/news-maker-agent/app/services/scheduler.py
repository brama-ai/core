"""APScheduler setup for crawl and cleanup jobs."""

import logging
import threading
from datetime import datetime, timezone

from apscheduler.schedulers.background import BackgroundScheduler
from apscheduler.triggers.cron import CronTrigger

from app.database import SessionLocal
from app.models.models import AgentSettings, SchedulerRun

logger = logging.getLogger(__name__)

_scheduler: BackgroundScheduler | None = None
_crawl_pipeline_lock = threading.Lock()
_digest_lock = threading.Lock()


def recover_interrupted_runs() -> int:
    """Mark stale running scheduler runs as failed after service restarts."""
    db = SessionLocal()
    try:
        stale_runs = (
            db.query(SchedulerRun).filter(SchedulerRun.status == "running", SchedulerRun.finished_at.is_(None)).all()
        )
        if not stale_runs:
            return 0

        finished_at = datetime.now(timezone.utc)
        message = "Interrupted by service restart before completion"
        for run in stale_runs:
            run.status = "failed"
            run.finished_at = finished_at
            run.error_message = f"{run.error_message}; {message}" if run.error_message else message

        db.commit()
        logger.warning("Recovered %d interrupted scheduler runs", len(stale_runs))
        return len(stale_runs)
    except Exception:
        db.rollback()
        logger.exception("Failed to recover interrupted scheduler runs")
        return 0
    finally:
        db.close()


def _run_crawl_pipeline() -> None:
    from app.services.crawler import run_crawl
    from app.services.ranker import run_ranking
    from app.services.rewriter import run_rewriting

    if not _crawl_pipeline_lock.acquire(blocking=False):
        logger.warning("Skipping crawl pipeline: another crawl pipeline run is in progress")
        return

    logger.info("Starting crawl pipeline")
    try:
        crawled_items = run_crawl()
        ranked_items = run_ranking()
        rewritten_items = run_rewriting()
        logger.info(
            "Crawl pipeline finished: crawled=%d ranked_selected=%d rewritten_ready=%d",
            crawled_items,
            ranked_items,
            rewritten_items,
        )
    finally:
        _crawl_pipeline_lock.release()


def _run_cleanup() -> None:
    from app.services.crawler import run_cleanup

    logger.info("Starting cleanup job")
    run_cleanup()


def _get_settings() -> AgentSettings | None:
    db = SessionLocal()
    try:
        return db.query(AgentSettings).first()
    finally:
        db.close()


def start_scheduler() -> None:
    global _scheduler

    recover_interrupted_runs()

    agent_settings = _get_settings()
    crawl_cron = agent_settings.crawl_cron if agent_settings else "0 */4 * * *"
    cleanup_cron = agent_settings.cleanup_cron if agent_settings else "0 2 * * *"

    _scheduler = BackgroundScheduler()
    _scheduler.add_job(
        _run_crawl_pipeline,
        CronTrigger.from_crontab(crawl_cron),
        id="crawl_pipeline",
        replace_existing=True,
    )
    _scheduler.add_job(
        _run_cleanup,
        CronTrigger.from_crontab(cleanup_cron),
        id="cleanup",
        replace_existing=True,
    )
    _scheduler.start()
    logger.info("Scheduler started (crawl: %s, cleanup: %s)", crawl_cron, cleanup_cron)


def stop_scheduler() -> None:
    global _scheduler
    if _scheduler and _scheduler.running:
        _scheduler.shutdown(wait=False)
        logger.info("Scheduler stopped")


def trigger_crawl_now() -> bool:
    """Manually trigger crawl pipeline immediately."""
    if _crawl_pipeline_lock.locked():
        logger.warning("Manual crawl trigger ignored: crawl pipeline is already running")
        return False

    threading.Thread(target=_run_crawl_pipeline, daemon=True, name="news-crawl-manual").start()
    logger.info("Manual crawl trigger accepted")
    return True


def trigger_cleanup_now() -> None:
    """Manually trigger cleanup immediately."""
    import threading

    threading.Thread(target=_run_cleanup, daemon=True, name="news-cleanup-manual").start()
    logger.info("Manual cleanup trigger accepted")


def _run_digest() -> None:
    from app.services.digest import run_digest

    if not _digest_lock.acquire(blocking=False):
        logger.warning("Skipping digest: another digest run is in progress")
        return

    logger.info("Starting manual digest run")
    try:
        run_digest()
    finally:
        _digest_lock.release()


def trigger_digest_now() -> bool:
    """Manually trigger digest generation immediately. Returns False if already running."""
    if _digest_lock.locked():
        logger.warning("Manual digest trigger ignored: digest is already running")
        return False

    threading.Thread(target=_run_digest, daemon=True, name="news-digest-manual").start()
    logger.info("Manual digest trigger accepted")
    return True
