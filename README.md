# ClickShot Metrika v0.1

## Сервер

```text
https://metrika.clickshot.ru
```

## Каталог проекта

```text
/var/www/metrika.clickshot.ru
```

## Тестовый сайт

```text
neural-courses.ru
```

## Код подключения

```html
<script src="https://metrika.clickshot.ru/counter.js?id=neural_courses" async></script>
```

## Установка

### 1. Установить зависимости

```bash
sudo apt update
sudo apt install -y php8.3-fpm php8.3-sqlite3 unzip
```

### 2. Создать каталог

```bash
sudo mkdir -p /var/www/metrika.clickshot.ru
```

### 3. Распаковать архив

```bash
sudo unzip metrika-clickshot-v0.1.zip -d /tmp/metrika-clickshot
sudo cp -R /tmp/metrika-clickshot/metrika-clickshot-v0.1/. /var/www/metrika.clickshot.ru/
```

### 4. Права

```bash
sudo chown -R root:www-data /var/www/metrika.clickshot.ru
sudo chown -R www-data:www-data /var/www/metrika.clickshot.ru/storage
sudo chmod 770 /var/www/metrika.clickshot.ru/storage
```

### 5. Настроить PHP-FPM

Открыть:

```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

Добавить:

```ini
env[METRIKA_DASHBOARD_PASSWORD] = "СЮДА_ДЛИННЫЙ_ПАРОЛЬ"
env[METRIKA_TIMEZONE] = "Europe/Moscow"
env[METRIKA_DB_PATH] = "/var/www/metrika.clickshot.ru/storage/metrika.sqlite"
```

Перезапустить:

```bash
sudo systemctl restart php8.3-fpm
```

### 6. Настроить Caddy

Добавить в `/etc/caddy/Caddyfile`:

```caddyfile
metrika.clickshot.ru {
    root * /var/www/metrika.clickshot.ru/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server

    @health path /health
    rewrite @health /health.php
}
```

Проверить:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

### 7. Проверить DNS

A-запись:

```text
metrika.clickshot.ru → IP сервера
```

### 8. Проверить работу

```bash
curl -I https://metrika.clickshot.ru/counter.js
```

Должно быть `200 OK`.

Проверка API:

```bash
curl -i 'https://metrika.clickshot.ru/api/hit.php' \
  -X POST \
  -H 'Origin: https://neural-courses.ru' \
  -H 'Content-Type: application/json' \
  --data '{"site":"neural_courses","path":"/test/","referrer":"https://yandex.ru/search/","newVisit":true}'
```

Ожидается:

```text
HTTP/2 204
```

### 9. Открыть дашборд

```text
https://metrika.clickshot.ru/
```

Логин:

```text
admin
```

Пароль — значение `METRIKA_DASHBOARD_PASSWORD`.

### 10. Диагностика

```text
https://metrika.clickshot.ru/health
```

## Что собирается

- путь страницы без query;
- домен источника;
- дата и час;
- просмотры;
- приблизительные визиты.

## Что не собирается приложением

- IP;
- cookie;
- fingerprint;
- User-Agent;
- разрешение экрана;
- пользовательский ID;
- полный referrer URL;
- query-параметры.

## Добавление сайта

Можно через форму в дашборде.

После создания система покажет код:

```html
<script src="https://metrika.clickshot.ru/counter.js?id=SITE_ID" async></script>
```

## Важное замечание

Caddy, CDN или хостинг могут отдельно сохранять IP в access logs.
Для домена `metrika.clickshot.ru` не включайте access log.
