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

Pierwsze uruchomienie pobierze obrazy i zbuduje serwisy `app` i `queue` (PHP). Poczekaj, aż MySQL i RabbitMQ przejdą healthcheck (kilkadziesiąt sekund). Kontener **`queue`** uruchamia worker kolejki (`php artisan queue:work`) — patrz [Kolejki](#7-kolejki-importów).

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

### 7. Kolejki (importów)

Import plików (CSV / JSON / XML) idzie przez kolejkę Laravel (`ShouldQueue`, m.in. `ProcessImportJob`). Żeby **upload HTTP** nie czekał na przetworzenie całego pliku, w `backend/.env` ustaw **`QUEUE_CONNECTION=database`** (tak jest w `backend/.env.example`). Po migracjach powstaną m.in. tabele `jobs` i `job_batches`.

**Zalecane (Docker):** razem ze stackiem startuje serwis **`queue`** — osobny kontener z `php artisan queue:work --sleep=3 --tries=3 --timeout=0`. Nie musisz nic uruchamiać ręcznie.

```bash
# status / logi workera
docker compose ps queue
docker compose logs -f queue
```

**Uwaga:** nie uruchamiaj wtedy drugiego workera w tle w kontenerze `app` (`docker compose exec app php artisan queue:work`), bo zadania mogłyby się **podwójnie** pobierać z kolejki. Jeśli wolisz tylko ręczny worker, zatrzymaj serwis:

```bash
docker compose stop queue
docker compose exec app php artisan queue:work
```

**Bez Dockera:** w katalogu `backend` uruchom `php artisan queue:work` w osobnym terminalu (z tym samym `QUEUE_CONNECTION` i dostępem do bazy co aplikacja).

### 8. Generowanie plików testowych do importu (JSON / XML / CSV)

Artisan udostępnia trzy komendy z tymi samymi losowymi danymi: pola `transaction_id`, `account_number`, `transaction_date`, `amount`, `currency`. Numery kont to losowe polskie IBAN z poprawnym checksum (zgodne z walidacją w aplikacji). Opcja **`--limit`** ustawia liczbę rekordów (domyślnie `10000`).

| Format | Komenda | Domyślny plik wyjściowy |
|--------|---------|-------------------------|
| JSON (tablica obiektów) | `import:generate-json-file` | `exports/import.json` |
| XML (`<transactions>` → `<transaction>` …) | `import:generate-xml-file` | `exports/import.xml` |
| CSV (nagłówek + wiersze) | `import:generate-csv-file` | `exports/import.csv` |

**Docker — plik poza kontenerem (na hoście):** w `docker-compose.yml` katalog `./exports` z **rootu repozytorium** jest zamontowany jako `/var/www/html/exports` w serwisie `app`. Domyślne ścieżki komend to `exports/import.*` wewnątrz aplikacji, więc na hoście pliki trafiają do **`exports/`** obok `backend/`, `frontend/` i `docker-compose.yml` — bez `docker cp`.

W kontenerze to np. `/var/www/html/exports/import.json` (analogicznie `.xml` / `.csv`).

```bash
# z hosta, z katalogu głównego repozytorium:
docker compose exec app php artisan import:generate-json-file
docker compose exec app php artisan import:generate-xml-file
docker compose exec app php artisan import:generate-csv-file

# mniejszy plik (szybszy test):
docker compose exec app php artisan import:generate-json-file --limit=5000
docker compose exec app php artisan import:generate-xml-file --limit=5000
docker compose exec app php artisan import:generate-csv-file --limit=5000
```

Po **pierwszej** zmianie `docker-compose.yml` z nowym volume zrób restart kontenera `app` (np. `docker compose up -d`).

**Inna ścieżka** — pierwszy argument jest względem katalogu `backend/` w kontenerze (`/var/www/html/`). Żeby zapisać nadal „na zewnątrz” w `./exports` na hoście, używaj prefiksu `exports/`:

```bash
docker compose exec app php artisan import:generate-json-file exports/moj-plik.json
docker compose exec app php artisan import:generate-xml-file exports/moj-plik.xml
docker compose exec app php artisan import:generate-csv-file exports/moj-plik.csv
```

Bez Dockera (uruchamiasz `artisan` z katalogu `backend/`) domyślnie powstają pliki w **`backend/exports/`** (`import.json`, `import.xml`, `import.csv`).

```bash
cd backend
php artisan import:generate-json-file
php artisan import:generate-xml-file
php artisan import:generate-csv-file
php artisan import:generate-json-file --limit=1000
```

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
