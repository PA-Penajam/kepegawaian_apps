# Integrasi Express.js

Panduan ini menjelaskan cara mengintegrasikan aplikasi Express.js dengan SSO kepegawaian-apps.

---

## 1. Konfigurasi Environment

Tambahkan ke `.env`:

```env
IAM_URL=https://kepegawaian.pa-penajam.go.id
IAM_API_KEY=iam_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
IAM_API_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
IAM_APP_SLUG=nama-aplikasi-anda
SESSION_SECRET=ganti-dengan-secret-panjang-aman
```

Install dependency:

```bash
npm install axios express-session dotenv
```

---

## 2. Helper: Hitung HMAC Signature

Buat `src/services/iamSignature.js`:

```js
const crypto = require("crypto");

function buildIamHeaders(method, path, query = {}, body = "") {
  const timestamp = Math.floor(Date.now() / 1000).toString();

  // Sort query parameters A-Z lalu encode
  const sortedQuery = new URLSearchParams(
    Object.entries(query).sort(([a], [b]) => a.localeCompare(b))
  ).toString();

  const bodyHash = crypto
    .createHash("sha256")
    .update(typeof body === "string" ? body : JSON.stringify(body))
    .digest("hex");

  const payload = [
    method.toUpperCase(),
    path,
    sortedQuery,
    bodyHash,
    timestamp,
  ].join(":");

  const signature = crypto
    .createHmac("sha256", process.env.IAM_API_SECRET)
    .update(payload)
    .digest("hex");

  return {
    "X-App-Key":    process.env.IAM_API_KEY,
    "X-Timestamp":  timestamp,
    "X-Signature":  signature,
    "Content-Type": "application/json",
  };
}

module.exports = { buildIamHeaders };
```

---

## 3. Middleware: Validasi Session IAM

Buat `src/middleware/iamAuth.js`:

```js
const axios = require("axios");
const crypto = require("crypto");
const { buildIamHeaders } = require("../services/iamSignature");

// Cache sederhana in-memory (ganti dengan Redis untuk production)
const cache = new Map();

async function fetchIamData(token) {
  const cacheKey = "iam_" + crypto
    .createHash("sha256")
    .update(token + process.env.IAM_API_KEY)
    .digest("hex");

  if (cache.has(cacheKey)) {
    const { data, expiresAt } = cache.get(cacheKey);
    if (Date.now() < expiresAt) return data;
    cache.delete(cacheKey);
  }

  const path    = "/api/v1/iam/validate";
  const headers = {
    ...buildIamHeaders("GET", path),
    Authorization: `Bearer ${token}`,
  };

  try {
    const response = await axios.get(process.env.IAM_URL + path, { headers });
    cache.set(cacheKey, { data: response.data, expiresAt: Date.now() + 60_000 });
    return response.data;
  } catch {
    return null;
  }
}

function requireIam(permission = null) {
  return async (req, res, next) => {
    const token = req.session?.iamToken;

    if (!token) {
      return redirectToSso(req, res);
    }

    const data = await fetchIamData(token);

    if (!data || !data.user) {
      delete req.session.iamToken;
      return redirectToSso(req, res);
    }

    if (permission && !data.permissions?.includes(permission)) {
      return res.status(403).json({ message: "Akses ditolak." });
    }

    // Inject ke req agar bisa diakses di route handler
    req.iamUser        = data.user;
    req.iamRoles       = data.roles;
    req.iamPermissions = data.permissions;

    next();
  };
}

function redirectToSso(req, res) {
  const callback = `${req.protocol}://${req.get("host")}/sso/callback`;
  const ssoUrl   = `${process.env.IAM_URL}/sso/login`
    + `?app=${process.env.IAM_APP_SLUG}`
    + `&redirect=${encodeURIComponent(callback)}`;

  return res.redirect(ssoUrl);
}

module.exports = { requireIam };
```

> **Catatan production:** Ganti `Map` in-memory dengan Redis menggunakan `ioredis` agar cache tidak hilang saat restart dan bisa di-share antar instance.

---

## 4. Route SSO Callback

Tambahkan ke `src/routes/sso.js`:

```js
const express = require("express");
const axios   = require("axios");
const { buildIamHeaders } = require("../services/iamSignature");

const router = express.Router();

router.get("/sso/callback", async (req, res) => {
  const { code } = req.query;

  if (!code || code.length !== 64) {
    return res.status(400).send("SSO code tidak valid.");
  }

  const path    = "/api/v1/iam/exchange-code";
  const body    = JSON.stringify({ code });
  const headers = buildIamHeaders("POST", path, {}, body);

  try {
    const response = await axios.post(
      process.env.IAM_URL + path,
      body,
      { headers }
    );

    const { token, expires_at } = response.data;

    // Simpan token di session server-side
    req.session.iamToken      = token;
    req.session.iamExpiresAt  = expires_at;

    res.redirect("/dashboard");
  } catch {
    res.redirect("/login?error=sso_failed");
  }
});

module.exports = router;
```

Setup session di `src/app.js`:

```js
const session = require("express-session");

app.use(session({
  secret:            process.env.SESSION_SECRET,
  resave:            false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,   // blokir akses dari JavaScript
    secure:   process.env.NODE_ENV === "production",
    maxAge:   8 * 60 * 60 * 1000, // 8 jam
  },
}));
```

---

## 5. Penggunaan di Route

```js
const { requireIam } = require("./middleware/iamAuth");

// Protect seluruh group route
app.use("/dashboard", requireIam(), (req, res) => {
  const { name, nip } = req.iamUser;
  res.json({ nama: name, nip });
});

// Protect dengan cek permission spesifik
app.post("/absensi", requireIam("absensi:create"), (req, res) => {
  // proses absensi...
  res.json({ status: "ok" });
});
```

---

## 6. Logout

```js
const axios = require("axios");
const { buildIamHeaders } = require("./services/iamSignature");

app.post("/logout", async (req, res) => {
  const token = req.session?.iamToken;

  if (token) {
    const path    = "/api/v1/iam/logout";
    const headers = {
      ...buildIamHeaders("POST", path),
      Authorization: `Bearer ${token}`,
    };

    try {
      await axios.post(process.env.IAM_URL + path, {}, { headers });
    } catch {
      // lanjutkan logout lokal meski request ke IAM gagal
    }
  }

  req.session.destroy(() => {
    res.redirect("/login");
  });
});
```
