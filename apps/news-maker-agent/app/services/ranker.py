"""Agent 1: Ranker — scores and selects up to 10 raw news items per run."""

import json
import logging
import uuid

from openai import OpenAI

from app.config import settings
from app.database import SessionLocal
from app.middleware.trace import request_id_var, trace_id_var
from app.models.models import AgentSettings, RawNewsItem

logger = logging.getLogger(__name__)

MAX_SELECTED = 10
SERVICE_NAME = "news-maker-agent"
FEATURE_NAME = "news.ranker.run_ranking"


def _get_client() -> OpenAI:
    return OpenAI(
        base_url=f"{settings.litellm_base_url}/v1",
        api_key=settings.litellm_api_key,
    )


def _trace_context() -> tuple[str, str, dict[str, str], str, dict[str, object]]:
    request_id = request_id_var.get("") or f"llm-ranker-{uuid.uuid4()}"
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


def run_ranking() -> int:
    """Score and select top items. Returns count of selected items."""
    db = SessionLocal()
    try:
        agent_settings = db.query(AgentSettings).first()
        model = agent_settings.ranker_model if agent_settings else settings.ranker_model
        base_prompt = agent_settings.ranker_prompt if agent_settings else ""
        guardrail = agent_settings.ranker_guardrail if agent_settings else ""

        new_items = db.query(RawNewsItem).filter(RawNewsItem.status == "new").limit(50).all()
        if not new_items:
            logger.info("No new items to rank")
            return 0

        candidates = [
            {"id": str(item.id), "title": item.title or "", "excerpt": item.excerpt or ""} for item in new_items
        ]

        system_prompt = f"{base_prompt}\n\n{guardrail}"
        user_prompt = (
            f"Here are {len(candidates)} news article candidates. "
            f"Score each from 0.0 to 1.0 for AI-relevance and quality. "
            f"Select at most {MAX_SELECTED} best ones.\n\n"
            f'Respond with JSON: {{"scored": [{{"id": "...", "score": 0.0, "selected": true/false}}]}}\n\n'
            f"Candidates:\n{json.dumps(candidates, ensure_ascii=False)}"
        )

        client = _get_client()
        request_id, trace_id, llm_headers, user_tag, metadata = _trace_context()
        response = client.chat.completions.create(
            model=model,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            response_format={"type": "json_object"},
            temperature=0.3,
            user=user_tag,
            metadata=metadata,
            extra_headers=llm_headers,
            extra_body={
                "tags": [f"agent:{SERVICE_NAME}", f"method:{FEATURE_NAME}"],
            },
        )

        raw = response.choices[0].message.content or "{}"
        result = json.loads(raw)
        scored_list = result.get("scored", [])

        selected_count = 0
        id_map = {str(item.id): item for item in new_items}

        for entry in scored_list:
            item_id = entry.get("id", "")
            score = float(entry.get("score", 0.0))
            selected = bool(entry.get("selected", False))

            item = id_map.get(item_id)
            if not item:
                continue

            item.score = score
            if selected and selected_count < MAX_SELECTED:
                item.status = "selected"
                selected_count += 1
            else:
                item.status = "scored"

        # Mark remaining new items as scored
        for item in new_items:
            if item.status == "new":
                item.status = "scored"

        db.commit()
        logger.info(
            "Ranking complete: %d/%d selected (llm_request_id=%s, trace_id=%s)",
            selected_count,
            len(new_items),
            request_id,
            trace_id,
        )
        return selected_count

    except Exception as exc:
        logger.exception("Ranking failed: %s", exc)
        return 0
    finally:
        db.close()
