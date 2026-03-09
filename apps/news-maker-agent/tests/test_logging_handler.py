import json
import logging
import sys

from app.logging_handler import OpenSearchHandler
from app.middleware.trace import request_id_var, trace_id_var


def test_opensearch_handler_flush_posts_bulk_payload(monkeypatch) -> None:
    captured: dict[str, object] = {}

    def fake_post(url: str, content: str, headers: dict[str, str], timeout: float):
        captured["url"] = url
        captured["content"] = content
        captured["headers"] = headers
        captured["timeout"] = timeout

    monkeypatch.setattr("app.logging_handler.httpx.post", fake_post)

    handler = OpenSearchHandler("http://opensearch:9200")
    logger = logging.getLogger("test.news.logging")
    logger.setLevel(logging.INFO)
    logger.handlers = [handler]
    logger.propagate = False

    logger.info("hello logging pipeline")
    handler.flush()

    assert captured["url"] == "http://opensearch:9200/_bulk"
    assert captured["headers"] == {"Content-Type": "application/x-ndjson"}
    assert captured["timeout"] == 3.0

    lines = str(captured["content"]).strip().splitlines()
    assert len(lines) == 2
    meta = json.loads(lines[0])
    doc = json.loads(lines[1])
    assert "index" in meta
    assert doc["message"] == "hello logging pipeline"


def test_format_record_contains_trace_and_request_ids() -> None:
    handler = OpenSearchHandler("http://opensearch:9200")

    trace_token = trace_id_var.set("trace-123")
    request_token = request_id_var.set("req-456")
    try:
        record = logging.LogRecord(
            name="test.channel",
            level=logging.INFO,
            pathname=__file__,
            lineno=10,
            msg="trace check",
            args=(),
            exc_info=None,
        )
        payload = handler._format_record(record)  # noqa: SLF001
    finally:
        trace_id_var.reset(trace_token)
        request_id_var.reset(request_token)

    assert payload["trace_id"] == "trace-123"
    assert payload["request_id"] == "req-456"
    assert payload["message"] == "trace check"


def test_format_record_serializes_exception() -> None:
    handler = OpenSearchHandler("http://opensearch:9200")

    try:
        raise ValueError("boom")
    except ValueError:
        record = logging.LogRecord(
            name="test.channel",
            level=logging.ERROR,
            pathname=__file__,
            lineno=40,
            msg="failed",
            args=(),
            exc_info=sys.exc_info(),
        )

    payload = handler._format_record(record)  # noqa: SLF001
    assert payload["message"] == "failed"
    assert payload["exception"]["class"] == "ValueError"
    assert payload["exception"]["message"] == "boom"
