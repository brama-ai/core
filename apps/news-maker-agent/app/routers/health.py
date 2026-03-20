import os
import time
from datetime import datetime
from typing import Dict, Any

import httpx
from fastapi import APIRouter, status
from fastapi.responses import JSONResponse
from sqlalchemy import text

from app.database import get_db

router = APIRouter()


@router.get("/health")
def health() -> JSONResponse:
    """Basic health check endpoint."""
    return JSONResponse({
        "status": "ok",
        "service": "news-maker-agent",
        "version": "0.1.0",
        "timestamp": datetime.utcnow().isoformat() + "Z"
    })


@router.get("/health/ready")
def ready() -> JSONResponse:
    """Readiness check - verifies all dependencies are available."""
    checks = {}
    overall_status = "ok"
    
    # Database connectivity check
    try:
        db = next(get_db())
        db.execute(text("SELECT 1"))
        checks["database"] = {"status": "ok", "message": "Connected"}
    except Exception as e:
        checks["database"] = {"status": "error", "message": f"Connection failed: {str(e)}"}
        overall_status = "error"
    
    # Core platform connectivity check
    checks["core_platform"] = _check_core_connectivity()
    if checks["core_platform"]["status"] != "ok":
        overall_status = "error"
    
    response_data = {
        "status": overall_status,
        "service": "news-maker-agent",
        "version": "0.1.0",
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "checks": checks
    }
    
    http_status = status.HTTP_200_OK if overall_status == "ok" else status.HTTP_503_SERVICE_UNAVAILABLE
    return JSONResponse(response_data, status_code=http_status)


@router.get("/health/live")
def live() -> JSONResponse:
    """Liveness check - verifies the application is running."""
    return JSONResponse({
        "status": "ok",
        "service": "news-maker-agent",
        "version": "0.1.0",
        "timestamp": datetime.utcnow().isoformat() + "Z",
        "uptime": _get_uptime()
    })


def _check_core_connectivity() -> Dict[str, Any]:
    """Check connectivity to the core platform."""
    core_url = os.getenv("CORE_PLATFORM_URL", "http://core")
    
    try:
        with httpx.Client(timeout=2.0) as client:
            response = client.get(f"{core_url}/health")
            if response.status_code == 200:
                return {"status": "ok", "message": "Core platform reachable"}
            else:
                return {"status": "error", "message": f"Core platform returned {response.status_code}"}
    except Exception as e:
        return {"status": "error", "message": f"Core connectivity check failed: {str(e)}"}


def _get_uptime() -> Dict[str, Any]:
    """Get system uptime information."""
    try:
        with open('/proc/uptime', 'r') as f:
            uptime_seconds = float(f.read().split()[0])
        
        days = int(uptime_seconds // 86400)
        hours = int((uptime_seconds % 86400) // 3600)
        minutes = int((uptime_seconds % 3600) // 60)
        
        return {
            "seconds": uptime_seconds,
            "human": f"{days}d {hours}h {minutes}m"
        }
    except Exception:
        return {"seconds": 0, "human": "unknown"}
