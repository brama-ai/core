"""Digest generation service — compiles curated items into a single digest."""

import logging
import re
import uuid
from datetime import datetime, timezone

import requests
from openai import OpenAI

from app.config import settings
from app.database import SessionLocal
from app.middleware.trace import request_id_var, trace_id_var
from app.models.models import AgentSettings, CuratedNewsItem, Digest, DigestItem

logger = logging.getLogger(__name__)
SERVICE_NAME = "news-maker-agent"
FEATURE_NAME = "news.digest.generate"


def _get_client() -> OpenAI:
    return OpenAI(
        base_url=f"{settings.litellm_base_url}/v1",
        api_key=settings.litellm_api_key,
    )


def _trace_context() -> tuple[str, str, dict[str, str], str, dict[str, object]]:
    request_id = request_id_var.get("") or f"llm-digest-{uuid.uuid4()}"
    trace_id = trace_id_var.get("")
    effective_trace_id = trace_id or request_id
    session_id = effective_trace_id
    headers = {
        "x-request-id": request_id,
        "x-service-name": SERVICE_NAME,
        "x-agent-name": SERVICE_NAME,
        "x-feature-name": FEATURE_NAME,
    }
    if trace_id:
        headers["x-trace-id"] = trace_id

    user_tag = f"service={SERVICE_NAME};feature={FEATURE_NAME};request_id={request_id}"
    metadata = {
        "request_id": request_id,
        "trace_id": effective_trace_id,
        "trace_name": f"{SERVICE_NAME}.{FEATURE_NAME}",
        "session_id": session_id,
        "generation_name": FEATURE_NAME,
        "tags": [f"agent:{SERVICE_NAME}", f"method:{FEATURE_NAME}"],
        "trace_user_id": user_tag,
        "trace_metadata": {
            "request_id": request_id,
            "session_id": session_id,
            "agent_name": SERVICE_NAME,
            "feature_name": FEATURE_NAME,
        },
    }
    return request_id, trace_id, headers, user_tag, metadata


def _adaptive_length_instruction(item_count: int) -> str:
    """Return per-item word count instruction based on total item count."""
    if item_count == 1:
        return "Provide detailed coverage (~500 words total)."
    elif item_count <= 3:
        return f"Provide moderate detail (~200 words per item, ~{item_count * 200} words total)."
    elif item_count <= 7:
        return f"Provide concise summaries (~100 words per item, ~{item_count * 100} words total)."
    else:
        return f"Provide brief bullet-style entries (~50 words per item, ~{item_count * 50} words total)."


def _build_digest_prompt(items: list[CuratedNewsItem], base_prompt: str, guardrail: str) -> tuple[str, str]:
    """Build system and user prompts for digest generation."""
    item_count = len(items)
    length_instruction = _adaptive_length_instruction(item_count)

    system_prompt = (
        f"{base_prompt}\n\n"
        f"You are compiling a digest of {item_count} news item(s). "
        f"{length_instruction} "
        f"Target total length: 600–800 words.\n\n"
        f"{guardrail}"
    )

    items_text = ""
    for i, item in enumerate(items, 1):
        items_text += f"--- Item {i} ---\nTitle: {item.title}\nSummary: {item.summary}\n"
        if item.reference_url:
            items_text += f"Source: {item.reference_url}\n"
        items_text += "\n"

    user_prompt = (
        f"Compile the following {item_count} news item(s) into a single Ukrainian-language digest.\n\n"
        f"{items_text}"
        f"Respond with:\n"
        f"TITLE: <digest title in Ukrainian>\n"
        f"BODY: <full digest body in Ukrainian>"
    )

    return system_prompt, user_prompt


def _extract_section(text: str, label: str) -> str:
    pattern = rf"(?:^|\n){re.escape(label)}:\s*(.+?)(?=\n[A-Z]+:|$)"
    match = re.search(pattern, text, re.DOTALL)
    if match:
        return match.group(1).strip()
    return ""


def run_digest() -> uuid.UUID | None:
    """
    Generate a digest from eligible curated items.
    Returns the digest UUID if created, None otherwise.
    """
    db = SessionLocal()
    try:
        agent_settings = db.query(AgentSettings).first()
        if not agent_settings:
            logger.warning("Digest: no agent settings found, using defaults")
            source_statuses_str = "ready,moderated"
            model = settings.rewriter_model
            base_prompt = (
                "You are a Ukrainian-language tech journalist. "
                "Compile the provided news items into a single cohesive digest article."
            )
            guardrail = "Always write in Ukrainian. Never fabricate facts. Preserve source references."
        else:
            source_statuses_str = agent_settings.digest_source_statuses or "ready,moderated"
            model = agent_settings.digest_model
            base_prompt = agent_settings.digest_prompt
            guardrail = agent_settings.digest_guardrail

        source_statuses = [s.strip() for s in source_statuses_str.split(",") if s.strip()]

        # Collect eligible items
        eligible_items = (
            db.query(CuratedNewsItem)
            .filter(CuratedNewsItem.status.in_(source_statuses))
            .order_by(CuratedNewsItem.created_at.asc())
            .all()
        )

        if not eligible_items:
            logger.info("Digest: no eligible items found (statuses: %s)", source_statuses_str)
            return None

        logger.info("Digest: found %d eligible items (statuses: %s)", len(eligible_items), source_statuses_str)

        system_prompt, user_prompt = _build_digest_prompt(eligible_items, base_prompt, guardrail)

        client = _get_client()
        request_id, trace_id, llm_headers, user_tag, metadata = _trace_context()

        response = client.chat.completions.create(
            model=model,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            temperature=0.5,
            max_tokens=2000,
            user=user_tag,
            metadata=metadata,
            extra_headers=llm_headers,
            extra_body={
                "tags": [f"agent:{SERVICE_NAME}", f"method:{FEATURE_NAME}"],
            },
        )

        content = response.choices[0].message.content or ""
        title = _extract_section(content, "TITLE") or "Дайджест новин"
        body = _extract_section(content, "BODY") or content

        if not body.strip():
            logger.warning("Digest: LLM returned empty body (request_id=%s)", request_id)
            return None

        # Persist digest and update item statuses atomically
        digest = Digest(
            title=title[:512],
            body=body,
            language="uk",
            item_count=len(eligible_items),
            source_statuses_used=source_statuses_str,
        )
        db.add(digest)
        db.flush()  # Get digest.id before committing

        now = datetime.now(timezone.utc)
        for item in eligible_items:
            link = DigestItem(digest_id=digest.id, curated_news_item_id=item.id)
            db.add(link)
            item.status = "published"
            item.published_at = now

        db.commit()
        logger.info(
            "Digest created: id=%s items=%d (request_id=%s, trace_id=%s)",
            digest.id,
            len(eligible_items),
            request_id,
            trace_id,
        )

        _publish_to_channel(digest.id, title, body, len(eligible_items), request_id, trace_id)

        return digest.id

    except Exception as exc:
        db.rollback()
        logger.exception("Digest generation failed: %s", exc)
        return None
    finally:
        db.close()


def _publish_to_channel(
    digest_id: uuid.UUID,
    title: str,
    body: str,
    item_count: int,
    request_id: str,
    trace_id: str,
) -> None:
    """Publish digest to channel via Core A2A -> OpenClaw route.

    Failures are logged but do NOT roll back the digest.
    """
    token = settings.openclaw_gateway_token
    if not token:
        logger.warning("Digest publish skipped: OPENCLAW_GATEWAY_TOKEN not configured (digest_id=%s)", digest_id)
        return

    url = f"{settings.platform_core_url}/api/v1/a2a/send-message"
    payload = {
        "tool": "openclaw.send_message",
        "input": {
            "title": title,
            "body": body,
        },
        "metadata": {
            "digest_id": str(digest_id),
            "item_count": item_count,
            "source": SERVICE_NAME,
        },
    }
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
        "x-request-id": request_id,
    }
    if trace_id:
        headers["x-trace-id"] = trace_id

    try:
        resp = requests.post(url, json=payload, headers=headers, timeout=10)
        resp.raise_for_status()
        logger.info(
            "Digest published to channel: digest_id=%s status=%d (request_id=%s)",
            digest_id,
            resp.status_code,
            request_id,
        )
    except Exception as exc:
        logger.warning(
            "Digest channel delivery failed (digest preserved): digest_id=%s error=%s (request_id=%s, trace_id=%s)",
            digest_id,
            exc,
            request_id,
            trace_id,
        )
