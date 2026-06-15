# Интеграция с Яндекс.Картами

Веб-приложение для подключения карточки организации на Яндекс.Картах, асинхронной синхронизации отзывов и просмотра рейтинга с пагинацией

## Стек

- Laravel 13, PHP 8.4
- Inertia.js + Vue 3 (Composition API)
- Laravel Sanctum (session auth для Inertia)
- PostgreSQL 18, Redis (очередь и lock)
- nginx, Docker Compose

## Требования

- Docker и Docker Compose
- Node.js 20+ (сборка фронтенда на хосте)

## Запуск

```bash
cp .env.example .env
```

Заполните в `.env` минимум:

- `APP_KEY` - после `docker compose up` выполните `docker compose exec app php artisan key:generate`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `SEED_USER_EMAIL`, `SEED_USER_PASSWORD` - учётные данные demo-пользователя
- при необходимости `APP_PORT` (по умолчанию `8080`)

Переменные парсера и Redis - см. `.env.example`.

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate   # если APP_KEY пустой
docker compose exec app php artisan migrate --seed
npm install
npm run build
```

Приложение: `http://localhost:8080` (или ваш `APP_PORT`).

Синхронизацию выполняет сервис `queue` в Compose. После запуска `docker compose ps` должен показывать статус `Up` у `app`, `nginx`, `postgres`, `redis`, `queue`.

## Вход

Логин и пароль - из `SEED_USER_EMAIL` и `SEED_USER_PASSWORD` в `.env` (задаются при `migrate --seed`).

Страница входа: `/login`.

## Проверка в UI

1. Войти под seed-пользователем.
2. Открыть `/organization`.
3. Вставить ссылку на карточку организации Яндекс.Карт и нажать **Save and sync**.
4. Дождаться статуса синхронизации (`queued` - `running` - `succeeded` или `failed`).
5. Просмотреть название, рейтинг, счётчики оценок и отзывов, список отзывов.
6. При более чем 50 отзывах - переключение страниц (данные из БД, парсер не запускается).
7. Можно сохранить несколько организаций и переключать активную в списке.

Повторная синхронизация - снова **Save and sync** (в том числе после `failed`).

## Парсинг

Официального API у Яндекс.Карт нет. Используется `InternalRequestParser`:

- нормализует URL карточки;
- загружает HTML вкладки отзывов и читает embedded JSON (`state-view`);
- обходит страницы `?page=N` до лимита или пустой выдачи;
- сохраняет организацию и отзывы в PostgreSQL.

Отзывы в UI берутся из БД, а не с Яндекса при каждом открытии страницы: один раз после **Save and sync** job вытягивает до ~600 отзывов, дальше список листается по 50 из PostgreSQL.

Защита Яндекса: HTTP-запросы с настраиваемым User-Agent, повторы при сбоях и пустых страницах, таймауты, lock на organization. Ошибки (недоступная карточка, блокировка, смена формата) попадают в статус `failed` и `last_sync_error` в интерфейсе.

## Полезные команды

```bash
php artisan test
composer phpstan
composer lint
npm run build
```
