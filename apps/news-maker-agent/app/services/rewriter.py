"""Agent 2: Rewriter/Translator — rewrites selected items to Ukrainian publication format."""

import logging
import re
import uuid
from urllib.parse import urlparse

from openai import OpenAI

from app.config import settings
from app.database import SessionLocal
from app.middleware.trace import request_id_var, trace_id_var
from app.models.models import AgentSettings, CuratedNewsItem, RawNewsItem

logger = logging.getLogger(__name__)
SERVICE_NAME = "news-maker-agent"
FEATURE_NAME = "news.rewriter.run_rewriting"


def _get_client() -> OpenAI:
    return OpenAI(
        base_url=f"{settings.litellm_base_url}/v1",
        api_key=settings.litellm_api_key,
    )


def _trace_context(item_suffix: str = "") -> tuple[str, str, dict[str, str], str, dict[str, object]]:
    base_request_id = request_id_var.get("") or f"llm-rewriter-{uuid.uuid4()}"
    request_id = f"{base_request_id}:{item_suffix}" if item_suffix else base_request_id
    trace_id = trace_id_var.get("")
    effective_trace_id = trace_id or base_request_id
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


def _extract_domain(url: str | None) -> str | None:
    if not url:
        return None
    try:
        return urlparse(url).netloc
    except Exception:
        return None


def run_rewriting() -> int:
    """Rewrite selected items to Ukrainian. Returns count of ready items."""
    db = SessionLocal()
    try:
        agent_settings = db.query(AgentSettings).first()
        model = agent_settings.rewriter_model if agent_settings else settings.rewriter_model
        base_prompt = agent_settings.rewriter_prompt if agent_settings else ""
        guardrail = agent_settings.rewriter_guardrail if agent_settings else ""

        selected_items = (
            db.query(RawNewsItem).filter(RawNewsItem.status == "selected").filter(~RawNewsItem.curated_item.has()).all()
        )

        if not selected_items:
            logger.info("No selected items to rewrite")
            return 0

        client = _get_client()
        system_prompt = f"{base_prompt}\n\n{guardrail}"
        ready_count = 0

        for item in selected_items:
            article_text = item.raw_text or item.excerpt or ""
            if not article_text.strip():
                item.status = "discarded"
                db.commit()
                continue

            user_prompt = (
                f"Original article title: {item.title or 'Unknown'}\n"
                f"Source URL: {item.canonical_url or item.source_url}\n\n"
                f"Article content:\n{article_text[:4000]}\n\n"
                f"Write a Ukrainian-language news article based on the above. "
                f"Respond with:\n"
                f"TITLE: <Ukrainian title>\n"
                f"SUMMARY: <2-3 sentence summary in Ukrainian>\n"
                f"BODY: <full Ukrainian article body>"
            )

            try:
                llm_request_id, trace_id, llm_headers, user_tag, metadata = _trace_context(
                    item_suffix=str(item.id),
                )
                response = client.chat.completions.create(
                    model=model,
                    messages=[
                        {"role": "system", "content": system_prompt},
                        {"role": "user", "content": user_prompt},
                    ],
                    temperature=0.5,
                    max_tokens=1500,
                    user=user_tag,
                    metadata=metadata,
                    extra_headers=llm_headers,
                    extra_body={
                        "tags": [f"agent:{SERVICE_NAME}", f"method:{FEATURE_NAME}"],
                    },
                )
                content = response.choices[0].message.content or ""

                title = _extract_section(content, "TITLE") or item.title or "Без заголовку"
                summary = _extract_section(content, "SUMMARY") or ""
                body = _extract_section(content, "BODY") or content

                if not summary or not body:
                    logger.warning("Rewriter output malformed for item %s", item.id)
                    item.status = "discarded"
                    db.commit()
                    continue

                ref_url = item.canonical_url or item.source_url
                curated = CuratedNewsItem(
                    raw_news_item_id=item.id,
                    title=title[:512],
                    summary=summary[:1024],
                    body=body,
                    language="uk",
                    status="ready",
                    reference_title=item.title,
                    reference_url=ref_url,
                    reference_domain=_extract_domain(ref_url),
                )
                db.add(curated)
                db.commit()
                ready_count += 1
                logger.info(
                    "Rewritten item %s → ready (llm_request_id=%s, trace_id=%s)",
                    item.id,
                    llm_request_id,
                    trace_id,
                )

            except Exception as exc:
                logger.warning("Rewriting failed for item %s: %s", item.id, exc)
                item.status = "discarded"
                db.commit()

        logger.info("Rewriting complete: %d items ready", ready_count)
        return ready_count

    except Exception as exc:
        logger.exception("Rewriting job failed: %s", exc)
        return 0
    finally:
        db.close()


def _extract_section(text: str, label: str) -> str:
    pattern = rf"(?:^|\n){re.escape(label)}:\s*(.+?)(?=\n[A-Z]+:|$)"
    match = re.search(pattern, text, re.DOTALL)
    if match:
        return match.group(1).strip()
    return ""
