<?php
if (!defined('ABSPATH')) { exit; }

function tarhan_site_token()
{
    $value = $_COOKIE['tarhan_browser'] ?? '';
    if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) {
        $value = bin2hex(random_bytes(32));
        setcookie('tarhan_browser', $value, array('expires' => time() + DAY_IN_SECONDS,
            'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'));
        $_COOKIE['tarhan_browser'] = $value;
    }
    return hash_hmac('sha256', $value, wp_salt('nonce'));
}

function tarhan_public_permission($request)
{
    $cookie = $_COOKIE['tarhan_browser'] ?? '';
    $token = $request->get_header('X-Tarhan-Token');
    if (!is_string($cookie) || !preg_match('/^[a-f0-9]{64}$/D', $cookie) ||
        !$token || !hash_equals(hash_hmac('sha256', $cookie, wp_salt('nonce')), $token)) {
        return tarhan_attempt_error('form_expired', 'Обновите страницу и повторите отправку.', 403);
    }
    $origin = $request->get_header('origin');
    $expected = wp_parse_url(home_url());
    $actual = wp_parse_url($origin);
    if ($origin && (!$actual || ($actual['scheme'] ?? '') !== $expected['scheme'] ||
        ($actual['host'] ?? '') !== $expected['host'] || ($actual['port'] ?? null) !== ($expected['port'] ?? null))) {
        return tarhan_attempt_error('form_origin', 'Откройте форму на сайте конкурса.', 403);
    }
    return true;
}

// The remote address is supplied by the web server. Never trust a forwarded header here.
function tarhan_rate_limit($action, $limit, $identity = null)
{
    global $wpdb;
    $identity = $identity ?? ($_SERVER['REMOTE_ADDR'] ?? 'local');
    $key = 'tarhan_rate_' . substr(hash_hmac('sha256', $action . ':' . $identity, wp_salt()), 0, 40);
    if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $key)) !== '1') {
        return tarhan_attempt_error('rate_limit', 'Повторите запрос немного позже.', 429);
    }
    try {
        $count = (int) get_transient($key);
        if ($count >= $limit) { return tarhan_attempt_error('rate_limit', 'Слишком много запросов. Попробуйте через 15 минут.', 429); }
        set_transient($key, $count + 1, 15 * MINUTE_IN_SECONDS);
        return true;
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key)); }
}

function tarhan_private_directory()
{
    $root = defined('TARHAN_PRIVATE_DIR') ? TARHAN_PRIVATE_DIR : dirname(untrailingslashit(ABSPATH)) . '/tarhan-private';
    if (!wp_mkdir_p($root)) { return tarhan_attempt_error('file_storage', 'Хранилище файлов недоступно.', 503); }
    $real = realpath($root);
    $public = realpath(ABSPATH);
    if (!$real || !$public) { return tarhan_attempt_error('file_storage', 'Хранилище файлов недоступно.', 503); }
    $document_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? ABSPATH) ?: $public;
    foreach (array($public, $document_root) as $webroot) {
        $base = strtolower(str_replace('\\', '/', $webroot));
        $path = strtolower(str_replace('\\', '/', $real));
        if ($path === $base || str_starts_with($path, $base . '/')) {
            return tarhan_attempt_error('file_storage', 'Для файлов требуется хранилище вне публичного каталога.', 503);
        }
    }
    @chmod($real, 0700);
    return $real;
}

function tarhan_validate_essay_file_info($filename, $size_bytes, $allowed_extensions, $max_bytes)
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $error = null;
    if (!$extension || !in_array($extension, $allowed_extensions, true)) { $error = 'Недопустимое расширение файла.'; }
    elseif ($size_bytes <= 0) { $error = 'Файл пустой.'; }
    elseif ($size_bytes > $max_bytes) { $error = 'Файл превышает допустимый размер.'; }
    return array('extension' => $extension, 'size_bytes' => $size_bytes, 'error' => $error);
}

function tarhan_store_private_upload($file, $extensions, $max_bytes)
{
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['name']) ||
        !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK ||
        !is_string($file['tmp_name']) || !is_string($file['name']) || !is_uploaded_file($file['tmp_name'])) {
        return tarhan_attempt_error('upload_failed', 'Файл не загружен. Проверьте размер и повторите отправку.');
    }
    $size = filesize($file['tmp_name']);
    $info = tarhan_validate_essay_file_info($file['name'], $size, $extensions, $max_bytes);
    if ($info['error']) { return tarhan_attempt_error('upload_format', $info['error']); }
    if (!class_exists('finfo')) { return tarhan_attempt_error('upload_service', 'Проверка файлов недоступна.', 503); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $head = file_get_contents($file['tmp_name'], false, null, 0, 16);
    $ext = $info['extension'];
    $valid = false;
    if ($ext === 'pdf') { $valid = $mime === 'application/pdf' && str_starts_with($head, '%PDF-'); }
    if (in_array($ext, array('jpg', 'jpeg', 'png'), true)) {
        $image = @getimagesize($file['tmp_name']);
        $valid = $image && $image['mime'] === ($ext === 'png' ? 'image/png' : 'image/jpeg') && $mime === $image['mime'];
    }
    if ($ext === 'txt') {
        $text = file_get_contents($file['tmp_name']);
        $valid = in_array($mime, array('text/plain', 'application/x-empty'), true) &&
            mb_check_encoding($text, 'UTF-8') && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text);
    }
    if ($ext === 'doc') {
        $valid = str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") &&
            in_array($mime, array('application/msword', 'application/CDFV2', 'application/x-ole-storage'), true) &&
            str_contains(file_get_contents($file['tmp_name']), "W\0o\0r\0d\0D\0o\0c\0u\0m\0e\0n\0t\0");
    }
    if ($ext === 'docx' && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) === true) {
            $content = $zip->statName('[Content_Types].xml');
            $document = $zip->statName('word/document.xml');
            $valid = $content && $document && $content['size'] < 1024 * 1024 &&
                $document['size'] <= 20 * 1024 * 1024 && $zip->numFiles <= 2000 &&
                $zip->locateName('word/vbaProject.bin') === false;
            if ($valid) {
                $types = $zip->getFromName('[Content_Types].xml');
                $valid = str_contains($types, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml') &&
                    !str_contains(strtolower($types), 'macroenabled');
            }
            $zip->close();
        }
    }
    if (!$valid) { return tarhan_attempt_error('upload_content', 'Содержимое файла не соответствует разрешённому формату.'); }
    $directory = tarhan_private_directory();
    if (is_wp_error($directory)) { return $directory; }
    $key = bin2hex(random_bytes(24)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $key)) {
        return tarhan_attempt_error('file_storage', 'Не удалось сохранить файл. Повторите отправку.', 500);
    }
    @chmod($directory . '/' . $key, 0600);
    return array('key' => $key, 'name' => sanitize_file_name($file['name']), 'size' => $size, 'mime' => $mime);
}

function tarhan_delete_private_file($file)
{
    if (!is_array($file) || !preg_match('/^[a-f0-9]{48}\.[a-z0-9]+$/D', $file['key'] ?? '')) { return; }
    $directory = tarhan_private_directory();
    if (!is_wp_error($directory) && is_file($directory . '/' . $file['key'])) { unlink($directory . '/' . $file['key']); }
}

function tarhan_docx_escape($value)
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value);
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function tarhan_create_essay_docx($path, $essay, $user_id, $season)
{
    if (!class_exists('ZipArchive') || !is_object($essay) || trim((string) ($essay->text ?? '')) === '') { return false; }
    $user = get_userdata($user_id);
    $enrollment = function_exists('tarhan_enrollment') ? tarhan_enrollment($user_id, $season) : null;
    $season_name = $enrollment && $enrollment->season_name ? $enrollment->season_name : $season;
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { return false; }
    $created = gmdate('Y-m-d\TH:i:s\Z', (int) ($essay->submitted_at ?? time()));
    $paragraphs = '';
    foreach (preg_split('/\R/u', (string) $essay->text) as $line) {
        $paragraphs .= $line === '' ? '<w:p/>' : '<w:p><w:pPr><w:spacing w:after="160" w:line="360" w:lineRule="auto"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman"/><w:sz w:val="24"/></w:rPr><w:t xml:space="preserve">' . tarhan_docx_escape($line) . '</w:t></w:r></w:p>';
    }
    $title = '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="240"/></w:pPr><w:r><w:rPr><w:b/><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman"/><w:sz w:val="34"/></w:rPr><w:t>Эссе участника конкурса «Тархан»</w:t></w:r></w:p>';
    $meta = array('Участник: ' . ($user ? $user->display_name : '#' . $user_id), 'Сезон: ' . ($season_name ?: 'не указан'), 'Дата сдачи: ' . wp_date('d.m.Y H:i', (int) ($essay->submitted_at ?? time())));
    foreach ($meta as $line) { $title .= '<w:p><w:pPr><w:spacing w:after="80"/></w:pPr><w:r><w:rPr><w:b/><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman"/><w:sz w:val="22"/></w:rPr><w:t>' . tarhan_docx_escape($line) . '</w:t></w:r></w:p>'; }
    $title .= '<w:p><w:pPr><w:spacing w:after="260"/></w:pPr></w:p>';
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $title . $paragraphs . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1134" w:bottom="1440" w:left="1701" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>';
    $files = array(
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
        'word/document.xml' => $document,
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Эссе участника конкурса «Тархан»</dc:title><dc:creator>Конкурс «Тархан»</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created></cp:coreProperties>',
        'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Тархан</Application></Properties>',
    );
    foreach ($files as $name => $contents) { if (!$zip->addFromString($name, $contents)) { $zip->close(); @unlink($path); return false; } }
    return $zip->close();
}

function tarhan_send_private_download($path, $name, $mime = 'application/octet-stream', $delete_after = false)
{
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="document.' . pathinfo($name, PATHINFO_EXTENSION) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Content-Length: ' . filesize($path));
    readfile($path);
    if ($delete_after) { @unlink($path); }
    exit;
}

function tarhan_send_essay_document($id, $season)
{
    $essay = function_exists('tarhan_get_essay') ? tarhan_get_essay($id, $season) : null;
    if (!$essay) { wp_die('Эссе не найдено.', '', array('response' => 404)); }
    $file = json_decode($essay->file, true);
    $directory = tarhan_private_directory();
    if (is_wp_error($directory)) { wp_die('Файл не найден.', '', array('response' => 404)); }
    if (is_array($file) && preg_match('/^[a-f0-9]{48}\.[a-z0-9]+$/D', $file['key'] ?? '') && is_file($directory . '/' . $file['key'])) {
        tarhan_send_private_download($directory . '/' . $file['key'], $file['name'] ?? ('essay.' . pathinfo($file['key'], PATHINFO_EXTENSION)));
    }
    $path = $directory . '/essay-' . bin2hex(random_bytes(16)) . '.docx';
    if (!tarhan_create_essay_docx($path, $essay, $id, $season)) { wp_die('Не удалось создать документ Word.', '', array('response' => 500)); }
    tarhan_send_private_download($path, 'essay-' . (int) $id . '.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', true);
}

function tarhan_private_url($kind, $user_id, $season = null)
{
    $season = $season ?? tarhan_current_season_id() ?? '';
    return wp_nonce_url(add_query_arg(array('action' => 'tarhan_private_file', 'kind' => $kind,
        'user_id' => $user_id, 'season' => $season), admin_url('admin-post.php')), 'tarhan_file_' . $kind . '_' . $user_id . '_' . $season);
}

function tarhan_download_private_file()
{
    $id = absint($_GET['user_id'] ?? 0);
    $kind = is_string($_GET['kind'] ?? null) ? $_GET['kind'] : '';
    if (!$id || !in_array($kind, array('essay', 'consent'), true) ||
        (!current_user_can('manage_options') && get_current_user_id() !== $id)) {
        wp_die('Нет доступа.', '', array('response' => 403));
    }
    $season = is_string($_GET['season'] ?? null) ? $_GET['season'] : '';
    check_admin_referer('tarhan_file_' . $kind . '_' . $id . '_' . $season);
    if ($kind === 'essay') { tarhan_send_essay_document($id, $season); }
    $enrollment = tarhan_enrollment($id, $season);
    $file = $enrollment ? json_decode($enrollment->consent, true) : null;
    $directory = tarhan_private_directory();
    if (is_wp_error($directory) || !is_array($file) || !preg_match('/^[a-f0-9]{48}\.[a-z0-9]+$/D', $file['key'] ?? '') ||
        !is_file($directory . '/' . $file['key'])) { wp_die('Файл не найден.', '', array('response' => 404)); }
    tarhan_send_private_download($directory . '/' . $file['key'], $file['name']);
}
add_action('admin_post_tarhan_private_file', 'tarhan_download_private_file');

add_filter('rest_post_dispatch', function ($response, $server, $request) {
    if (str_starts_with($request->get_route(), '/tarhan/v1/')) { $response->header('Cache-Control', 'no-store, private'); }
    return $response;
}, 10, 3);
