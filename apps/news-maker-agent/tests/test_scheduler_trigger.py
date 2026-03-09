from app.services import scheduler


def test_trigger_crawl_now_ignored_when_lock_is_held() -> None:
    acquired = scheduler._crawl_pipeline_lock.acquire(blocking=False)
    assert acquired

    try:
        assert scheduler.trigger_crawl_now() is False
    finally:
        scheduler._crawl_pipeline_lock.release()


def test_trigger_crawl_now_starts_thread(monkeypatch) -> None:
    started: dict[str, bool] = {}
    if scheduler._crawl_pipeline_lock.locked():
        scheduler._crawl_pipeline_lock.release()

    class DummyThread:
        def __init__(self, target, daemon, name):
            started["configured"] = callable(target) and daemon and name == "news-crawl-manual"
            started["target"] = target

        def start(self):
            started["started"] = True

    monkeypatch.setattr(scheduler.threading, "Thread", DummyThread)

    assert scheduler.trigger_crawl_now() is True
    assert started.get("configured") is True
    assert started.get("started") is True
    if scheduler._crawl_pipeline_lock.locked():
        scheduler._crawl_pipeline_lock.release()
