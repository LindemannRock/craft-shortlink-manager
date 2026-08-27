import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';

const packageRoot = path.resolve(import.meta.dirname, '../..');
const editSource = readFileSync(path.join(packageRoot, 'src/web/assets/edit/src/edit.js'), 'utf8');
const previewSource = readFileSync(path.join(packageRoot, 'src/web/assets/qrpreview/src/qr-preview.js'), 'utf8');

function editorFixture() {
    const clickHandlers = [];
    const alerts = [];
    const frames = [];
    const values = {
        '#qrCodeColor': '#123456',
        '#qrCodeBgColor': '#F5E6D3',
        '#qrCodeEyeColor': '#AA2244',
        '#qrCodeFormat': 'svg',
    };
    let promptValue = null;

    const config = {
        urls: {
            qrDownloadUrl: '/actions/shortlink-manager/qr-code/generate?linkId=42&siteId=1',
        },
        defaults: {
            qrFormat: 'png',
            qrMargin: 0,
            qrErrorCorrection: 'H',
            qrModuleStyle: 'dots',
            qrEyeStyle: 'rounded',
            qrLogoSize: 24,
        },
        messages: {
            enterCustomSize: 'Custom size',
            invalidCustomSize: 'Invalid size',
        },
    };
    const configElement = {textContent: JSON.stringify(config)};
    const logoElement = {dataset: {id: '73'}};
    const document = {
        readyState: 'complete',
        body: {
            appendChild(element) {
                frames.push(element);
            },
        },
        querySelector(selector) {
            if (selector === 'script[data-shortlink-edit-config]') {
                return configElement;
            }
            if (selector === '#qrLogoId-field .elements .element') {
                return logoElement;
            }
            return null;
        },
        getElementById(id) {
            return id === 'shortlink-qr-download-frame' ? frames[0] ?? null : null;
        },
        createElement(tagName) {
            return {tagName, id: '', style: {}, src: ''};
        },
        addEventListener() {},
    };

    function jquery(subject) {
        if (subject === '.download-qr') {
            return {
                on(event, handler) {
                    assert.equal(event, 'click');
                    clickHandlers.push(handler);
                },
            };
        }
        if (typeof subject === 'string') {
            const api = {
                val: () => values[subject] ?? '',
                on() {
                    return api;
                },
                trigger() {
                    return api;
                },
                addClass() {
                    return api;
                },
                removeClass() {
                    return api;
                },
                prop() {
                    return api;
                },
            };
            return api;
        }
        return {
            data: (key) => key === 'size' ? subject.size : undefined,
        };
    }

    vm.runInNewContext(editSource, {
        document,
        navigator: {platform: 'Linux'},
        window: {},
        $: jquery,
        prompt: () => promptValue,
        alert: (message) => alerts.push(message),
        confirm: () => true,
        JSON,
        Number,
        String,
        encodeURIComponent,
        isNaN,
    });

    assert.equal(clickHandlers.length, 1);

    return {
        alerts,
        frames,
        setPrompt(value) {
            promptValue = value;
        },
        click(size) {
            clickHandlers[0].call({size}, {preventDefault() {}});
            return frames[0]?.src ?? '';
        },
    };
}

test('preset downloads use the authenticated action and forward exact unsaved styling', () => {
    const fixture = editorFixture();

    for (const size of [256, 512, 1024, 2048]) {
        const url = new URL(fixture.click(size), 'https://cp.example');
        assert.equal(url.pathname, '/actions/shortlink-manager/qr-code/generate');
        assert.equal(url.searchParams.get('linkId'), '42');
        assert.equal(url.searchParams.get('siteId'), '1');
        assert.equal(url.searchParams.get('size'), String(size));
        assert.equal(url.searchParams.get('color'), '123456');
        assert.equal(url.searchParams.get('bg'), 'F5E6D3');
        assert.equal(url.searchParams.get('format'), 'svg');
        assert.equal(url.searchParams.get('eyeColor'), 'AA2244');
        assert.equal(url.searchParams.get('logo'), '73');
        assert.equal(url.searchParams.get('margin'), '0');
        assert.equal(url.searchParams.get('errorCorrection'), 'H');
        assert.equal(url.searchParams.get('moduleStyle'), 'dots');
        assert.equal(url.searchParams.get('eyeStyle'), 'rounded');
        assert.equal(url.searchParams.get('logoSize'), '24');
        assert.equal(url.searchParams.get('download'), '1');
        assert.doesNotMatch(url.pathname, /\/qr\//);
    }

    assert.equal(fixture.frames.length, 1, 'the hidden frame must be reused');
});

test('custom download accepts both boundaries and rejects malformed or out-of-range input', () => {
    const fixture = editorFixture();

    for (const size of ['100', '4096']) {
        fixture.setPrompt(size);
        const url = new URL(fixture.click('custom'), 'https://cp.example');
        assert.equal(url.searchParams.get('size'), size);
    }

    const lastValidUrl = fixture.frames[0].src;
    for (const invalid of ['99', '4097', '100px']) {
        fixture.setPrompt(invalid);
        assert.equal(fixture.click('custom'), lastValidUrl);
    }
    assert.deepEqual(fixture.alerts, ['Invalid size', 'Invalid size', 'Invalid size']);
    assert.equal(fixture.frames.length, 1);
});

test('linked public preview identity remains canonical', () => {
    assert.match(previewSource, /target\.href = link\.baseUrl;/);
    assert.doesNotMatch(previewSource, /target\.href = link\.baseUrl \+ separator/);
});
