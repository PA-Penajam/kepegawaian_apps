# Integrasi FastAPI (Python)

Panduan ini menjelaskan cara mengintegrasikan aplikasi FastAPI dengan SSO kepegawaian-apps.

---

## 1. Konfigurasi Environment

Tambahkan ke `.env`:

```env
IAM_URL=https://kepegawaian.pa-penajam.go.id
IAM_API_KEY=iam_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
IAM_API_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
IAM_APP_SLUG=nama-aplikasi-anda
```

Install dependency:

```bash
pip install httpx python-dotenv starlette itsdangerous
```

Buat `config/iam.py`:

```python
import os
from dotenv import load_dotenv

load_dotenv()

IAM_URL       = os.getenv("IAM_URL", "")
IAM_API_KEY   = os.getenv("IAM_API_KEY", "")
IAM_API_SECRET = os.getenv("IAM_API_SECRET", "")
IAM_APP_SLUG  = os.getenv("IAM_APP_SLUG", "")
```

---

## 2. Helper: Hitung HMAC Signature

Buat `services/iam_signature.py`:

```python
import hashlib
import hmac
import time
from urllib.parse import urlencode

from config.iam import IAM_API_KEY, IAM_API_SECRET


def build_iam_headers(
    method: str,
    path: str,
    query: dict = None,
    body: bytes = b"",
) -> dict:
    timestamp  = str(int(time.time()))
    query      = query or {}

    # Sort query parameters A-Z
    sorted_query = urlencode(sorted(query.items()))
    body_hash    = hashlib.sha256(body).hexdigest()

    payload = ":".join([
        method.upper(),
        path,
        sorted_query,
        body_hash,
        timestamp,
    ])

    signature = hmac.new(
        IAM_API_SECRET.encode(),
        payload.encode(),
        hashlib.sha256,
    ).hexdigest()

    return {
        "X-App-Key":    IAM_API_KEY,
        "X-Timestamp":  timestamp,
        "X-Signature":  signature,
        "Content-Type": "application/json",
    }
```

---

## 3. Dependency: Validasi Session IAM

Buat `dependencies/iam_auth.py`:

```python
import hashlib
import json
from typing import Optional

import httpx
from fastapi import Depends, HTTPException, Request
from starlette.responses import RedirectResponse

from config.iam import IAM_APP_SLUG, IAM_URL
from services.iam_signature import build_iam_headers

# Cache sederhana in-memory (ganti dengan Redis untuk production)
_cache: dict = {}


async def get_iam_data(token: str) -> Optional[dict]:
    cache_key = "iam_" + hashlib.sha256(token.encode()).hexdigest()

    if cache_key in _cache:
        return _cache[cache_key]

    path    = "/api/v1/iam/validate"
    headers = build_iam_headers("GET", path)
    headers["Authorization"] = f"Bearer {token}"

    async with httpx.AsyncClient() as client:
        response = await client.get(IAM_URL + path, headers=headers)

    if response.status_code != 200:
        return None

    data = response.json()
    _cache[cache_key] = data  # simpan 60 detik (implementasi TTL disesuaikan)
    return data


async def require_iam(request: Request) -> dict:
    token = request.session.get("iam_token")

    if not token:
        callback  = str(request.url_for("sso_callback"))
        sso_url   = f"{IAM_URL}/sso/login?app={IAM_APP_SLUG}&redirect={callback}"
        raise HTTPException(status_code=307, headers={"Location": sso_url})

    data = await get_iam_data(token)

    if not data or "user" not in data:
        request.session.pop("iam_token", None)
        callback  = str(request.url_for("sso_callback"))
        sso_url   = f"{IAM_URL}/sso/login?app={IAM_APP_SLUG}&redirect={callback}"
        raise HTTPException(status_code=307, headers={"Location": sso_url})

    return data


def require_permission(permission: str):
    async def checker(iam_data: dict = Depends(require_iam)) -> dict:
        if permission not in iam_data.get("permissions", []):
            raise HTTPException(status_code=403, detail="Akses ditolak.")
        return iam_data
    return checker
```

> **Catatan production:** Ganti `_cache` dict in-memory dengan Redis menggunakan `aioredis` agar cache tidak hilang saat restart dan bisa di-share antar worker.

---

## 4. Route SSO Callback

Tambahkan ke `main.py` atau router Anda:

```python
import json

import httpx
from fastapi import APIRouter, Request
from fastapi.responses import RedirectResponse

from config.iam import IAM_URL
from services.iam_signature import build_iam_headers

router = APIRouter()


@router.get("/sso/callback", name="sso_callback")
async def sso_callback(request: Request, code: str = ""):
    if not code or len(code) != 64:
        raise HTTPException(status_code=400, detail="SSO code tidak valid.")

    path    = "/api/v1/iam/exchange-code"
    payload = json.dumps({"code": code}).encode()
    headers = build_iam_headers("POST", path, body=payload)

    async with httpx.AsyncClient() as client:
        response = await client.post(
            IAM_URL + path,
            content=payload,
            headers=headers,
        )

    if response.status_code != 200:
        return RedirectResponse("/login?error=sso_failed")

    data = response.json()

    # Simpan token di session server-side
    request.session["iam_token"]      = data["token"]
    request.session["iam_expires_at"] = data["expires_at"]

    return RedirectResponse("/dashboard")
```

Setup session middleware di `main.py`:

```python
from starlette.middleware.sessions import SessionMiddleware

app.add_middleware(SessionMiddleware, secret_key="ganti-dengan-secret-panjang-aman")
```

---

## 5. Penggunaan di Route

```python
from fastapi import Depends
from dependencies.iam_auth import require_iam, require_permission

@app.get("/dashboard")
async def dashboard(iam: dict = Depends(require_iam)):
    user = iam["user"]
    return {"nama": user["name"], "nip": user["nip"]}


@app.post("/absensi")
async def buat_absensi(iam: dict = Depends(require_permission("absensi:create"))):
    user = iam["user"]
    # proses absensi...
    return {"status": "ok"}
```

---

## 6. Logout

```python
@app.post("/logout")
async def logout(request: Request):
    token = request.session.get("iam_token")

    if token:
        path    = "/api/v1/iam/logout"
        headers = build_iam_headers("POST", path)
        headers["Authorization"] = f"Bearer {token}"

        async with httpx.AsyncClient() as client:
            await client.post(IAM_URL + path, headers=headers)

    request.session.pop("iam_token", None)
    request.session.pop("iam_expires_at", None)

    return RedirectResponse("/login")
```
