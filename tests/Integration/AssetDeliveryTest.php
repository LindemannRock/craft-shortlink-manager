<?php
/**
 * ShortLink Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\shortlinkmanager\tests\Integration;

use Craft;
use craft\db\Connection;
use craft\web\AssetBundle;
use craft\web\AssetManager;
use lindemannrock\shortlinkmanager\ShortLinkManager;
use lindemannrock\shortlinkmanager\tests\TestCase;
use lindemannrock\shortlinkmanager\web\assets\analytics\AnalyticsAsset;
use lindemannrock\shortlinkmanager\web\assets\edit\EditAsset;
use lindemannrock\shortlinkmanager\web\assets\qrpreview\QrPreviewAsset;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers static asset delivery for ShortLink Manager control-panel features.
 *
 * @since 5.29.0
 */
final class AssetDeliveryTest extends TestCase
{
    public function testInstalledAliasResolvesToPackageAssets(): void
    {
        $packageRoot = dirname(__DIR__, 2);
        $sourceRoot = realpath($packageRoot . '/src');
        $aliasRoot = realpath(Craft::getAlias('@lindemannrock/shortlinkmanager'));

        self::assertIsString($sourceRoot);
        self::assertSame($sourceRoot, $aliasRoot);
        self::assertDirectoryExists($aliasRoot . '/web/assets/analytics/dist');
        self::assertDirectoryExists($aliasRoot . '/web/assets/qrpreview/dist');
        self::assertDirectoryExists($aliasRoot . '/web/assets/edit/dist');
    }

    /**
     * @param class-string<AssetBundle> $bundleClass
     * @param list<string> $js
     * @param list<string> $css
     * @param list<class-string<AssetBundle>> $depends
     */
    #[DataProvider('assetBundleDefinitions')]
    public function testBundlesResolveAliasWithoutPluginDatabaseOrPublicationState(
        string $bundleClass,
        string $sourceSuffix,
        array $js,
        array $css,
        array $depends,
    ): void {
        $originalAlias = Craft::getAlias('@lindemannrock/shortlinkmanager');
        $originalAssetManager = Craft::$app->getAssetManager();
        $originalDb = Craft::$app->getDb();
        $originalPlugin = ShortLinkManager::$plugin;
        $aliasRoot = $this->createTrackedTempDirectory('shortlink-asset-package-');
        $offlineDb = new Connection([
            'dsn' => 'unsupported:shortlink-manager-asset-test',
        ]);
        $offlineAssetManager = new class([ 'basePath' => $aliasRoot, 'baseUrl' => '/unavailable-runtime-assets', ]) extends AssetManager {
            /** @var list<string> */
            public array $publicationPaths = [];

            /**
             * @inheritdoc
             */
            public function publish($path, $options = []): array
            {
                $this->publicationPaths[] = Craft::getAlias($path);

                throw new \RuntimeException('Runtime asset publication is unavailable.');
            }
        };

        try {
            Craft::setAlias('@lindemannrock/shortlinkmanager', $aliasRoot);
            Craft::$app->set('assetManager', $offlineAssetManager);
            Craft::$app->set('db', $offlineDb);
            ShortLinkManager::$plugin = null;

            $bundle = new $bundleClass();

            self::assertNull(ShortLinkManager::$plugin);
            self::assertFalse($offlineDb->getIsActive());
            self::assertSame([], $offlineAssetManager->publicationPaths);
            self::assertSame($aliasRoot . $sourceSuffix, $bundle->sourcePath);
            self::assertSame($js, $bundle->js);
            self::assertSame($css, $bundle->css);
            self::assertSame($depends, $bundle->depends);
            self::assertSame([], $bundle->jsOptions);
            self::assertSame([], $bundle->cssOptions);
        } finally {
            ShortLinkManager::$plugin = $originalPlugin;
            Craft::$app->set('db', $originalDb);
            Craft::$app->set('assetManager', $originalAssetManager);
            Craft::setAlias('@lindemannrock/shortlinkmanager', $originalAlias);
        }
    }

    public function testPrepublishedUrlsRenderOnceInRegistrationOrder(): void
    {
        $originalAssetManager = Craft::$app->getAssetManager();
        $view = Craft::$app->getView();
        $sourceRoot = Craft::getAlias('@lindemannrock/shortlinkmanager');
        $analyticsUrl = 'https://cdn.example.test/shortlink-manager/analytics';
        $qrPreviewUrl = 'https://cdn.example.test/shortlink-manager/qr-preview';
        $editUrl = 'https://cdn.example.test/shortlink-manager/edit';
        $assetManager = new class([ 'basePath' => $sourceRoot, 'baseUrl' => '/unavailable-runtime-assets', 'appendTimestamp' => false, 'bundles' => [ AnalyticsAsset::class => [ 'basePath' => $sourceRoot . '/web/assets/analytics/dist', 'baseUrl' => $analyticsUrl, ], QrPreviewAsset::class => [ 'basePath' => $sourceRoot . '/web/assets/qrpreview/dist', 'baseUrl' => $qrPreviewUrl, ], EditAsset::class => [ 'basePath' => $sourceRoot . '/web/assets/edit/dist', 'baseUrl' => $editUrl, ], \lindemannrock\base\web\assets\analytics\AnalyticsAsset::class => [ 'class' => \yii\web\AssetBundle::class, 'basePath' => $sourceRoot, 'baseUrl' => 'https://cdn.example.test/base/analytics', ], ], ]) extends AssetManager {
            /** @var list<string> */
            public array $publicationPaths = [];

            /**
             * @inheritdoc
             */
            public function publish($path, $options = []): array
            {
                $this->publicationPaths[] = Craft::getAlias($path);

                throw new \RuntimeException('Runtime asset publication is unavailable.');
            }
        };

        try {
            Craft::$app->set('assetManager', $assetManager);
            $view->clear();

            foreach ([AnalyticsAsset::class, QrPreviewAsset::class, EditAsset::class] as $bundleClass) {
                $view->registerAssetBundle($bundleClass);
                $view->registerAssetBundle($bundleClass);
            }

            $registeredBundles = array_values(array_intersect(
                array_keys($view->assetBundles),
                [AnalyticsAsset::class, QrPreviewAsset::class, EditAsset::class],
            ));

            self::assertSame([], $assetManager->publicationPaths);
            self::assertSame([AnalyticsAsset::class, QrPreviewAsset::class, EditAsset::class], $registeredBundles);
            self::assertSame($analyticsUrl, $view->assetBundles[AnalyticsAsset::class]->baseUrl);
            self::assertSame($qrPreviewUrl, $view->assetBundles[QrPreviewAsset::class]->baseUrl);
            self::assertSame($editUrl, $view->assetBundles[EditAsset::class]->baseUrl);

            $bodyHtml = $view->getBodyHtml(false);
            $scriptCount = preg_match_all('/<script[^>]+src="([^"]+)"[^>]*><\/script>/', $bodyHtml, $scriptMatches);
            $scriptUrls = array_map(static fn(string $url): string => html_entity_decode($url), $scriptMatches[1]);

            self::assertSame(3, $scriptCount, $bodyHtml);
            self::assertSame([
                $analyticsUrl . '/analytics.js',
                $qrPreviewUrl . '/qr-preview.js',
                $editUrl . '/edit.js',
            ], $scriptUrls, $bodyHtml);
            self::assertSame([], $assetManager->publicationPaths);
        } finally {
            $view->clear();
            Craft::$app->set('assetManager', $originalAssetManager);
        }
    }

    public function testCustomerArchivesIncludeEveryBundleAssetAndPortableCacheRuntimeFile(): void
    {
        $expected = [
            'src/services/CacheStorageService.php',
            'src/services/AnalyticsCleanupScheduler.php',
            'src/web/assets/analytics/dist/analytics.js',
            'src/web/assets/qrpreview/dist/qr-preview.js',
            'src/web/assets/edit/dist/edit.js',
        ];
        $packageRoot = dirname(__DIR__, 2);
        $archiveRoot = $this->createTrackedTempDirectory('shortlink-asset-archive-');
        $gitArchive = $archiveRoot . '/git-package.tar';
        $composerArchive = $archiveRoot . '/composer-package.tar';
        $excludedPrefixes = [
            '.github/',
            '.githooks/',
            '.internal/',
            'docs/',
            'scripts/',
            'tests/',
            'src/web/assets/analytics/src/',
            'src/web/assets/edit/src/',
            'src/web/assets/qrpreview/src/',
        ];
        $excludedFiles = [
            '.gitattributes',
            '.gitignore',
            'CLAUDE.md',
            'ecs.php',
            'package.json',
            'phpstan.neon',
            'phpunit.xml.dist',
            'src/web/assets/package.json',
            'src/web/assets/package-lock.json',
        ];

        foreach ($expected as $path) {
            self::assertFileExists($packageRoot . '/' . $path, $path);
        }

        $gitTree = trim($this->runProcess([
            'git',
            '-c',
            'safe.directory=' . $packageRoot,
            'write-tree',
        ], $packageRoot));
        self::assertNotSame('', $gitTree);

        $this->runProcess([
            'git',
            '-c',
            'safe.directory=' . $packageRoot,
            'archive',
            '--worktree-attributes',
            '--output=' . $gitArchive,
            $gitTree,
        ], $packageRoot);
        $this->runProcess([
            'composer',
            'archive',
            '--format=tar',
            '--dir=' . $archiveRoot,
            '--file=composer-package',
            '--no-interaction',
            '--no-ansi',
        ], $packageRoot);

        $gitMembers = array_filter(explode("\n", $this->runProcess(['tar', '-tf', $gitArchive], $packageRoot)));
        $composerMembers = array_filter(explode("\n", $this->runProcess(['tar', '-tf', $composerArchive], $packageRoot)));

        foreach ($expected as $path) {
            self::assertContains($path, $gitMembers, 'Git archive: ' . $path);
            self::assertContains($path, $composerMembers, 'Composer archive: ' . $path);
        }

        foreach (['Git archive' => $gitMembers, 'Composer archive' => $composerMembers] as $label => $members) {
            foreach ($excludedFiles as $path) {
                self::assertNotContains($path, $members, $label . ': ' . $path);
            }

            foreach ($excludedPrefixes as $prefix) {
                self::assertSame(
                    [],
                    array_values(array_filter(
                        $members,
                        static fn(string $member): bool => str_starts_with($member, $prefix),
                    )),
                    $label . ': ' . $prefix,
                );
            }
        }
    }

    /**
     * @return iterable<string, array{class-string<AssetBundle>, string, list<string>, list<string>, list<class-string<AssetBundle>>}>
     */
    public static function assetBundleDefinitions(): iterable
    {
        yield 'analytics' => [
            AnalyticsAsset::class,
            '/web/assets/analytics/dist',
            ['analytics.js'],
            [],
            [\lindemannrock\base\web\assets\analytics\AnalyticsAsset::class],
        ];
        yield 'QR preview' => [
            QrPreviewAsset::class,
            '/web/assets/qrpreview/dist',
            ['qr-preview.js'],
            [],
            [],
        ];
        yield 'edit screen' => [
            EditAsset::class,
            '/web/assets/edit/dist',
            ['edit.js'],
            [],
            [],
        ];
    }

    /**
     * @param list<string> $command
     */
    private function runProcess(array $command, string $workingDirectory): string
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory,
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertIsString($output);
        self::assertIsString($error);
        self::assertSame(0, proc_close($process), $error);

        return $output;
    }
}
