<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

use craft\models\Site;

$projectRoot = $_SERVER['SHORTLINK_MANAGER_TEST_PROJECT_ROOT'] ?? null;
$expected = '#^' . preg_quote(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), '#')
    . '/shortlink-manager-fixture-[a-f0-9]{16}$#';
if (!is_string($projectRoot) || preg_match($expected, $projectRoot) !== 1) {
    throw new RuntimeException('Site seeding requires the exact disposable project boundary.');
}

require $projectRoot . '/bootstrap.php';
require $projectRoot . '/vendor/craftcms/cms/bootstrap/console.php';

$sites = Craft::$app->getSites();
$primary = $sites->getPrimarySite();
$primary->handle = 'en';
if (!$sites->saveSite($primary)) {
    throw new RuntimeException('Unable to normalize the primary fixture site: ' . json_encode($primary->getErrors()));
}
foreach ([
    ['name' => 'ShortLink DE', 'handle' => 'shortlinkDe', 'language' => 'de-DE', 'baseUrl' => 'https://de.shortlink.example.test'],
    ['name' => 'ShortLink FR', 'handle' => 'shortlinkFr', 'language' => 'fr-FR', 'baseUrl' => 'https://fr.shortlink.example.test'],
    ['name' => 'ShortLink NL', 'handle' => 'shortlinkNl', 'language' => 'nl-NL', 'baseUrl' => 'https://nl.shortlink.example.test'],
    ['name' => 'ShortLink ES', 'handle' => 'shortlinkEs', 'language' => 'es-ES', 'baseUrl' => 'https://es.shortlink.example.test'],
    ['name' => 'ShortLink AR', 'handle' => 'shortlinkAr', 'language' => 'ar', 'baseUrl' => 'https://ar.shortlink.example.test'],
    ['name' => 'ShortLink IT', 'handle' => 'shortlinkIt', 'language' => 'it-IT', 'baseUrl' => 'https://it.shortlink.example.test'],
    ['name' => 'ShortLink JA', 'handle' => 'shortlinkJa', 'language' => 'ja-JP', 'baseUrl' => 'https://ja.shortlink.example.test'],
] as $definition) {
    $site = new Site([
        ...$definition,
        'groupId' => $primary->groupId,
        'primary' => false,
        'enabled' => true,
    ]);
    if (!$sites->saveSite($site)) {
        throw new RuntimeException('Unable to save fixture site: ' . json_encode($site->getErrors()));
    }
}

if (count($sites->getAllSites()) !== 8) {
    throw new RuntimeException('ShortLink Manager fixture must contain exactly eight sites.');
}
