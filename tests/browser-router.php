<?php
// Local test fixture only. Not inside the production document root.
declare(strict_types=1);
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/privacy') { header('Content-Type: text/html; charset=utf-8'); echo '<h1>Тестовая политика</h1>'; return true; }
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/fixture') return false;
$id = ($_GET['client'] ?? '') === 'other' ? 'browser_other' : ((($_GET['mode'] ?? '') === 'conditional') ? 'browser_conditional' : 'browser_site');
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html><head><meta charset="utf-8"><title>ClickShot consent fixture</title>
<script referrerpolicy="no-referrer" src="/api/loader.php?id=<?= $id ?>"></script>
<script>
window.fixtureGoals = [];
window.ym = function () { (window.ym.a = window.ym.a || []).push(arguments); };
window.ym(12345, 'reachGoal', 'fixture_goal');
if (new URLSearchParams(location.search).get('mode') === 'attribute') {
const tag = document.createElement('script');
if (new URLSearchParams(location.search).get('mode') === 'attribute') {
  tag.setAttribute('src', 'https://mc.yandex.ru/metrika/tag.js?id=12345');
} else {
  tag.src = 'https://mc.yandex.ru/metrika/tag.js?id=12345';
}
document.head.appendChild(tag);
}
</script></head><body><h1>Страница клиента</h1><a href="/fixture">Ссылка страницы</a><button id="client-action">Действие</button></body></html>
