"""
setup_db.py - Copy-paste template for trading bot database setup.

Supports SQLite (auto) and PostgreSQL (auto-create database and tables).

Usage:
    cp setup_db.py <your-project-root>/
    python setup_db.py

Pre-requisites:
    pip install python-dotenv 'psycopg[binary]'
"""

import os
import sys
import logging
from pathlib import Path
from urllib.parse import urlparse

from dotenv import load_dotenv

# Load .env from same dir as this script
load_dotenv(Path(__file__).parent / ".env")

logging.basicConfig(level=logging.INFO, format="%(message)s")
logger = logging.getLogger(__name__)


def setup_sqlite() -> bool:
    """SQLite: data dir + auto-init DB file on next access."""
    db_url = os.environ.get("DATABASE_URL", "sqlite:///data/trading_bot.db")
    logger.info(f"OK Mode SQLite - Path: {db_url}")
    if db_url.startswith("sqlite:///"):
        db_path = db_url.replace("sqlite:///", "")
        if not os.path.isabs(db_path):
            db_path = str(Path.cwd() / db_path)
        Path(db_path).parent.mkdir(parents=True, exist_ok=True)
        logger.info(f"   Directory ready: {Path(db_path).parent}")
    return True


def setup_postgresql() -> bool:
    """PostgreSQL: connect -> check server -> create DB -> create tables."""
    db_url = os.environ.get("DATABASE_URL", "")
    if not db_url or not db_url.startswith("postgresql"):
        logger.error("DATABASE_URL must start with postgresql://")
        return False

    try:
        import psycopg
    except ImportError:
        logger.error("psycopg not installed. Run: pip install 'psycopg[binary]'")
        return False

    parsed = urlparse(db_url)
    target_db = parsed.path.lstrip("/") or "trading_bot"
    user = parsed.username
    password = parsed.password
    host = parsed.hostname or "localhost"
    port = parsed.port or 5432

    if not user or not password:
        logger.error("DATABASE_URL needs username and password")
        return False

    logger.info(f"Connecting to PostgreSQL at {host}:{port} as {user}...")

    # Step 1: connect to default 'postgres' DB to check + create target
    try:
        with psycopg.connect(
            host=host, port=port, user=user, password=password,
            dbname="postgres", connect_timeout=10,
        ) as conn:
            conn.autocommit = True
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT 1 FROM pg_database WHERE datname = %s",
                    (target_db,),
                )
                if cur.fetchone():
                    logger.info(f"Database '{target_db}' exists")
                else:
                    cur.execute(f'CREATE DATABASE "{target_db}"')
                    logger.info(f"Database '{target_db}' CREATED")
    except psycopg.OperationalError as e:
        logger.error(f"Cannot connect: {e}")
        logger.error("Troubleshooting:")
        logger.error("  1. Is the Postgres service running?")
        logger.error("  2. Username and password correct?")
        logger.error("  3. Host and port correct? (default: localhost:5432)")
        logger.error(f"  Try: psql -h {host} -p {port} -U {user} -d postgres")
        return False

    # Step 2: create tables via SQLAlchemy
    logger.info(f"Creating tables in '{target_db}'...")
    try:
        sys.path.insert(0, str(Path(__file__).parent))
        from src.database.models import Base  # adjust import for your project
        from sqlalchemy import create_engine

        engine = create_engine(db_url, pool_pre_ping=True)
        Base.metadata.create_all(engine)
        engine.dispose()

        # Verify
        with psycopg.connect(db_url, connect_timeout=5) as conn:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT tablename FROM pg_tables WHERE schemaname='public' "
                    "ORDER BY tablename"
                )
                tables = [r[0] for r in cur.fetchall()]
                logger.info(f"Tables: {', '.join(tables) if tables else '(none)'}")
        return True
    except Exception as e:
        logger.error(f"Failed to create tables: {e}")
        return False


def main():
    logger.info("=" * 60)
    logger.info("Trading Bot - Database Setup")
    logger.info("=" * 60)

    env_path = Path(__file__).parent / ".env"
    if not env_path.exists():
        logger.error(f".env not found at {env_path}")
        logger.error("Run: cp .env.example .env then edit DATABASE_TYPE/URL")
        return 1

    db_type = os.environ.get("DATABASE_TYPE", "sqlite").lower()
    logger.info(f"Database type: {db_type}\n")

    if db_type == "sqlite":
        success = setup_sqlite()
    elif db_type in ("postgresql", "postgres"):
        success = setup_postgresql()
    else:
        logger.error(f"Unknown DATABASE_TYPE: {db_type}")
        return 1

    logger.info("")
    if success:
        logger.info("Setup OK! Next:")
        logger.info("   python main.py backtest")
        logger.info("   python main.py run --mode=mock --once")
        return 0
    else:
        logger.error("Setup failed.")
        return 1


if __name__ == "__main__":
    sys.exit(main())
