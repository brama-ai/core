"""Tests for manual digest trigger and channel delivery."""

import uuid
from unittest.mock import MagicMock, patch

from app.services import scheduler

# ---------------------------------------------------------------------------
# Scheduler-level single-flight tests
# ---------------------------------------------------------------------------


def test_trigger_digest_now_accepted() -> None:
    """trigger_digest_now returns True and starts a thread when lock is free."""
    if scheduler._digest_lock.locked():
        scheduler._digest_lock.release()

    started: dict[str, bool] = {}

    class DummyThread:
        def __init__(self, target, daemon, name):
            started["configured"] = callable(target) and daemon and name == "news-digest-manual"

        def start(self):
            started["started"] = True

    with patch.object(scheduler.threading, "Thread", DummyThread):
        result = scheduler.trigger_digest_now()

    assert result is True
    assert started.get("configured") is True
    assert started.get("started") is True

    if scheduler._digest_lock.locked():
        scheduler._digest_lock.release()


def test_trigger_digest_now_skipped_when_lock_held() -> None:
    """trigger_digest_now returns False when digest is already running."""
    acquired = scheduler._digest_lock.acquire(blocking=False)
    assert acquired

    try:
        result = scheduler.trigger_digest_now()
        assert result is False
    finally:
        scheduler._digest_lock.release()


# ---------------------------------------------------------------------------
# Admin endpoint tests
# ---------------------------------------------------------------------------


def test_admin_trigger_digest_accepted(client) -> None:
    """POST /admin/trigger/digest redirects when trigger is accepted."""
    with patch("app.services.scheduler.trigger_digest_now", return_value=True) as mock_trigger:
        response = client.post("/admin/trigger/digest", follow_redirects=False)

    assert response.status_code == 303
    assert response.headers["location"] == "/admin/settings"
    mock_trigger.assert_called_once()


def test_admin_trigger_digest_skipped(client) -> None:
    """POST /admin/trigger/digest still redirects when trigger is skipped (already running)."""
    with patch("app.services.scheduler.trigger_digest_now", return_value=False) as mock_trigger:
        response = client.post("/admin/trigger/digest", follow_redirects=False)

    assert response.status_code == 303
    assert response.headers["location"] == "/admin/settings"
    mock_trigger.assert_called_once()


# ---------------------------------------------------------------------------
# Digest service + channel delivery tests
# ---------------------------------------------------------------------------


def _make_curated_item():
    item = MagicMock()
    item.id = uuid.uuid4()
    item.title = "Test title"
    item.summary = "Test summary"
    item.body = "Test body"
    item.reference_url = "https://example.com/article"
    item.status = "ready"
    return item


def test_successful_digest_calls_publish_once() -> None:
    """When digest is created successfully, _publish_to_channel is called once."""
    from app.services.digest import run_digest

    fake_item = _make_curated_item()
    fake_digest_id = uuid.uuid4()

    fake_response = MagicMock()
    fake_response.choices = [MagicMock()]
    fake_response.choices[0].message.content = "TITLE: Test Digest\nBODY: Test body content"

    with (
        patch("app.services.digest.SessionLocal") as mock_session_local,
        patch("app.services.digest._get_client") as mock_get_client,
        patch("app.services.digest._publish_to_channel") as mock_publish,
    ):
        mock_db = MagicMock()
        mock_session_local.return_value = mock_db

        mock_settings = MagicMock()
        mock_settings.digest_source_statuses = "ready"
        mock_settings.digest_model = "test-model"
        mock_settings.digest_prompt = "Test prompt"
        mock_settings.digest_guardrail = "Test guardrail"
        mock_db.query.return_value.first.return_value = mock_settings
        mock_db.query.return_value.filter.return_value.order_by.return_value.all.return_value = [fake_item]

        fake_digest = MagicMock()
        fake_digest.id = fake_digest_id

        def add_side_effect(obj):
            if hasattr(obj, "title") and hasattr(obj, "body") and hasattr(obj, "item_count"):
                obj.id = fake_digest_id

        mock_db.add.side_effect = add_side_effect

        mock_client = MagicMock()
        mock_get_client.return_value = mock_client
        mock_client.chat.completions.create.return_value = fake_response

        run_digest()

    assert mock_publish.call_count == 1
    call_kwargs = mock_publish.call_args
    assert call_kwargs[0][2] == "Test body content"  # body
    assert call_kwargs[0][3] == 1  # item_count


def test_no_eligible_items_skips_publish() -> None:
    """When no eligible items exist, _publish_to_channel is NOT called."""
    from app.services.digest import run_digest

    with (
        patch("app.services.digest.SessionLocal") as mock_session_local,
        patch("app.services.digest._publish_to_channel") as mock_publish,
    ):
        mock_db = MagicMock()
        mock_session_local.return_value = mock_db

        mock_settings = MagicMock()
        mock_settings.digest_source_statuses = "ready"
        mock_settings.digest_model = "test-model"
        mock_settings.digest_prompt = "Test prompt"
        mock_settings.digest_guardrail = "Test guardrail"
        mock_db.query.return_value.first.return_value = mock_settings
        mock_db.query.return_value.filter.return_value.order_by.return_value.all.return_value = []

        result = run_digest()

    assert result is None
    mock_publish.assert_not_called()


def test_delivery_failure_digest_preserved(caplog) -> None:
    """When channel delivery fails, the warning is logged but digest is not rolled back."""
    import logging

    from app.services.digest import _publish_to_channel

    digest_id = uuid.uuid4()

    with (
        patch("app.services.digest.settings") as mock_settings,
        patch("app.services.digest.requests.post") as mock_post,
    ):
        mock_settings.openclaw_gateway_token = "test-token"
        mock_settings.platform_core_url = "http://core"
        mock_post.side_effect = Exception("Connection refused")

        with caplog.at_level(logging.WARNING, logger="app.services.digest"):
            _publish_to_channel(digest_id, "Title", "Body", 3, "req-123", "trace-456")

    assert any("channel delivery failed" in record.message for record in caplog.records)
    assert any("digest preserved" in record.message for record in caplog.records)
    assert any(str(digest_id) in record.message for record in caplog.records)
