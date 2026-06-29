(function() {
    'use strict';

    const configElement = document.querySelector('script[data-shortlink-edit-config]');
    if (!configElement) {
        return;
    }

    let config = {};
    try {
        config = JSON.parse(configElement.textContent || '{}');
    } catch (e) {
        config = {};
    }

    const messages = config.messages || {};
    const urls = config.urls || {};
    const defaults = config.defaults || {};

    function initSaveShortcut() {
        const saveShortcut = document.getElementById('save-shortcut');
        if (saveShortcut) {
            saveShortcut.textContent = navigator.platform.toUpperCase().indexOf('MAC') >= 0 ? '⌘S' : 'Ctrl+S';
        }
    }

    function initHttpStatusHelp() {
        const httpCodeSelect = document.getElementById('httpCode');
        const httpCodeTipText = document.getElementById('httpCodeTipText');
        const httpCodeWarningBox = document.getElementById('httpCodeWarningBox');

        if (!httpCodeSelect) {
            return;
        }

        const statusTips = messages.httpStatusTips || {};

        function updateHttpCodeTip() {
            if (!httpCodeTipText) {
                return;
            }

            const code = parseInt(httpCodeSelect.value, 10);
            const tip = statusTips[String(code)] || '';
            httpCodeTipText.innerHTML = tip;
        }

        function updateHttpCodeWarning() {
            if (!httpCodeWarningBox) {
                return;
            }

            const code = parseInt(httpCodeSelect.value, 10);
            httpCodeWarningBox.style.display = [301, 308].includes(code) ? '' : 'none';
        }

        httpCodeSelect.addEventListener('change', updateHttpCodeTip);
        httpCodeSelect.addEventListener('change', updateHttpCodeWarning);
        updateHttpCodeTip();
        updateHttpCodeWarning();
    }

    function initNewFolderToggle() {
        const folderSelect = document.getElementById('folderId');
        const newFolderField = document.getElementById(config.newFolderFieldId);
        const newFolderInput = document.getElementById('newFolderName');

        if (!folderSelect || !newFolderInput || !newFolderField) {
            return;
        }

        function updateNewFolderField() {
            const creatingNewFolder = folderSelect.value === '__new__';
            newFolderField.classList.toggle('hidden', !creatingNewFolder);

            if (!creatingNewFolder) {
                newFolderInput.value = '';
            }
        }

        folderSelect.addEventListener('change', updateNewFolderField);
        updateNewFolderField();
    }

    function initTagSubmitGuard() {
        const tagsSelectEl = document.getElementById('tags');
        if (!tagsSelectEl || typeof $ === 'undefined') {
            return;
        }

        const tagsSelectize = $(tagsSelectEl).data('selectize');
        const form = tagsSelectEl.closest('form');

        if (!tagsSelectize || !form) {
            return;
        }

        form.addEventListener('submit', function() {
            const raw = (tagsSelectize.$control_input && tagsSelectize.$control_input.val())
                ? String(tagsSelectize.$control_input.val())
                : '';
            const pending = raw.trim();

            if (!pending) {
                return;
            }

            if (!tagsSelectize.options[pending]) {
                tagsSelectize.addOption({value: pending, text: pending});
            }
            tagsSelectize.addItem(pending, true);
            tagsSelectize.$control_input.val('');
        });
    }

    function initLinkTypeToggle() {
        if (typeof $ === 'undefined') {
            return;
        }

        $('#linkType').on('change', function() {
            const type = $(this).val();
            if (type === 'code') {
                $('#code-field-auto').removeClass('hidden');
                $('#code-field-vanity').addClass('hidden');
                $('#codeVanity').prop('disabled', true);
                $('#codeAuto').prop('disabled', false);
            } else {
                $('#code-field-auto').addClass('hidden');
                $('#code-field-vanity').removeClass('hidden');
                $('#codeAuto').prop('disabled', true);
                $('#codeVanity').prop('disabled', false);
            }
        }).trigger('change');
    }

    function initRegenerateCode() {
        if (typeof $ === 'undefined') {
            return;
        }

        $('#regenerate-code-btn').on('click', function() {
            if (!confirm(messages.generateNewCodeConfirm || 'Generate a new code?')) {
                return;
            }

            Craft.sendActionRequest('POST', urls.generateCodeAction || 'shortlink-manager/shortlinks/generate-code')
                .then(function(response) {
                    if (response.data && response.data.code) {
                        $('#current-code-display').text(response.data.code);
                        $('#codeAuto').val(response.data.code);
                        Craft.cp.displayNotice(messages.newCodeGenerated || 'New code generated. Save to apply changes.');
                    }
                })
                .catch(function() {
                    Craft.cp.displayError(messages.generateCodeFailed || 'Failed to generate code');
                });
        });
    }

    function initDisclosureMenu() {
        if (typeof Craft !== 'undefined' && Craft.ui && Craft.ui.createDisclosureMenu) {
            const actionBtn = document.getElementById('action-btn');
            if (actionBtn) {
                new Craft.ui.createDisclosureMenu(actionBtn);
            }
        }
    }

    function initQrDownload() {
        if (typeof $ === 'undefined' || !urls.qrPublicBaseUrl) {
            return;
        }

        $('.download-qr').on('click', function(e) {
            e.preventDefault();

            let size = $(this).data('size');

            if (size === 'custom') {
                const customSize = prompt(messages.enterCustomSize || 'Enter custom size (100-4096 pixels):', '1024');
                if (!customSize) {
                    return;
                }

                size = parseInt(customSize, 10);
                if (isNaN(size) || size < 100 || size > 4096) {
                    alert(messages.invalidCustomSize || 'Please enter a valid size between 100 and 4096 pixels');
                    return;
                }
            }

            const color = ($('#qrCodeColor').val() || '000000').replace(/^#/, '');
            const bgColor = ($('#qrCodeBgColor').val() || 'FFFFFF').replace(/^#/, '');
            const eyeColor = $('#qrCodeEyeColor').val() ? $('#qrCodeEyeColor').val().replace(/^#/, '') : '';
            const format = $('#qrCodeFormat').val() || defaults.qrFormat || 'png';

            let downloadUrl = urls.qrPublicBaseUrl +
                '?size=' + encodeURIComponent(size) +
                '&color=' + encodeURIComponent(color) +
                '&bg=' + encodeURIComponent(bgColor) +
                '&format=' + encodeURIComponent(format) +
                '&download=1';

            if (eyeColor) {
                downloadUrl += '&eyeColor=' + encodeURIComponent(eyeColor);
            }

            const logoField = document.querySelector('#qrLogoId-field .elements .element');
            if (logoField) {
                downloadUrl += '&logo=' + encodeURIComponent(logoField.dataset.id);
            }

            let downloadFrame = document.getElementById('shortlink-qr-download-frame');
            if (!downloadFrame) {
                downloadFrame = document.createElement('iframe');
                downloadFrame.id = 'shortlink-qr-download-frame';
                downloadFrame.style.display = 'none';
                document.body.appendChild(downloadFrame);
            }

            downloadFrame.src = downloadUrl;
        });
    }

    function initQrDefaultsReset() {
        if (typeof $ === 'undefined') {
            return;
        }

        $('#reset-qr-defaults').on('click', function(e) {
            e.preventDefault();
            if (!confirm(messages.resetQrDefaultsConfirm || 'Reset QR code settings to plugin defaults?')) {
                return;
            }

            $('#qrCodeSize').val(defaults.qrSize || 256);
            $('#qrCodeColor').val(defaults.qrColor || '#000000');
            $('#qrCodeBgColor').val(defaults.qrBgColor || '#FFFFFF');
            $('#qrCodeEyeColor').val(defaults.qrEyeColor || '');
            $('#qrCodeFormat').val(defaults.qrFormat || '');

            Craft.cp.displayNotice(messages.qrDefaultsReset || 'QR code settings reset to defaults');
        });
    }

    function init() {
        initSaveShortcut();
        initHttpStatusHelp();
        initNewFolderToggle();
        initTagSubmitGuard();
        initLinkTypeToggle();
        initRegenerateCode();
        initDisclosureMenu();
        initQrDownload();
        initQrDefaultsReset();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
