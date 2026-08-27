import assert from 'node:assert/strict';
import {spawn, spawnSync} from 'node:child_process';
import {chmodSync, cpSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync} from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';

const packageRoot = path.resolve(import.meta.dirname, '../..');

function executable(pathname, source) {
    writeFileSync(pathname, source, {mode: 0o700});
    chmodSync(pathname, 0o700);
}

async function waitForFile(pathname) {
    const deadline = Date.now() + 5000;
    while (Date.now() < deadline) {
        try {
            readFileSync(pathname);
            return;
        } catch {
            await new Promise((resolve) => setTimeout(resolve, 25));
        }
    }
    throw new Error(`Timed out waiting for ${pathname}`);
}

function waitForExit(child) {
    return new Promise((resolve, reject) => {
        child.once('error', reject);
        child.once('close', (code, signal) => resolve({code, signal}));
    });
}

test('asset parity runner preserves failure status and removes its exact temporary output', () => {
    const root = mkdtempSync(path.join(os.tmpdir(), 'shortlink-manager-asset-failure-'));
    const tool = path.join(root, 'tool.sh');
    const temporaryParent = path.join(root, 'temporary');
    mkdirSync(temporaryParent);
    executable(tool, '#!/bin/sh\nexit 71\n');
    try {
        const result = spawnSync('node', ['scripts/check-asset-build.mjs'], {
            cwd: packageRoot,
            encoding: 'utf8',
            env: {
                ...process.env,
                SHORTLINK_ASSET_BUILD_TEST_MODE: '1',
                SHORTLINK_ASSET_BUILD_TEST_TOOL: tool,
                SHORTLINK_ASSET_BUILD_TMP_PARENT: temporaryParent,
            },
        });
        assert.equal(result.status, 71, `${result.stdout}\n${result.stderr}`);
        assert.deepEqual(readdirSync(temporaryParent), []);
    } finally {
        rmSync(root, {recursive: true, force: true});
    }
});

test('asset parity runner removes its exact temporary output when interrupted', async () => {
    const root = mkdtempSync(path.join(os.tmpdir(), 'shortlink-manager-asset-signal-'));
    const tool = path.join(root, 'tool.sh');
    const marker = path.join(root, 'ready');
    const temporaryParent = path.join(root, 'temporary');
    mkdirSync(temporaryParent);
    executable(tool, `#!/bin/sh\nprintf ready > "${marker}"\ntrap 'exit 143' TERM\nwhile :; do sleep 1; done\n`);
    try {
        const child = spawn('node', ['scripts/check-asset-build.mjs'], {
            cwd: packageRoot,
            stdio: 'ignore',
            env: {
                ...process.env,
                SHORTLINK_ASSET_BUILD_TEST_MODE: '1',
                SHORTLINK_ASSET_BUILD_TEST_TOOL: tool,
                SHORTLINK_ASSET_BUILD_TMP_PARENT: temporaryParent,
            },
        });
        const exited = waitForExit(child);
        await waitForFile(marker);
        child.kill('SIGTERM');
        const result = await exited;
        assert.equal(result.code, 143, result.signal);
        assert.deepEqual(readdirSync(temporaryParent), []);
    } finally {
        rmSync(root, {recursive: true, force: true});
    }
});

test('Composer audit runner removes its exact temporary project on failure', () => {
    const root = mkdtempSync(path.join(os.tmpdir(), 'shortlink-manager-audit-failure-'));
    const currentPackage = path.join(root, 'shortlink-manager');
    const binRoot = path.join(root, 'bin');
    const temporaryParent = path.join(root, 'temporary');
    mkdirSync(path.join(currentPackage, 'scripts'), {recursive: true});
    mkdirSync(binRoot);
    mkdirSync(temporaryParent);
    cpSync(path.join(packageRoot, 'scripts/composer-audit'), path.join(currentPackage, 'scripts/composer-audit'));
    cpSync(path.join(packageRoot, 'composer.json'), path.join(currentPackage, 'composer.json'));
    executable(path.join(binRoot, 'composer'), '#!/bin/sh\nexit 72\n');
    try {
        const result = spawnSync('/bin/bash', ['scripts/composer-audit'], {
            cwd: currentPackage,
            encoding: 'utf8',
            env: {...process.env, PATH: `${binRoot}:/usr/bin:/bin`, TMPDIR: temporaryParent},
        });
        assert.equal(result.status, 72, `${result.stdout}\n${result.stderr}`);
        assert.deepEqual(readdirSync(temporaryParent), []);
    } finally {
        rmSync(root, {recursive: true, force: true});
    }
});
