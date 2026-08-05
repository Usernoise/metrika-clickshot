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

Без Slide Cookie:

```html
<script src="https://metrika.clickshot.ru/counter.js?id=neural_courses" async></script>
```

Со включённым Slide Cookie админка покажет тот же URL без `async`. Его нужно
поместить в `<head>` до сниппета Яндекс.Метрики — тогда баннер успеет
заблокировать её до согласия.

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

    @counter path /counter.js
    uri @counter replace /counter.js /api/loader.php

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

- по настройке сайта: просмотры и дата/час;
- по настройке сайта: шаблон пути страницы без query-параметров;
- по настройке сайта: домен источника, без полного URL;
- по настройке сайта: приблизительные визиты с маркером вкладки в `sessionStorage`;
- по настройке сайта: семейство браузера, ОС и тип устройства.

Технические категории выключены по умолчанию. Полная строка User-Agent не
сохраняется: она сразу сводится к одной из крупных категорий. Пути с UUID,
длинными числовыми ID, токенами и e-mail маскируются как `:id`.

В админке для каждого сайта можно включить или выключить страницы, источники,
приблизительные визиты и технические категории. Новые события начнут
обрабатываться по настройке сразу; уже сохранённые агрегаты не удаляются.

## Slide Cookie

В админке сайта можно включить Slide Cookie и настроить ссылку на политику,
условие показа, цвета, блокировку Яндекс.Метрики и её ID. Настройка «Запросить
согласие заново» меняет версию локального ключа согласия — баннер отобразится
повторно без хранения идентификаторов посетителя на сервере.

При включённом Slide Cookie не используйте `async` и вставьте обновлённый код
в `<head>`. Иначе баннер покажется, но не сможет гарантированно остановить
теги, которые уже успели загрузиться.

## Что не собирается приложением

- IP;
- cookie;
- fingerprint;
- полный User-Agent и версии браузеров/ОС;
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
