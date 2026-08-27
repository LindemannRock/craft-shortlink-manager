#!/usr/bin/env node

import {createHash} from 'node:crypto';
import {existsSync, mkdtempSync, mkdirSync, readFileSync, rmSync} from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawn} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const packageRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const assetRoot = path.join(packageRoot, 'src/web/assets');
const temporaryParent = process.env.SHORTLINK_ASSET_BUILD_TMP_PARENT || os.tmpdir();
const expectedPrefix = path.join(temporaryParent, 'shortlink-manager-asset-build-');
const definitions = [
    ['analytics/src/analytics.js', 'analytics/dist/analytics.js'],
    ['qrpreview/src/qr-preview.js', 'qrpreview/dist/qr-preview.js'],
    ['edit/src/edit.js', 'edit/dist/edit.js'],
];

let temporaryRoot = null;
let activeChild = null;

function cleanup() {
    if (temporaryRoot === null) {
        return;
    }
    if (!temporaryRoot.startsWith(expectedPrefix)) {
        throw new Error(`Refusing to remove unexpected asset-build path: ${temporaryRoot}`);
    }
    rmSync(temporaryRoot, {recursive: true, force: true});
    temporaryRoot = null;
}

for (const [signal, status] of [['SIGINT', 130], ['SIGTERM', 143], ['SIGHUP', 129]]) {
    process.once(signal, () => {
        if (activeChild !== null) {
            activeChild.kill(signal);
        }
        try {
            cleanup();
        } catch (error) {
            console.error(error instanceof Error ? error.message : String(error));
        }
        process.exit(status);
    });
}

function run(executable, argumentsList) {
    return new Promise((resolve, reject) => {
        const child = spawn(executable, argumentsList, {cwd: assetRoot, stdio: 'inherit'});
        activeChild = child;
        child.once('error', reject);
        child.once('close', (status) => {
            activeChild = null;
            if (status === 0) {
                resolve();
                return;
            }
            const error = new Error(`Asset compiler failed with exit ${status ?? 1}.`);
            error.exitStatus = status ?? 1;
            reject(error);
        });
    });
}

function digest(bytes) {
    return createHash('sha256').update(bytes).digest('hex');
}

let exitStatus = 0;
try {
    mkdirSync(temporaryParent, {recursive: true});
    temporaryRoot = mkdtempSync(expectedPrefix);
    const testTool = process.env.SHORTLINK_ASSET_BUILD_TEST_MODE === '1'
        ? process.env.SHORTLINK_ASSET_BUILD_TEST_TOOL
        : null;
    const terser = testTool || path.join(assetRoot, 'node_modules/.bin/terser');
    if (!terser || !existsSync(terser)) {
        throw new Error(`Terser is unavailable at ${terser || '(unset)'}. Run npm ci in src/web/assets.`);
    }

    for (const [source, distribution] of definitions) {
        const output = path.join(temporaryRoot, distribution);
        mkdirSync(path.dirname(output), {recursive: true});
        await run(terser, [source, '-o', output, '-c', '-m']);

        const built = readFileSync(output);
        const committed = readFileSync(path.join(assetRoot, distribution));
        if (!built.equals(committed)) {
            throw new Error(`${distribution} differs from ${source}; expected ${digest(committed)}, built ${digest(built)}.`);
        }
        console.log(`${source} -> ${distribution}: ${digest(built)}`);
    }
} catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    exitStatus = Number.isInteger(error?.exitStatus) ? error.exitStatus : 1;
} finally {
    try {
        cleanup();
    } catch (error) {
        console.error(error instanceof Error ? error.message : String(error));
        if (exitStatus === 0) {
            exitStatus = 1;
        }
    }
}

process.exitCode = exitStatus;
