# Техническое задание: ClickShot Metrika v0.1

## 1. Цель проекта

Разработать собственную систему веб-аналитики, которая подключается на сайт клиента одной строкой JavaScript-кода и собирает агрегированную статистику без cookie, fingerprint и пользовательских идентификаторов.

Основной домен системы:

```text
https://metrika.clickshot.ru
```

Каталог проекта на сервере:

```text
/var/www/metrika.clickshot.ru
```

Первый тестовый сайт:

```text
https://neural-courses.ru
```

ID первого сайта:

```text
neural_courses
```

Код подключения:

```html
<script src="https://metrika.clickshot.ru/counter.js?id=neural_courses" async></script>
```

## 2. Технологии

- Ubuntu 24.04;
- Caddy;
- PHP 8.3;
- PHP-FPM;
- SQLite;
- чистый JavaScript;
- HTML/CSS без обязательного frontend-фреймворка.

## 3. Основные требования

### 3.1. Подключение

На сайте клиента должен использоваться только один тег:

```html
<script src="https://metrika.clickshot.ru/counter.js?id=SITE_ID" async></script>
```

Других файлов или настроек на стороне клиента не требуется.

### 3.2. Собираемые данные

Разрешено собирать:

- ID сайта;
- путь страницы без query-параметров;
- домен источника перехода;
- дату;
- час;
- количество просмотров;
- приблизительное количество визитов.

### 3.3. Запрещённые данные

Приложение не должно сохранять:

- IP-адрес;
- cookie;
- localStorage ID;
- пользовательский ID;
- fingerprint;
- User-Agent;
- разрешение экрана;
- полный URL источника;
- query-параметры страницы;
- email, телефон и другие формы персональных данных.

### 3.4. Подсчёт визита

Визит определяется приблизительно.

Допускается хранение в sessionStorage только булевого признака начала визита в текущей вкладке.

Запрещено создавать случайный идентификатор пользователя или визита.

## 4. Архитектура

### Клиентская часть

`counter.js`:

- считывает `SITE_ID`;
- получает `location.pathname`;
- получает `document.referrer`;
- определяет новый визит;
- отправляет POST-запрос в `/api/hit.php`;
- использует `sendBeacon`, при отсутствии — `fetch`;
- работает асинхронно;
- не блокирует загрузку страницы.

### Серверная часть

`api/hit.php`:

- принимает POST JSON;
- проверяет SITE_ID;
- проверяет Origin или Referer;
- разрешает отправку только с доменов, привязанных к сайту;
- нормализует путь;
- сохраняет только домен referrer;
- обновляет агрегаты SQLite;
- возвращает HTTP 204.

`api/stats.php`:

- требует авторизацию;
- принимает `site` и `days`;
- возвращает статистику за 7, 30, 90 или 365 дней;
- возвращает просмотры, визиты, глубину, динамику, страницы и источники.

`api/sites.php`:

- требует авторизацию;
- GET — список сайтов;
- POST — создание нового сайта;
- валидирует ID, название и домены.

`health.php`:

- требует авторизацию;
- проверяет PHP;
- проверяет pdo_sqlite;
- проверяет sqlite3;
- проверяет права на storage;
- проверяет подключение к базе.

## 5. База данных

### sites

- id TEXT PRIMARY KEY;
- name TEXT;
- domains_json TEXT;
- created_at TEXT;
- is_active INTEGER.

### daily_stats

- site_id;
- stat_date;
- pageviews;
- visits.

Уникальный ключ:

```text
site_id + stat_date
```

### hourly_stats

- site_id;
- stat_hour;
- pageviews;
- visits.

### page_daily_stats

- site_id;
- stat_date;
- path;
- pageviews;
- visits.

### referrer_daily_stats

- site_id;
- stat_date;
- referrer;
- pageviews;
- visits.

## 6. Дашборд

Дашборд доступен:

```text
https://metrika.clickshot.ru/
```

Авторизация:

- HTTP Basic Auth;
- логин `admin`;
- пароль из `METRIKA_DASHBOARD_PASSWORD`.

### Виджеты

- просмотры;
- приблизительные визиты;
- страниц за визит;
- график по дням;
- топ страниц;
- топ источников.

### Фильтры

- выбор сайта;
- 7 дней;
- 30 дней;
- 90 дней;
- 365 дней.

### Управление сайтами

Форма:

- ID сайта;
- название;
- список доменов через запятую.

После создания должен формироваться готовый код подключения.

## 7. Безопасность

- проверка Origin;
- проверка Referer как fallback;
- разрешённые домены хранятся в БД;
- SQL только через prepared statements;
- пароль не хранить в репозитории;
- SQLite вне public;
- ограничение размера JSON-запроса;
- очистка и нормализация входных данных;
- не включать Caddy access log для домена аналитики.

## 8. Развёртывание

Проект размещается:

```text
/var/www/metrika.clickshot.ru
```

Public root:

```text
/var/www/metrika.clickshot.ru/public
```

SQLite:

```text
/var/www/metrika.clickshot.ru/storage/metrika.sqlite
```

Переменные окружения:

```ini
env[METRIKA_DASHBOARD_PASSWORD] = "..."
env[METRIKA_TIMEZONE] = "Europe/Moscow"
env[METRIKA_DB_PATH] = "/var/www/metrika.clickshot.ru/storage/metrika.sqlite"
```

## 9. Caddy

```caddyfile
metrika.clickshot.ru {
    root * /var/www/metrika.clickshot.ru/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server

    @health path /health
    rewrite @health /health.php
}
```

Access log для данного хоста не включать.

## 10. Критерии приёмки

1. `counter.js` отдаётся с HTTP 200.
2. POST от `neural-courses.ru` возвращает HTTP 204.
3. POST от чужого домена отклоняется.
4. После визита данные появляются в дашборде.
5. Фильтры периода работают.
6. Новый сайт создаётся через интерфейс.
7. Для нового сайта формируется корректный код подключения.
8. В БД отсутствуют IP, cookie, User-Agent и идентификаторы пользователей.
9. `/health` показывает успешное подключение SQLite.
10. После перезапуска PHP-FPM данные сохраняются.

## 11. Ограничения v0.1

- нет уникальных пользователей;
- нет Webvisor;
- нет событий и целей;
- нет UTM-аналитики;
- нет географии;
- нет устройств и браузеров;
- визит считается приблизительно по вкладке браузера.

## 12. Возможное развитие v0.2

- цели и события без идентификаторов;
- экспорт CSV;
- график по часам;
- удаление и редактирование сайтов;
- токены доступа вместо Basic Auth;
- роли пользователей;
- архивирование старой статистики;
- резервное копирование SQLite;
- rate limiting.
