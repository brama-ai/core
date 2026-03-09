"""add crawl depth metadata and depth settings

Revision ID: 003
Revises: 002
Create Date: 2026-03-08

"""

import sqlalchemy as sa
from alembic import op

revision = "003"
down_revision = "002"
branch_labels = None
depends_on = None


def upgrade() -> None:
    op.add_column("raw_news_items", sa.Column("crawl_depth", sa.Integer(), nullable=False, server_default="0"))
    op.add_column("raw_news_items", sa.Column("discovered_from_url", sa.String(length=1024), nullable=True))
    op.add_column("agent_settings", sa.Column("crawl_max_depth", sa.Integer(), nullable=False, server_default="1"))
    op.add_column(
        "agent_settings",
        sa.Column("crawl_max_links_per_depth", sa.Integer(), nullable=False, server_default="10"),
    )


def downgrade() -> None:
    op.drop_column("agent_settings", "crawl_max_links_per_depth")
    op.drop_column("agent_settings", "crawl_max_depth")
    op.drop_column("raw_news_items", "discovered_from_url")
    op.drop_column("raw_news_items", "crawl_depth")
