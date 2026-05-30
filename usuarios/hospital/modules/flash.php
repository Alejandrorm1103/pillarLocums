<?php
declare(strict_types=1);

function flash_set(string $key, string $msg): void {
  $_SESSION['_flash'][$key] = $msg;
}
function flash_get(string $key): ?string {
  if (!isset($_SESSION['_flash'][$key])) return null;
  $m = (string)$_SESSION['_flash'][$key];
  unset($_SESSION['_flash'][$key]);
  return $m;
}
