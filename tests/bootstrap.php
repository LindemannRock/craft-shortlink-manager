<?php

/**
 * PHPUnit bootstrap for the shortlink-manager plugin.
 *
 * Delegates to the shared base-plugin bootstrap, which initialises Craft as a
 * console application. Tests run against the live DDEV database — there is no
 * transactional rollback. Cleanup is by marker (see `tests/TestCase.php`).
 *
 * @since 5.19.0
 */

declare(strict_types=1);

$configuredProjectRoot = $_SERVER['SHORTLINK_MANAGER_TEST_PROJECT_ROOT'] ?? null;
$projectRoot = is_string($configuredProjectRoot) && $configuredProjectRoot !== ''
    ? $configuredProjectRoot
    : null;

$baseBootstrapCandidates = array_filter([
    $projectRoot === null ? null : $projectRoot . '/vendor/lindemannrock/craft-plugin-base/src/testing/bootstrap.php',
    dirname(__DIR__) . '/vendor/lindemannrock/craft-plugin-base/src/testing/bootstrap.php',
    dirname(__DIR__, 3) . '/vendor/lindemannrock/craft-plugin-base/src/testing/bootstrap.php',
]);

$baseBootstrap = null;
foreach ($baseBootstrapCandidates as $candidate) {
    if (file_exists($candidate)) {
        $baseBootstrap = $candidate;
        break;
    }
}

if ($baseBootstrap === null) {
    fwrite(STDERR, "Base plugin testing bootstrap not found in the package or workspace vendor directories.\n");
    fwrite(STDERR, "Run `composer install` and ensure lindemannrock/craft-plugin-base ^5.38 is present.\n");
    exit(1);
}

require_once $baseBootstrap;

\lindemannrock\base\testing\bootstrap($projectRoot);
