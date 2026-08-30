import assert from 'node:assert/strict';
import {execFileSync, spawnSync} from 'node:child_process';
import {chmodSync, cpSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, realpathSync, rmSync, statSync, writeFileSync} from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';

const packageRoot = path.resolve(import.meta.dirname, '../..');
const gatePath = path.join(packageRoot, 'scripts/quality-gate.mjs');
const expectedIds = [
    'composer-validation',
    'platform-compatibility',
    'composer-audit',
    'php-quality',
    'asset-build-parity',
    'phpunit',
    'tooling-contracts',
];

function definitions() {
    return JSON.parse(execFileSync('node', [gatePath, '--list'], {cwd: packageRoot, encoding: 'utf8'}));
}

function probeFixture() {
    const root = mkdtempSync(path.join(os.tmpdir(), 'shortlink-manager-gate-'));
    const probePath = path.join(root, 'probe.sh');
    const logPath = path.join(root, 'constituents.log');
    writeFileSync(probePath, `#!/bin/sh\nprintf '%s:%s\n' "$1" "$2" >> "$SHORTLINK_MANAGER_GATE_PROBE_LOG"\nif [ "$1" = "$SHORTLINK_MANAGER_GATE_FAIL_ID" ]; then exit 71; fi\nexit 0\n`, {mode: 0o700});
    chmodSync(probePath, 0o700);

    return {
        run(failId = '') {
            return spawnSync('node', [gatePath, '--probe', probePath], {
                cwd: packageRoot,
                encoding: 'utf8',
                env: {
                    ...process.env,
                    SHORTLINK_MANAGER_GATE_PROBE_LOG: logPath,
                    SHORTLINK_MANAGER_GATE_FAIL_ID: failId,
                },
            });
        },
        ids() {
            try {
                return readFileSync(logPath, 'utf8').trim().split('\n').filter(Boolean).map((line) => line.split(':')[0]);
            } catch {
                return [];
            }
        },
        reset() {
            writeFileSync(logPath, '');
        },
        cleanup() {
            rmSync(root, {recursive: true, force: true});
        },
    };
}

function actFixture() {
    const root = mkdtempSync(path.join(os.tmpdir(), 'shortlink-manager-act-'));
    const binRoot = path.join(root, 'bin');
    const resources = path.join(root, 'owned-resources');
    const logPath = path.join(root, 'act.log');
    mkdirSync(path.join(root, '.github/workflows'), {recursive: true});
    mkdirSync(path.join(root, 'scripts'), {recursive: true});
    mkdirSync(binRoot, {recursive: true});
    mkdirSync(resources, {recursive: true});
    cpSync(path.join(packageRoot, 'scripts/act-quality-gates'), path.join(root, 'scripts/act-quality-gates'));
    cpSync(path.join(packageRoot, '.github/workflows/ci.yml'), path.join(root, '.github/workflows/ci.yml'));
    const actPath = path.join(binRoot, 'act');
    writeFileSync(actPath, `#!/bin/sh\nprintf '%s\n' "$*" > "$SHORTLINK_MANAGER_ACT_LOG"\ntouch "$SHORTLINK_MANAGER_ACT_RESOURCES/container" "$SHORTLINK_MANAGER_ACT_RESOURCES/network"\ncase " $* " in *" --rm "*) rm -f "$SHORTLINK_MANAGER_ACT_RESOURCES"/* ;; esac\nexit 73\n`, {mode: 0o700});
    chmodSync(actPath, 0o700);

    return {
        run: () => spawnSync('/bin/bash', ['scripts/act-quality-gates', '--verbose'], {
            cwd: root,
            encoding: 'utf8',
            env: {
                ...process.env,
                PATH: `${binRoot}:/usr/bin:/bin`,
                SHORTLINK_MANAGER_ACT_LOG: logPath,
                SHORTLINK_MANAGER_ACT_RESOURCES: resources,
            },
        }),
        log: () => readFileSync(logPath, 'utf8'),
        resources: () => readdirSync(resources),
        cleanup: () => rmSync(root, {recursive: true, force: true}),
    };
}

function hookFixture() {
    const root = mkdtempSync(path.join(os.tmpdir(), 'shortlink-manager-hook-'));
    const fixturePackageRoot = path.join(root, 'plugins/shortlink-manager');
    const binRoot = path.join(root, 'bin');
    const missingBinRoot = path.join(root, 'missing-bin');
    const hookPath = path.join(fixturePackageRoot, '.githooks/pre-commit');
    const invocationLog = path.join(root, 'ddev.log');
    const violationLog = path.join(root, 'violations.log');
    const trackedPath = path.join(fixturePackageRoot, 'tracked.txt');
    const stagedPath = path.join(fixturePackageRoot, 'staged-equivalent.txt');
    const sentinelPath = path.join(fixturePackageRoot, 'sentinel.txt');
    const protectedPaths = [trackedPath, stagedPath, sentinelPath];
    const forbiddenExecutables = [
        'php',
        'phpstan',
        'ecs',
        'composer',
        'phpunit',
        'node',
        'npm',
        'act',
        'git',
    ];

    mkdirSync(path.dirname(hookPath), {recursive: true});
    mkdirSync(binRoot, {recursive: true});
    mkdirSync(missingBinRoot, {recursive: true});
    const sourceHookPath = path.join(packageRoot, '.githooks/pre-commit');
    cpSync(sourceHookPath, hookPath);
    chmodSync(hookPath, statSync(sourceHookPath).mode & 0o777);
    writeFileSync(invocationLog, '');
    writeFileSync(violationLog, '');
    writeFileSync(trackedPath, 'tracked bytes\n');
    writeFileSync(stagedPath, 'staged-equivalent bytes\n');
    writeFileSync(sentinelPath, 'sentinel bytes\n');

    const ddevPath = path.join(binRoot, 'ddev');
    writeFileSync(ddevPath, `#!/bin/sh
printf 'cwd=<%s>\n' "$PWD" > "$SHORTLINK_MANAGER_HOOK_DDEV_LOG"
printf '<%s>\n' "$@" >> "$SHORTLINK_MANAGER_HOOK_DDEV_LOG"
exit "$SHORTLINK_MANAGER_HOOK_DDEV_STATUS"
`, {mode: 0o700});
    chmodSync(ddevPath, 0o700);

    for (const executable of forbiddenExecutables) {
        const executablePath = path.join(binRoot, executable);
        writeFileSync(executablePath, `#!/bin/sh
printf '%s:<%s>\n' "${executable}" "$*" >> "$SHORTLINK_MANAGER_HOOK_VIOLATION_LOG"
printf 'forbidden mutation\n' > "$SHORTLINK_MANAGER_HOOK_SENTINEL"
exit 99
`, {mode: 0o700});
        chmodSync(executablePath, 0o700);
    }

    const treeEntries = () => {
        const entries = [];
        const walk = (directory) => {
            for (const entry of readdirSync(directory, {withFileTypes: true})) {
                const entryPath = path.join(directory, entry.name);
                entries.push(path.relative(root, entryPath));
                if (entry.isDirectory()) {
                    walk(entryPath);
                }
            }
        };
        walk(root);
        return entries.sort();
    };
    const protectedBytes = () => protectedPaths.map((filePath) => readFileSync(filePath));
    const run = (status, pathRoot = binRoot) => spawnSync(hookPath, [], {
        cwd: fixturePackageRoot,
        encoding: 'utf8',
        env: {
            ...process.env,
            PATH: pathRoot,
            SHORTLINK_MANAGER_HOOK_DDEV_LOG: invocationLog,
            SHORTLINK_MANAGER_HOOK_DDEV_STATUS: String(status),
            SHORTLINK_MANAGER_HOOK_SENTINEL: sentinelPath,
            SHORTLINK_MANAGER_HOOK_VIOLATION_LOG: violationLog,
        },
    });

    return {
        hookPath,
        invocationLog,
        violationLog,
        missingBinRoot,
        protectedBytes,
        treeEntries,
        run,
        resetLogs() {
            writeFileSync(invocationLog, '');
            writeFileSync(violationLog, '');
        },
        cleanup() {
            rmSync(root, {recursive: true, force: true});
        },
    };
}

test('aggregate declares each ShortLink constituent exactly once', () => {
    const declared = definitions();
    assert.deepEqual(declared.map(({id}) => id), expectedIds);
    assert.equal(new Set(declared.map(({id}) => id)).size, expectedIds.length);
    assert.equal(new Set(declared.map(({family}) => family)).size, expectedIds.length);
    assert.match(
        declared.find(({id}) => id === 'composer-validation').standalone,
        /^composer --no-plugins validate /,
    );
    assert.doesNotMatch(JSON.stringify(declared), /test-craft-compat|smoke-test|php.?8\.2|php.?8\.4|php.?8\.5|act-quality-gates/i);
});

test('canonical Composer quality gate has one orchestrator and timeout owner', () => {
    const composer = JSON.parse(readFileSync(path.join(packageRoot, 'composer.json'), 'utf8'));
    assert.deepEqual(composer.scripts['quality-gate'], [
        'Composer\\Config::disableProcessTimeout',
        'node scripts/quality-gate.mjs',
    ]);
});

test('PHP quality configuration includes product, tests, bootstrap support, and ECS config', () => {
    const phpstan = readFileSync(path.join(packageRoot, 'phpstan.neon'), 'utf8');
    const ecs = readFileSync(path.join(packageRoot, 'ecs.php'), 'utf8');
    assert.match(phpstan, /paths:\s*\n\s*- src\s*\n\s*- tests/);
    assert.match(phpstan, /scanFiles:\s*\n\s*- tests\/Stubs\/BaseTestingBootstrap\.php/);
    assert.match(ecs, /__DIR__ \. '\/src'[\s\S]*__DIR__ \. '\/tests'[\s\S]*__FILE__/);
});

test('PHPUnit bootstrap supports package and workspace dependency layouts', () => {
    const bootstrap = readFileSync(path.join(packageRoot, 'tests/bootstrap.php'), 'utf8');
    const packageVendor = "dirname(__DIR__) . '/vendor/lindemannrock/craft-plugin-base/src/testing/bootstrap.php'";
    const workspaceVendor = "dirname(__DIR__, 3) . '/vendor/lindemannrock/craft-plugin-base/src/testing/bootstrap.php'";
    assert.ok(bootstrap.indexOf(packageVendor) < bootstrap.indexOf(workspaceVendor));
    assert.match(bootstrap, /craft-plugin-base \^5\.38/);
});

test('PHPUnit constituent owns a disposable MySQL Craft project', () => {
    const declared = definitions().find(({id}) => id === 'phpunit');
    assert.equal(declared.standalone, 'php tests/Fixtures/Project/run.php --no-progress');
    assert.match(declared.workspace, /SHORTLINK_MANAGER_FIXTURE_SOURCE_VENDOR_ROOT=\/var\/www\/html\/vendor/);

    const ci = readFileSync(path.join(packageRoot, '.github/workflows/ci.yml'), 'utf8');
    assert.match(ci, /services:\s*\n\s*db:\s*\n\s*image: mysql:8\.4/);
    assert.match(ci, /SHORTLINK_MANAGER_FIXTURE_DB_HOST: 127\.0\.0\.1/);
});

test('aggregate preserves order and propagates every constituent failure', async (context) => {
    const current = probeFixture();
    try {
        assert.equal(current.run().status, 0);
        assert.deepEqual(current.ids(), expectedIds);
        for (const id of expectedIds) {
            await context.test(id, () => {
                current.reset();
                const result = current.run(id);
                assert.equal(result.status, 71, `${id}\n${result.stdout}\n${result.stderr}`);
                assert.equal(current.ids().at(-1), id);
            });
        }
    } finally {
        current.cleanup();
    }
});

test('CI invokes only the canonical package authority after exact trust and dependency setup', () => {
    const ci = readFileSync(path.join(packageRoot, '.github/workflows/ci.yml'), 'utf8');
    assert.equal((ci.match(/run:\s+composer quality-gate/g) ?? []).length, 1);
    assert.doesNotMatch(ci, /run:\s+composer\s+(?:audit|phpstan|check-cs|ci(?::full)?|test)\b/);
    assert.match(ci, /npm ci --prefix src\/web\/assets --ignore-scripts/);
    assert.equal((ci.match(/safe\.directory/g) ?? []).length, 1);
    assert.match(ci, /safe\.directory "\$GITHUB_WORKSPACE"/);
    assert.doesNotMatch(ci, /safe\.directory[^\n]*\*/);
    assert.ok(ci.indexOf('actions/checkout@v6') < ci.indexOf('safe.directory'));
    assert.ok(ci.indexOf('safe.directory') < ci.indexOf('composer quality-gate'));
});

test('Act targets the authoritative CI job, forwards arguments, propagates failure, and requests cleanup', () => {
    const current = actFixture();
    try {
        const result = current.run();
        assert.equal(result.status, 73, result.stderr);
        assert.match(current.log(), /-W \.github\/workflows\/ci\.yml/);
        assert.match(current.log(), /-j quality-gates/);
        assert.match(current.log(), /--container-architecture linux\/amd64/);
        assert.match(current.log(), /--rm/);
        assert.match(current.log(), /--verbose/);
        assert.deepEqual(current.resources(), []);
    } finally {
        current.cleanup();
    }
});

test('pre-commit runs the read-only DDEV PHP gate without changing package bytes', () => {
    const current = hookFixture();
    try {
        const sourceHookPath = path.join(packageRoot, '.githooks/pre-commit');
        const source = readFileSync(sourceHookPath, 'utf8');
        const sourceMode = statSync(sourceHookPath).mode & 0o777;
        assert.notEqual(sourceMode & 0o111, 0);
        assert.equal(statSync(current.hookPath).mode & 0o777, sourceMode);
        assert.equal(spawnSync('/bin/bash', ['-n', sourceHookPath]).status, 0);
        assert.doesNotMatch(
            source,
            /vendor\/bin|phpstan|ecs|--fix|\bgit\b|phpunit|quality-gate|ci:full|act-quality-gates|node|npm|mktemp|trap|^\s*(?:exec\s+)?(?:php|composer)\b/mi,
        );

        for (const status of [0, 73]) {
            current.resetLogs();
            const bytesBefore = current.protectedBytes();
            const entriesBefore = current.treeEntries();
            const result = current.run(status);
            assert.equal(result.status, status, `${result.stdout}\n${result.stderr}`);
            assert.equal(
                readFileSync(current.invocationLog, 'utf8'),
                `cwd=<${realpathSync(path.dirname(path.dirname(current.hookPath)))}>\n<exec>\n<cd plugins/shortlink-manager && composer ci>\n`,
            );
            assert.equal(readFileSync(current.violationLog, 'utf8'), '');
            assert.deepEqual(current.protectedBytes(), bytesBefore);
            assert.deepEqual(current.treeEntries(), entriesBefore);
        }

        current.resetLogs();
        const bytesBeforeMissingDdev = current.protectedBytes();
        const entriesBeforeMissingDdev = current.treeEntries();
        const missingDdev = current.run(0, current.missingBinRoot);
        assert.equal(missingDdev.status, 127);
        assert.match(missingDdev.stderr, /pre-commit requires DDEV/);
        assert.match(missingDdev.stderr, /ddev exec "cd plugins\/shortlink-manager && composer ci"/);
        assert.equal(readFileSync(current.invocationLog, 'utf8'), '');
        assert.equal(readFileSync(current.violationLog, 'utf8'), '');
        assert.deepEqual(current.protectedBytes(), bytesBeforeMissingDdev);
        assert.deepEqual(current.treeEntries(), entriesBeforeMissingDdev);
    } finally {
        current.cleanup();
    }
});
