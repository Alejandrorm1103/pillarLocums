<?php
require_once __DIR__ . '/core/I18n.php';

$lang = $_COOKIE['lang'] ?? 'es';
I18n::init($lang);

function __(string $key, array $params = []): string {
  return I18n::t($key, $params);
}