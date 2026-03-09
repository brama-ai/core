import uuid
from datetime import datetime

from sqlalchemy import (
    Boolean,
    DateTime,
    Float,
    ForeignKey,
    Integer,
    String,
    Text,
    func,
)
from sqlalchemy.dialects.postgresql import UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.database import Base


class NewsSource(Base):
    __tablename__ = "news_sources"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    name: Mapped[str] = mapped_column(String(128), nullable=False, unique=True)
    base_url: Mapped[str] = mapped_column(String(512), nullable=False)
    topic_scope: Mapped[str] = mapped_column(String(64), default="ai")
    enabled: Mapped[bool] = mapped_column(Boolean, default=True)
    crawl_priority: Mapped[int] = mapped_column(Integer, default=5)
    last_success_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    last_error_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    proxy_enabled_override: Mapped[bool | None] = mapped_column(Boolean, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())

    raw_items: Mapped[list["RawNewsItem"]] = relationship(back_populates="source")


class RawNewsItem(Base):
    __tablename__ = "raw_news_items"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    source_id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), ForeignKey("news_sources.id"), nullable=False)
    source_url: Mapped[str] = mapped_column(String(1024), nullable=False)
    canonical_url: Mapped[str | None] = mapped_column(String(1024), nullable=True)
    title: Mapped[str | None] = mapped_column(String(512), nullable=True)
    raw_text: Mapped[str | None] = mapped_column(Text, nullable=True)
    excerpt: Mapped[str | None] = mapped_column(String(1024), nullable=True)
    published_at_source: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    language: Mapped[str | None] = mapped_column(String(16), nullable=True)
    # new | scored | selected | expired | discarded
    status: Mapped[str] = mapped_column(String(32), default="new", index=True)
    score: Mapped[float | None] = mapped_column(Float, nullable=True)
    dedup_hash: Mapped[str] = mapped_column(String(64), nullable=False, unique=True, index=True)
    crawl_depth: Mapped[int] = mapped_column(Integer, default=0)
    discovered_from_url: Mapped[str | None] = mapped_column(String(1024), nullable=True)
    crawl_run_id: Mapped[uuid.UUID | None] = mapped_column(UUID(as_uuid=True), nullable=True, index=True)
    expires_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True, index=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())

    source: Mapped["NewsSource"] = relationship(back_populates="raw_items")
    curated_item: Mapped["CuratedNewsItem | None"] = relationship(back_populates="raw_item", uselist=False)


class CuratedNewsItem(Base):
    __tablename__ = "curated_news_items"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    raw_news_item_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("raw_news_items.id"),
        nullable=False,
        unique=True,
    )
    title: Mapped[str] = mapped_column(String(512), nullable=False)
    summary: Mapped[str] = mapped_column(Text, nullable=False)
    body: Mapped[str] = mapped_column(Text, nullable=False)
    language: Mapped[str] = mapped_column(String(16), default="uk")
    style_profile: Mapped[str | None] = mapped_column(String(64), nullable=True)
    # draft | ready | published | rejected
    status: Mapped[str] = mapped_column(String(32), default="draft", index=True)
    reference_title: Mapped[str | None] = mapped_column(String(512), nullable=True)
    reference_url: Mapped[str | None] = mapped_column(String(1024), nullable=True)
    reference_domain: Mapped[str | None] = mapped_column(String(128), nullable=True)
    published_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())

    raw_item: Mapped["RawNewsItem"] = relationship(back_populates="curated_item")


class AgentSettings(Base):
    __tablename__ = "agent_settings"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    # Prompts
    ranker_prompt: Mapped[str] = mapped_column(
        Text,
        default=(
            "You are an AI news ranking agent for a Ukrainian tech "
            "community. Evaluate each article for relevance, quality, and freshness."
        ),
    )
    ranker_guardrail: Mapped[str] = mapped_column(
        Text,
        default=(
            "Always respond with valid JSON. Never select more than 10 items. "
            "Reject clickbait, marketing content, and duplicate stories."
        ),
    )
    rewriter_prompt: Mapped[str] = mapped_column(
        Text,
        default=(
            "You are a Ukrainian-language tech journalist. Rewrite the provided "
            "article into a clear, engaging Ukrainian-language summary."
        ),
    )
    rewriter_guardrail: Mapped[str] = mapped_column(
        Text,
        default=(
            "Always preserve the original source reference. Never fabricate facts. "
            "Output must be in Ukrainian. Keep summary under 300 words."
        ),
    )
    # Scheduler
    crawl_cron: Mapped[str] = mapped_column(String(64), default="0 */4 * * *")
    cleanup_cron: Mapped[str] = mapped_column(String(64), default="0 2 * * *")
    # Retention
    raw_item_ttl_hours: Mapped[int] = mapped_column(Integer, default=72)
    # Crawl limits
    crawl_max_depth: Mapped[int] = mapped_column(Integer, default=1)
    crawl_max_links_per_depth: Mapped[int] = mapped_column(Integer, default=10)
    # Proxy
    proxy_enabled: Mapped[bool] = mapped_column(Boolean, default=False)
    proxy_url: Mapped[str | None] = mapped_column(String(512), nullable=True)
    # Models
    ranker_model: Mapped[str] = mapped_column(String(128), default="minimax/minimax-m2.5")
    rewriter_model: Mapped[str] = mapped_column(String(128), default="minimax/minimax-m2.5")
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True),
        server_default=func.now(),
        onupdate=func.now(),
    )


class SchedulerRun(Base):
    __tablename__ = "scheduler_runs"

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    job_name: Mapped[str] = mapped_column(String(64), nullable=False, index=True)
    started_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now())
    finished_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    status: Mapped[str] = mapped_column(String(32), default="running")
    items_seen: Mapped[int] = mapped_column(Integer, default=0)
    items_selected: Mapped[int] = mapped_column(Integer, default=0)
    error_message: Mapped[str | None] = mapped_column(Text, nullable=True)
