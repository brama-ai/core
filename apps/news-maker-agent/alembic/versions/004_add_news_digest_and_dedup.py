"""add news digest and deduplication

Revision ID: 004
Revises: 003
Create Date: 2026-03-11

"""

import sqlalchemy as sa
from alembic import op
from sqlalchemy.dialects import postgresql

revision = "004"
down_revision = "003"
branch_labels = None
depends_on = None


def upgrade() -> None:
    # Enable pgvector extension
    op.execute("CREATE EXTENSION IF NOT EXISTS vector")

    # Add embedding column to curated_news_items (1536-dim for text-embedding-3-small)
    op.execute("ALTER TABLE curated_news_items ADD COLUMN IF NOT EXISTS embedding vector(1536)")

    # Create digests table
    op.create_table(
        "digests",
        sa.Column("id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("title", sa.String(512), nullable=False),
        sa.Column("body", sa.Text(), nullable=False),
        sa.Column("language", sa.String(16), nullable=False, server_default="uk"),
        sa.Column("item_count", sa.Integer(), nullable=False, server_default="0"),
        sa.Column("source_statuses_used", sa.String(256), nullable=False, server_default=""),
        sa.Column("created_at", sa.DateTime(timezone=True), server_default=sa.text("now()"), nullable=False),
        sa.PrimaryKeyConstraint("id"),
    )

    # Create digest_items link table
    op.create_table(
        "digest_items",
        sa.Column("digest_id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("curated_news_item_id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.ForeignKeyConstraint(["digest_id"], ["digests.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["curated_news_item_id"], ["curated_news_items.id"], ondelete="CASCADE"),
        sa.PrimaryKeyConstraint("digest_id", "curated_news_item_id"),
    )

    # Add digest settings columns to agent_settings
    op.add_column(
        "agent_settings",
        sa.Column(
            "digest_prompt",
            sa.Text(),
            nullable=False,
            server_default=(
                "You are a Ukrainian-language tech journalist. "
                "Compile the provided news items into a single cohesive digest article."
            ),
        ),
    )
    op.add_column(
        "agent_settings",
        sa.Column(
            "digest_guardrail",
            sa.Text(),
            nullable=False,
            server_default=(
                "Always write in Ukrainian. Never fabricate facts. "
                "Preserve source references. Keep the digest focused and factual."
            ),
        ),
    )
    op.add_column(
        "agent_settings",
        sa.Column("digest_model", sa.String(128), nullable=False, server_default="minimax/minimax-m2.5"),
    )
    op.add_column(
        "agent_settings",
        sa.Column("digest_source_statuses", sa.String(256), nullable=False, server_default="ready,moderated"),
    )
    op.add_column(
        "agent_settings",
        sa.Column("digest_cron", sa.String(64), nullable=False, server_default="0 8 * * *"),
    )
    op.add_column(
        "agent_settings",
        sa.Column("embedding_model", sa.String(128), nullable=False, server_default="text-embedding-3-small"),
    )


def downgrade() -> None:
    op.drop_column("agent_settings", "embedding_model")
    op.drop_column("agent_settings", "digest_cron")
    op.drop_column("agent_settings", "digest_source_statuses")
    op.drop_column("agent_settings", "digest_model")
    op.drop_column("agent_settings", "digest_guardrail")
    op.drop_column("agent_settings", "digest_prompt")
    op.drop_table("digest_items")
    op.drop_table("digests")
    op.execute("ALTER TABLE curated_news_items DROP COLUMN IF EXISTS embedding")
