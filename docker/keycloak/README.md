# Keycloak Development Environment

Docker Compose setup untuk Keycloak + PostgreSQL untuk development environment.

## Prerequisites

- Docker & Docker Compose
- Port 9080 available (Keycloak UI - 8080 internal)
- Port 5432 available (PostgreSQL - internal only)

## Quick Start

```bash
# 1. Navigate ke directory
cd docker/keycloak

# 2. Start containers
docker compose up -d

# 3. Tunggu sampai ready (~60 detik)
docker compose logs -f keycloak
# Tekan Ctrl+C setelah seeing "started in x.xxx seconds"

# 4. Akses Keycloak Admin Console
open http://localhost:9080
```

## Initial Setup (First Time)

### 1. Create Admin Account
Pada first-time setup, Keycloak akan menampilkan wizard untuk membuat admin account:
- Username: `admin` (atau gunakan KEYCLOAK_ADMIN_USER dari .env)
- Password: sesuai yang di-set di .env

### 2. Create Realm "kepegawaian"

1. Klik dropdown "Master" di kiri atas
2. Pilih "Create realm"
3. Fill in:
   - Name: `kepegawaian`
   - Display name: `Kepegawaian Apps`
   - Enabled: ON
4. Klik "Create"

### 3. Create Client untuk kepegawaian-apps

1. Di realm "kepegawaian", masuk ke **Clients**
2. Klik **Create client**
3. Fill in:
   - Client ID: `kepegawaian-apps`
   - Client type: OpenID Connect
   - Name: Kepegawaian Applications
4. Klik **Next**
5. Enable **Client authentication**: ON
6. Enable **Authorization**: ON
7. Valid redirect URIs:
   - `http://localhost:8001/*`
   - `http://localhost:8001/oauth/callback/*`
8. Klik **Save**

### 4. Create Role "admin"

1. Di realm "kepegawaian", masuk ke **Realm roles**
2. Klik **Create role**
3. Role name: `admin`
4. Klik **Save**

### 5. Create Service Account Client (untuk M2M)

1. Di Clients, klik **Create client**
2. Fill in:
   - Client ID: `kepegawaian-service`
   - Client type: OpenID Connect
   - Name: Kepegawaian Service Account
3. Klik **Next**
4. Enable **Client authentication**: ON
5. Enable **Service accounts roles**: ON
6. Valid redirect URIs:
   - `http://localhost:8001/*`
7. Klik **Save**

## Configuration untuk Laravel App

Set environment variables di `.env` Laravel:

```env
# Keycloak Configuration
KEYCLOAK_BASE_URL=http://localhost:9080
KEYCLOAK_REALM=kepegawaian
KEYCLOAK_CLIENT_ID=kepegawaian-apps
KEYCLOAK_CLIENT_SECRET=<secret dari Keycloak client>

# Migration Mode
IAM_MIGRATION_MODE=dual
```

## Commands

```bash
# Start
docker compose up -d

# Stop
docker compose down

# Stop + remove volumes (clean slate)
docker compose down -v

# View logs
docker compose logs -f

# Restart
docker compose restart

# Rebuild
docker compose down && docker compose up -d --build
```

## Data Persistence

Data PostgreSQL tersimpan di `./data/` directory. Untuk backup:

```bash
# Backup database
docker compose exec postgres pg_dump -U keycloak keycloak > backup.sql

# Restore database
cat backup.sql | docker compose exec -T postgres psql -U keycloak keycloak
```

## Troubleshooting

### Keycloak tidak mau start

```bash
# Check logs
docker compose logs keycloak

# Common issue: PostgreSQL belum ready
# Solution: tunggu atau restart
docker compose restart keycloak
```

### Port conflict

```bash
# Check what's using port 8080
lsof -i :8080

# Ubah port di docker-compose.yml jika perlu
```

### Reset everything

```bash
docker compose down -v
rm -rf data/*
docker compose up -d
```

## Production Deployment Notes

Sebelum deploy ke production:

1. ✅ Ganti credentials di `.env`
2. ✅ Gunakan PostgreSQL Debian variant (lebih tested)
3. ✅ Setup HTTPS dengan reverse proxy (nginx/traefik)
4. ✅ Enable Keycloak healthcheck production
5. ✅ Setup proper backup strategy

## Links

- [Keycloak Documentation](https://www.keycloak.org/guides)
- [Keycloak Docker Image](https://hub.docker.com/r/keycloak/keycloak)
- [PostgreSQL Docker](https://hub.docker.com/_/postgres)