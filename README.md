# rekrutacja-gdynia

Stack: **Laravel 12** (API przez nginx + PHP-FPM), **MySQL**, **RabbitMQ**, **Vue 3 + Vite**.

## Wymagania

- [Docker](https://docs.docker.com/get-docker/) i Docker Compose (v2)
- Wolne porty domyślne: **8080** (API), **5173** (Vite), **3306** (MySQL), **5672** / **15672** (RabbitMQ)

## Uruchomienie krok po kroku (Docker)

### 1. Repozytorium i katalog roboczy

```bash
cd rekrutacja-gdynia
```

### 2. Konfiguracja środowiska Laravel

Skopiuj plik przykładowy i wygeneruj klucz aplikacji (możesz zrobić to przed lub tuż po starcie kontenerów — poniżej wersja po starcie `app`):

```bash
cp backend/.env.example backend/.env
```

W `backend/.env` dla pracy w Dockerze powinny zostać m.in.:

- `APP_URL=http://localhost:8080`
- `DB_HOST=mysql` oraz dane bazy zgodne z `docker-compose.yml` (domyślnie baza `laravel`, użytkownik `laravel`, hasło `secret`)

### 3. Start kontenerów

Z katalogu głównego projektu:

```bash
docker compose up -d --build
```

Pierwsze uruchomienie pobierze obrazy i zbuduje serwis `app` (PHP). Poczekaj, aż MySQL i RabbitMQ przejdą healthcheck (kilkadziesiąt sekund).

### 4. Zależności PHP i migracje

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### 5. Front-end (Vite)

Jednorazowo zainstaluj pakiety, potem uruchom serwer deweloperski nasłuchujący na wszystkich interfejsach (żeby był dostępny z hosta):

```bash
docker compose exec frontend sh -lc "npm install && npm run dev -- --host 0.0.0.0"
```

Zostaw ten proces w terminalu w czasie pracy. Po zmianach w kodzie frontu Vite zwykle sam przeładuje stronę.

### 6. Adresy

| Usługa | URL / port |
|--------|------------|
| API (Laravel przez nginx) | http://localhost:8080 |
| Vite (Vue) | http://localhost:5173 |
| Panel RabbitMQ (management) | http://localhost:15672 — domyślnie użytkownik `laravel`, hasło `secret` (jak w compose) |

### 7. Kolejki (opcjonalnie)

Jeśli używasz zadań w tle (`ShouldQueue` itd.), uruchom worker w osobnym terminalu:

```bash
docker compose exec app php artisan queue:work
```

(W `backend/.env.example` domyślnie jest `QUEUE_CONNECTION=database` — dostosuj do swojej konfiguracji.)

### Zatrzymanie

```bash
docker compose down
```

Dane MySQL i RabbitMQ są w wolumenach Dockera (`mysql_data`, `rabbitmq_data`) — `down` bez `-v` ich nie usuwa.

## Porty i zmienne

Domyślne porty można nadpisać zmiennymi środowiskowymi przy starcie (np. w pliku `.env` obok `docker-compose.yml`):

- `NGINX_PORT` — API (domyślnie 8080)
- `VITE_PORT` — Vite (domyślnie 5173)
- `MYSQL_PORT`, `RABBITMQ_PORT`, `RABBITMQ_MANAGEMENT_PORT`

## Rozwój bez Dockera (skrót)

Jeśli masz lokalnie PHP 8.2+, Composer i Node: w katalogu `backend` możesz użyć `composer run setup` (instalacja, `.env`, migracje, zasoby) oraz `composer run dev` — wtedy dostosuj `DB_HOST` i inne wpisy w `.env` do swojego MySQL (np. `127.0.0.1`). Ten README opisuje ścieżkę z Dockerem jako główną.
