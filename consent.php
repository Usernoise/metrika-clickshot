<?php
declare(strict_types=1);

// Consent evidence is deliberately separate from anonymous analytics.
function migrate_consent(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS site_consent_settings (
    site_id TEXT PRIMARY KEY,
    settings_json TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS consent_documents (
    site_id TEXT NOT NULL,
    document_hash TEXT NOT NULL,
    version INTEGER NOT NULL,
    document_json TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    PRIMARY KEY (site_id, document_hash)
);
CREATE TABLE IF NOT EXISTS consent_receipts (
    site_id TEXT NOT NULL,
    receipt TEXT PRIMARY KEY,
    document_hash TEXT NOT NULL,
    version INTEGER NOT NULL,
    decision TEXT NOT NULL CHECK(decision IN ('granted','denied','withdrawn')),
    method TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    revoked_at INTEGER,
    previous_receipt TEXT
);
CREATE INDEX IF NOT EXISTS idx_consent_receipts_time ON consent_receipts(created_at);
CREATE INDEX IF NOT EXISTS idx_consent_documents_time ON consent_documents(created_at);
CREATE INDEX IF NOT EXISTS idx_consent_receipts_site_time ON consent_receipts(site_id, created_at);
CREATE TABLE IF NOT EXISTS consent_budget (
    site_id TEXT PRIMARY KEY,
    minute INTEGER NOT NULL,
    hits INTEGER NOT NULL
);
SQL);
}

function consent_defaults(): array
{
    return ['operator_name' => '', 'operator_address' => '', 'operator_email' => '',
        'consent_text' => '', 'consent_days' => 180, 'strict_preconsent' => true];
}

function consent_details(string $siteId): array
{
    $stmt = db()->prepare('SELECT settings_json FROM site_consent_settings WHERE site_id=?');
    $stmt->execute([$siteId]);
    return array_replace(consent_defaults(), json_decode($stmt->fetchColumn() ?: '{}', true) ?: []);
}

function consent_policy_url_valid(string $url): bool
{
    if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $url) || str_starts_with($url, '//')) return false;
    if (preg_match('~^https?://~i', $url)) {
        $parts = parse_url($url);
        return $parts !== false && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
    }
    return preg_match('~^(?:/(?!/)|\./|\.\./)[^?#]+~', $url) === 1;
}

function consent_ready(array $settings): bool
{
    return $settings['operator_name'] !== '' && $settings['operator_address'] !== ''
        && filter_var($settings['operator_email'], FILTER_VALIDATE_EMAIL) !== false
        && consent_policy_url_valid($settings['policy_url'])
        && preg_match('/^[1-9][0-9]{0,14}$/', $settings['ym_counter']) === 1;
}

function consent_missing(array $settings): array
{
    $missing = [];
    if ($settings['operator_name'] === '') $missing[] = 'укажите оператора ПД';
    if ($settings['operator_address'] === '') $missing[] = 'укажите адрес оператора';
    if (filter_var($settings['operator_email'], FILTER_VALIDATE_EMAIL) === false) $missing[] = 'укажите корректный email (латиницей)';
    if (!consent_policy_url_valid($settings['policy_url'])) $missing[] = 'укажите ссылку на политику: /privacy или https://site.ru/privacy';
    if (preg_match('/^[1-9][0-9]{0,14}$/', $settings['ym_counter']) !== 1) $missing[] = 'укажите ID Метрики — только цифры';
    if (!$settings['block_metrika']) $missing[] = 'включите блокировку Метрики до согласия';
    return $missing;
}

function clean_consent_details(array $input, array $settings, ?array $fallback): array
{
    $settings = array_replace(consent_defaults(), $settings);
    foreach (['operator_name' => 300, 'operator_address' => 500, 'operator_email' => 254, 'consent_text' => 3000] as $key => $limit) {
        if (array_key_exists($key, $input)) $settings[$key] = input_string($input[$key], $key, $limit);
    }
    if (array_key_exists('consent_days', $input)) {
        if (!is_int($input['consent_days']) || $input['consent_days'] < 1 || $input['consent_days'] > 365) {
            throw new InvalidArgumentException('Срок согласия: от 1 до 365 дней');
        }
        $settings['consent_days'] = $input['consent_days'];
    }
    if (array_key_exists('strict_preconsent', $input)) $settings['strict_preconsent'] = input_bool($input['strict_preconsent']);
    if ($settings['policy_url'] !== '' && !consent_policy_url_valid($settings['policy_url'])) {
        throw new InvalidArgumentException('Укажите рабочий путь или https-ссылку на политику, без заглушки #');
    }
    if ($settings['enabled']) {
        $missing = consent_missing($settings);
        if ($missing) throw new InvalidArgumentException('Slide Cookie не сохранён: ' . implode('; ', $missing));
    }
    if ($fallback !== null) {
        $material = ['enabled', 'policy_url', 'ym_counter', 'block_metrika', 'operator_name',
            'operator_address', 'operator_email', 'consent_text', 'consent_days', 'strict_preconsent'];
        foreach ($material as $key) {
            if (($settings[$key] ?? null) !== ($fallback[$key] ?? consent_defaults()[$key] ?? null)) {
                $settings['version'] = max($settings['version'], $fallback['version'] + 1);
                break;
            }
        }
    }
    return $settings;
}

function save_consent_details(string $siteId, array $settings): void
{
    $details = array_intersect_key($settings, consent_defaults());
    db()->prepare('INSERT INTO site_consent_settings(site_id,settings_json) VALUES(?,?)
        ON CONFLICT(site_id) DO UPDATE SET settings_json=excluded.settings_json')
        ->execute([$siteId, json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
}

function consent_document(array $settings): array
{
    $name = $settings['operator_name'];
    $address = $settings['operator_address'];
    $email = $settings['operator_email'];
    $days = $settings['consent_days'];
    $text = "Согласие на обработку персональных данных для веб-аналитики\n\n"
        . "Перемещая слайдер до конца либо нажимая Enter или пробел на слайдере, я свободно даю указанному ниже оператору согласие на следующую обработку.\n\nОператор: {$name}. Адрес: {$address}. Контакт: {$email}.\n\n"
        . "Цель: статистический анализ посещаемости и использования сайта с помощью Яндекс.Метрики, счётчик {$settings['ym_counter']}.\n"
        . "Данные: cookie-идентификаторы, IP-адрес, сведения о браузере и устройстве, время посещения, просмотренные страницы и события взаимодействия.\n"
        . "Получатель данных аналитики: ООО «ЯНДЕКС», сервис Яндекс.Метрика. Вебвизор, карта кликов, ecommerce и отслеживание ссылок в управляемом подключении выключены.\n"
        . "Операции: автоматизированные сбор, запись, систематизация, накопление, хранение, использование, передача указанному получателю, блокирование, удаление и уничтожение.\n"
        . "Согласие действует {$days} дней с момента предоставления, если не будет отозвано раньше. Сроки отдельных cookie описаны в политике оператора и документации сервиса.\n"
        . "Отзыв: откройте ссылку «Настройки cookie» и выберите «Отозвать согласие» либо обратитесь по адресу {$email}. После отзыва новые события аналитики не отправляются; удаление ранее переданных данных рассматривается оператором отдельно.\n"
        . "Подтверждение выбора (решение, дата, редакция согласия и случайный номер подтверждения) сохраняется в отдельном журнале согласий этого сайта. IP-адрес и отпечаток устройства в журнал не записываются. Срок хранения подтверждения — " . app_config()['consent_retention_days'] . " дней.\n"
        . "Отказ от аналитики не препятствует использованию сайта. Это согласие не распространяется на рекламу, рассылки, обработку заявок и другие цели.\n";
    if ($settings['consent_text'] !== '') $text .= "\nДополнительные условия оператора:\n" . $settings['consent_text'] . "\n";
    $document = ['format' => 1, 'version' => $settings['version'], 'purpose' => 'analytics',
        'policy_url' => $settings['policy_url'], 'operator' => $name, 'text' => $text, 'days' => $days];
    $document['hash'] = hash('sha256', json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return $document;
}

function prune_consent(PDO $pdo): int
{
    $cutoff = time() - app_config()['consent_retention_days'] * 86400;
    $stmt = $pdo->prepare('DELETE FROM consent_receipts WHERE created_at < ?');
    $stmt->execute([$cutoff]);
    $count = $stmt->rowCount();
    $pdo->prepare('DELETE FROM consent_documents WHERE created_at < ? AND NOT EXISTS
        (SELECT 1 FROM consent_receipts r WHERE r.site_id=consent_documents.site_id AND r.document_hash=consent_documents.document_hash)')
        ->execute([$cutoff]);
    return $count;
}
