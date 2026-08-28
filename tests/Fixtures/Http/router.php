<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
$projectRoot = is_string($documentRoot) ? dirname($documentRoot) : null;
$expected = '#^' . preg_quote(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), '#')
    . '/shortlink-manager-fixture-[a-f0-9]{16}$#';
if (!is_string($projectRoot) || preg_match($expected, $projectRoot) !== 1) {
    http_response_code(500);
    exit('Invalid ShortLink Manager HTTP fixture boundary.');
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$staticPath = is_string($path) ? $projectRoot . '/web' . $path : null;
if (PHP_SAPI === 'cli-server' && is_string($staticPath) && is_file($staticPath)) {
    return false;
}

define('CRAFT_BASE_PATH', $projectRoot);
define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');
$_SERVER['SCRIPT_FILENAME'] = $projectRoot . '/web/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require CRAFT_VENDOR_PATH . '/autoload.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
$app->run();
