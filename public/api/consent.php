<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/lib.php';
header('Cache-Control: no-store');

try {
    $siteId = clean_site_id($_GET['site'] ?? '');
    $site = site_by_id($siteId);
    if (!$site || !$site['slide_cookie']['enabled']) respond_json(404, ['ok' => false]);
    require_site_origin($site['domains']);
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Vary: Origin');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        http_response_code(204);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond_json(405, ['ok' => false]);
    $input = json_input();
    $action = input_string($input['action'] ?? '', 'action', 20);
    if (!in_array($action, ['verify', 'granted', 'denied', 'withdrawn'], true)) {
        throw new InvalidArgumentException('Некорректное решение');
    }
    $previous = input_string($input['receipt'] ?? '', 'receipt', 64);
    if ($previous !== '' && !preg_match('/^[a-f0-9]{64}$/', $previous)) throw new InvalidArgumentException('Некорректное подтверждение');

    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO consent_budget(site_id,minute,hits) VALUES(?,?,1)
        ON CONFLICT(site_id) DO UPDATE SET minute=excluded.minute,
        hits=CASE WHEN minute=excluded.minute THEN hits+1 ELSE 1 END');
    $stmt->execute([$siteId, intdiv(time(), 60)]);
    $stmt = $pdo->prepare('SELECT hits FROM consent_budget WHERE site_id=?');
    $stmt->execute([$siteId]);
    if ((int)$stmt->fetchColumn() > 6000) throw new OverflowException();
    // Re-read under the writer lock, so an admin update cannot race a grant.
    $site = site_by_id($siteId);
    if (!$site || !$site['slide_cookie']['enabled']) throw new InvalidArgumentException('Баннер выключен');
    $settings = $site['slide_cookie'];
    $document = consent_document($settings);
    $now = time();
    $stmt = $pdo->prepare('SELECT * FROM consent_receipts WHERE site_id=? AND receipt=?');
    $stmt->execute([$siteId, $previous]);
    $prior = $stmt->fetch();
    if ($action === 'verify') {
        $valid = $prior && $prior['decision'] === 'granted' && $prior['revoked_at'] === null
            && (int)$prior['expires_at'] > $now && hash_equals($document['hash'], $prior['document_hash'])
            && consent_ready($settings) && $settings['block_metrika'];
        $pdo->commit();
        respond_json(200, ['ok' => true, 'valid' => (bool)$valid]);
    }
    $method = input_string($input['method'] ?? '', 'method', 20);
    if (!in_array($method, ['slider', 'keyboard', 'link'], true)) throw new InvalidArgumentException('Некорректный способ выбора');
    if ($action === 'granted') {
        if (!consent_ready($settings) || !$settings['block_metrika']) throw new InvalidArgumentException('Настройки согласия не заполнены');
        if (($input['version'] ?? null) !== $document['version'] || ($input['hash'] ?? '') !== $document['hash']) {
            $pdo->rollBack();
            respond_json(409, ['ok' => false, 'error' => 'Условия изменились. Обновите страницу перед согласием.']);
        }
        if ($method === 'link') throw new InvalidArgumentException('Согласие принимается слайдером');
    }
    if ($prior) {
        $pdo->prepare('UPDATE consent_receipts SET revoked_at=? WHERE site_id=? AND receipt=? AND revoked_at IS NULL')
            ->execute([$now, $siteId, $previous]);
    }
    $pdo->prepare('INSERT OR IGNORE INTO consent_documents(site_id,document_hash,version,document_json,created_at) VALUES(?,?,?,?,?)')
        ->execute([$siteId, $document['hash'], $document['version'], json_encode($document, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $now]);
    $receipt = bin2hex(random_bytes(32));
    $expires = $now + $settings['consent_days'] * 86400;
    $pdo->prepare('INSERT INTO consent_receipts(site_id,receipt,document_hash,version,decision,method,created_at,expires_at,previous_receipt)
        VALUES(?,?,?,?,?,?,?,?,?)')
        ->execute([$siteId, $receipt, $document['hash'], $document['version'], $action, $method, $now, $expires, $prior ? $previous : null]);
    prune_consent($pdo);
    $pdo->commit();
    respond_json(200, ['ok' => true, 'choice' => [
        'format' => 1, 'decision' => $action, 'categories' => ['analytics' => $action === 'granted'],
        'receipt' => $receipt, 'version' => $document['version'], 'hash' => $document['hash'],
        'created_at' => $now * 1000, 'expires_at' => $expires * 1000,
    ]]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    api_error($error);
}
