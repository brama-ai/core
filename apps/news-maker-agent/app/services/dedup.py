"""Deduplication service — embedding-based similarity + LLM confirmation."""

import json
import logging
import uuid
from datetime import datetime, timedelta, timezone

from openai import OpenAI
from pgvector.sqlalchemy import Vector
from sqlalchemy import cast, func, select

from app.config import settings
from app.database import SessionLocal
from app.middleware.trace import request_id_var, trace_id_var
from app.models.models import AgentSettings, CuratedNewsItem

logger = logging.getLogger(__name__)
SERVICE_NAME = "news-maker-agent"
FEATURE_NAME = "news.dedup.check_duplicate"

SIMILARITY_THRESHOLD = 0.85
LOOKBACK_MONTHS = 2


def _get_client() -> OpenAI:
    return OpenAI(
        base_url=f"{settings.litellm_base_url}/v1",
        api_key=settings.litellm_api_key,
    )


def _trace_context(item_suffix: str = "") -> tuple[str, str, dict[str, str], str, dict[str, object]]:
    base_request_id = request_id_var.get("") or f"llm-dedup-{uuid.uuid4()}"
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


def _compute_embedding(client: OpenAI, text: str, model: str, item_id: str) -> list[float] | None:
    """Compute embedding for text via LiteLLM. Returns None on failure."""
    try:
        _, _, headers, user_tag, _ = _trace_context(item_suffix=item_id)
        response = client.embeddings.create(
            model=model,
            input=text,
            extra_headers=headers,
        )
        return response.data[0].embedding
    except Exception as exc:
        logger.warning("Embedding computation failed for item %s: %s", item_id, exc)
        return None


def _llm_confirm_duplicate(
    client: OpenAI,
    model: str,
    new_summary: str,
    existing_summary: str,
    item_id: str,
) -> bool:
    """Ask LLM if two summaries describe the same news event. Returns True if duplicate."""
    try:
        _, _, headers, user_tag, metadata = _trace_context(item_suffix=f"{item_id}:confirm")
        system_prompt = (
            "You are a news deduplication assistant. "
            "Determine if two news summaries describe the same event or topic. "
            'Respond with JSON: {"is_duplicate": true} or {"is_duplicate": false}'
        )
        user_prompt = (
            f"Summary A:\n{new_summary}\n\n"
            f"Summary B:\n{existing_summary}\n\n"
            "Do these describe the same news event or topic?"
        )
        response = client.chat.completions.create(
            model=model,
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            response_format={"type": "json_object"},
            temperature=0.1,
            max_tokens=64,
            user=user_tag,
            metadata=metadata,
            extra_headers=headers,
            extra_body={
                "tags": [f"agent:{SERVICE_NAME}", f"method:{FEATURE_NAME}"],
            },
        )
        raw = response.choices[0].message.content or "{}"
        result = json.loads(raw)
        return bool(result.get("is_duplicate", False))
    except Exception as exc:
        logger.warning("LLM duplicate confirmation failed for item %s: %s", item_id, exc)
        return False


def check_and_mark_duplicate(curated_item_id: uuid.UUID) -> bool:
    """
    Check if a curated item is a duplicate of an existing item.
    Computes embedding, searches for similar items, and uses LLM to confirm.
    Returns True if item was marked as duplicate, False otherwise.
    """
    db = SessionLocal()
    try:
        item = db.query(CuratedNewsItem).filter(CuratedNewsItem.id == curated_item_id).first()
        if not item:
            logger.warning("Dedup: curated item %s not found", curated_item_id)
            return False

        agent_settings = db.query(AgentSettings).first()
        embedding_model = agent_settings.embedding_model if agent_settings else "text-embedding-3-small"
        dedup_model = agent_settings.ranker_model if agent_settings else settings.ranker_model

        client = _get_client()

        # Compute embedding for the new item
        text_to_embed = f"{item.title}\n{item.summary}"
        embedding = _compute_embedding(client, text_to_embed, embedding_model, str(item.id))
        if embedding is None:
            logger.warning("Dedup: skipping dedup for item %s (embedding failed)", item.id)
            return False

        # Store embedding
        item.embedding = embedding
        db.commit()

        # Search for similar items in the last 2 months (excluding self)
        lookback_cutoff = datetime.now(timezone.utc) - timedelta(days=LOOKBACK_MONTHS * 30)

        # Use pgvector cosine distance operator (<=>)
        # cosine_distance = 1 - cosine_similarity, so threshold 0.85 similarity → distance <= 0.15
        distance_threshold = 1.0 - SIMILARITY_THRESHOLD

        embedding_col = cast(embedding, Vector(1536))
        similar_items = (
            db.execute(
                select(CuratedNewsItem)
                .where(CuratedNewsItem.id != item.id)
                .where(CuratedNewsItem.embedding.isnot(None))
                .where(CuratedNewsItem.created_at >= lookback_cutoff)
                .where(CuratedNewsItem.status.notin_(["duplicate", "deleted"]))
                .order_by(CuratedNewsItem.embedding.op("<=>")(embedding_col))
                .limit(5)
            )
            .scalars()
            .all()
        )

        for candidate in similar_items:
            # Compute actual cosine distance
            distance_result = db.execute(
                select(func.cast(item.embedding, Vector(1536)).op("<=>")(func.cast(candidate.embedding, Vector(1536))))
            ).scalar()

            if distance_result is None or distance_result > distance_threshold:
                continue

            logger.info(
                "Dedup: item %s has cosine distance %.4f with item %s — triggering LLM confirmation",
                item.id,
                distance_result,
                candidate.id,
            )

            is_dup = _llm_confirm_duplicate(
                client,
                dedup_model,
                item.summary,
                candidate.summary,
                str(item.id),
            )

            if is_dup:
                item.status = "duplicate"
                db.commit()
                logger.info(
                    "Dedup: item %s marked as duplicate of %s",
                    item.id,
                    candidate.id,
                )
                return True

        logger.info("Dedup: item %s is unique", item.id)
        return False

    except Exception as exc:
        logger.exception("Dedup check failed for item %s: %s", curated_item_id, exc)
        return False
    finally:
        db.close()
