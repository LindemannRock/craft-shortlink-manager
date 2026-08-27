#!/usr/bin/env node

import {existsSync} from 'node:fs';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const packageRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const workspaceRoot = path.resolve(packageRoot, '../..');
const workspaceMode = packageRoot === path.join(workspaceRoot, 'plugins/shortlink-manager')
    && existsSync(path.join(workspaceRoot, '.ddev/config.yaml'));
const insideDdev = process.env.IS_DDEV_PROJECT === 'true';
const workspaceHostMode = workspaceMode && !insideDdev;

const constituents = [
    {
        id: 'composer-validation',
        family: 'package-validity',
        standalone: ['composer', ['--no-plugins', 'validate', '--no-check-publish', '--no-interaction']],
        workspace: ['ddev', ['exec', 'cd plugins/shortlink-manager && composer --no-plugins validate --no-check-publish --no-interaction']],
    },
    {
        id: 'platform-compatibility',
        family: 'platform',
        standalone: ['composer', ['check-platform-reqs', '--no-interaction']],
        workspace: ['ddev', ['exec', 'composer check-platform-reqs --no-interaction']],
    },
    {
        id: 'composer-audit',
        family: 'security-audit',
        standalone: ['bash', ['scripts/composer-audit']],
        workspace: ['ddev', ['exec', 'cd plugins/shortlink-manager && bash scripts/composer-audit']],
    },
    {
        id: 'php-quality',
        family: 'php-static-style',
        standalone: ['composer', ['ci']],
        workspace: ['ddev', ['exec', 'cd plugins/shortlink-manager && composer ci']],
    },
    {
        id: 'asset-build-parity',
        family: 'source-distribution',
        standalone: ['node', ['scripts/check-asset-build.mjs']],
        workspace: ['ddev', ['exec', 'cd plugins/shortlink-manager && node scripts/check-asset-build.mjs']],
    },
    {
        id: 'phpunit',
        family: 'php-runtime-archive',
        standalone: ['php', ['tests/Fixtures/Project/run.php', '--no-progress']],
        workspace: ['ddev', ['exec', 'cd plugins/shortlink-manager && SHORTLINK_MANAGER_FIXTURE_SOURCE_VENDOR_ROOT=/var/www/html/vendor php tests/Fixtures/Project/run.php --no-progress']],
    },
    {
        id: 'tooling-contracts',
        family: 'verification-orchestration',
        standalone: ['node', [
            '--test',
            'tests/js/quality-gate-orchestration.test.mjs',
            'tests/js/runner-cleanup.test.mjs',
            'tests/js/compatibility-runner-cleanup.test.mjs',
        ]],
        workspace: ['ddev', ['exec', 'cd plugins/shortlink-manager && node --test tests/js/quality-gate-orchestration.test.mjs tests/js/runner-cleanup.test.mjs tests/js/compatibility-runner-cleanup.test.mjs']],
    },
];

const argumentList = process.argv.slice(2);
const listOnly = argumentList.includes('--list');
const probeIndex = argumentList.indexOf('--probe');
const probeExecutable = probeIndex === -1 ? null : argumentList[probeIndex + 1];

if (probeIndex !== -1 && (!probeExecutable || !path.isAbsolute(probeExecutable))) {
    console.error('--probe requires an absolute executable path.');
    process.exit(2);
}

if (listOnly) {
    const formatCommand = ([command, commandArguments]) => [command, ...commandArguments].join(' ');
    console.log(JSON.stringify(constituents.map(({id, family, standalone, workspace}) => ({
        id,
        family,
        standalone: formatCommand(standalone),
        workspace: formatCommand(workspace ?? standalone),
    })), null, 2));
    process.exit(0);
}

function commandFor(constituent) {
    if (probeExecutable !== null) {
        return [probeExecutable, [constituent.id, constituent.family], packageRoot, process.env];
    }
    if (workspaceHostMode && constituent.workspace) {
        return [constituent.workspace[0], constituent.workspace[1], workspaceRoot, process.env];
    }

    const workingDirectory = insideDdev && constituent.id === 'platform-compatibility'
        ? workspaceRoot
        : packageRoot;

    return [constituent.standalone[0], constituent.standalone[1], workingDirectory, process.env];
}

for (const constituent of constituents) {
    const [command, commandArguments, cwd, environment] = commandFor(constituent);
    console.log(`\n==> ${constituent.id}`);
    const result = spawnSync(command, commandArguments, {cwd, env: environment, stdio: 'inherit'});
    if (result.error) {
        console.error(`${constituent.id} could not start: ${result.error.message}`);
        process.exit(1);
    }
    if (result.status !== 0) {
        const status = result.status ?? 1;
        console.error(`${constituent.id} failed with exit ${status}.`);
        process.exit(status);
    }
}

console.log('\nComplete ShortLink Manager quality gate passed.');
