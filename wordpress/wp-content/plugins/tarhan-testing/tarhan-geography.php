<?php
if (!defined('ABSPATH')) { exit; }

function tarhan_geography_districts($region_id = null)
{
    static $districts;
    if ($districts === null) {
        $path = __DIR__ . '/data/geography/districts.json';
        $districts = is_file($path) ? json_decode(file_get_contents($path), true) : array();
    }
    if (!is_array($districts)) { return array(); }
    if ($region_id === null) { return $districts; }
    if (!is_string($region_id) || !preg_match('/^[1-9][0-9]{0,11}$/D', $region_id) || !in_array($region_id, $districts['regions'] ?? array(), true)) { return array(); }
    static $cached_id, $cached;
    if ($cached_id !== $region_id) {
        $path = __DIR__ . '/data/geography/districts/' . $region_id . '.json';
        $cached = is_file($path) ? json_decode(file_get_contents($path), true) : array();
        $cached_id = $region_id;
    }
    return is_array($cached) ? $cached : array();
}

function tarhan_geography_regions()
{
    static $index;
    if ($index === null) {
        $path = __DIR__ . '/data/geography/regions.json';
        $index = is_file($path) ? json_decode(file_get_contents($path), true) : false;
        $districts = tarhan_geography_districts();
        if (is_array($index) && !empty($districts['version'])) { $index['version'] .= '-' . $districts['version']; }
    }
    if (!is_array($index) || empty($index['regions']) || empty($index['version'])) {
        return tarhan_attempt_error('geography_unavailable', 'Справочник временно недоступен. Повторите позже.', 503);
    }
    return $index;
}

function tarhan_geography_region($id)
{
    if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,11}$/D', $id)) {
        return tarhan_attempt_error('geography_region', 'Выберите регион из списка.');
    }
    $index = tarhan_geography_regions();
    if (is_wp_error($index)) { return $index; }
    foreach ($index['regions'] as $region) { if ($region['id'] === $id) { return $region; } }
    return tarhan_attempt_error('geography_region', 'Выберите регион из списка.');
}

function tarhan_geography_localities($region_id)
{
    $region = tarhan_geography_region($region_id);
    if (is_wp_error($region)) { return $region; }
    static $cached_id, $cached;
    if ($cached_id !== $region_id) {
        // The filename comes only from the allowlisted region index, never from an arbitrary path.
        $path = __DIR__ . '/data/geography/' . $region['id'] . '.json';
        $cached = is_file($path) ? json_decode(file_get_contents($path), true) : false;
        $districts = tarhan_geography_districts($region_id);
        if (is_array($cached)) {
            foreach ($districts as $id => $district) {
                if (isset($cached[$id]) && $cached[$id][1] === '') {
                    $cached[$id][1] = $district;
                    $cached[$id][2] .= ' ' . tarhan_geography_normalize($district);
                }
            }
        }
        $cached_id = $region_id;
    }
    if (!is_array($cached)) { return tarhan_attempt_error('geography_unavailable', 'Справочник временно недоступен. Повторите позже.', 503); }
    return $cached;
}

function tarhan_geography_normalize($text)
{
    return preg_replace('/\s+/u', ' ', str_replace('ё', 'е', mb_strtolower(trim($text), 'UTF-8')));
}

function tarhan_geography_search($region_id, $query)
{
    if (!is_string($query) || !mb_check_encoding($query, 'UTF-8') || mb_strlen(trim($query), 'UTF-8') < 2 || mb_strlen($query, 'UTF-8') > 120) {
        return tarhan_attempt_error('geography_query', 'Введите от 2 до 120 символов названия населённого пункта.');
    }
    $rows = tarhan_geography_localities($region_id);
    if (is_wp_error($rows)) { return $rows; }
    $needle = tarhan_geography_normalize($query);
    $parts = explode(' ', $needle);
    $matches = array();
    foreach ($rows as $id => $row) {
        foreach ($parts as $part) { if (mb_strpos($row[2], $part, 0, 'UTF-8') === false) { continue 2; } }
        $name = tarhan_geography_normalize($row[0]);
        $rank = $name === $needle ? 3 : (str_starts_with($name, $needle) ? 2 : 1);
        $matches[] = array('id' => (string) $id, 'region_id' => $region_id, 'name' => $row[0], 'district' => $row[1], '_rank' => $rank, '_population' => $row[3]);
    }
    usort($matches, function ($a, $b) {
        return ($b['_rank'] <=> $a['_rank']) ?: ($b['_population'] <=> $a['_population']) ?: strcmp($a['name'], $b['name']) ?: strcmp($a['id'], $b['id']);
    });
    $more = count($matches) > 20;
    $matches = array_slice($matches, 0, 20);
    foreach ($matches as &$item) { unset($item['_rank'], $item['_population']); }
    unset($item);
    return array('localities' => $matches, 'more' => $more);
}

function tarhan_geography_address($input)
{
    $region = tarhan_geography_region($input['region_id'] ?? null);
    if (is_wp_error($region)) { return $region; }
    $id = $input['locality_id'] ?? null;
    if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,11}$/D', $id)) {
        return tarhan_attempt_error('geography_locality', 'Выберите населённый пункт из подсказок.');
    }
    $rows = tarhan_geography_localities($region['id']);
    if (is_wp_error($rows)) { return $rows; }
    if (!isset($rows[$id])) {
        return tarhan_attempt_error('geography_locality', 'Населённый пункт не относится к выбранному региону. Выберите его заново.');
    }
    $manual = array();
    foreach (array('street' => 150, 'house' => 80) as $field => $limit) {
        $value = $input[$field] ?? null;
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || trim($value) === '' || mb_strlen($value, 'UTF-8') > $limit || sanitize_text_field(trim($value)) !== trim($value)) {
            return tarhan_attempt_error('registration_address', 'Укажите улицу (либо «Без улицы») и дом.');
        }
        $manual[$field] = trim($value);
    }
    $index = tarhan_geography_regions();
    $row = $rows[$id];
    return array('source' => 'geonames', 'directory_version' => $index['version'], 'region_id' => $region['id'],
        'locality_id' => $id, 'region' => $region['name'], 'district' => $row[1], 'locality' => $row[0],
        'street' => $manual['street'], 'house' => $manual['house'], 'street_verified' => false, 'house_verified' => false,
        'value' => implode(', ', array_filter(array($region['name'], $row[1], $row[0], $manual['street'], $manual['house']))));
}

add_action('rest_api_init', function () {
    register_rest_route('tarhan/v1', '/geography/regions', array('methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => 'tarhan_geography_regions'));
    register_rest_route('tarhan/v1', '/geography/localities', array('methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => function ($request) { return tarhan_geography_search($request->get_param('region_id'), $request->get_param('query')); }));
});
