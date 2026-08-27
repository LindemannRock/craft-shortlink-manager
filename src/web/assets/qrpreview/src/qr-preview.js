(function() {
    'use strict';

    const configElement = document.querySelector('script[data-shortlink-qr-preview-config]');
    if (!configElement) {
        return;
    }

    let config = {};
    try {
        config = JSON.parse(configElement.textContent || '{}');
    } catch (e) {
        config = {};
    }

    const fields = config.fields || {};
    const logo = config.logo || {};
    const preview = config.preview || {};
    const link = config.link || {};
    const form = document.querySelector('form');

    function field(id) {
        return id ? document.getElementById(id) : null;
    }

    function value(key, fallback) {
        const element = field(fields[key]);
        return element && element.value !== '' ? element.value : fallback;
    }

    function colorValue(key, fallback) {
        return String(value(key, fallback)).replace(/^#/, '');
    }

    function getLightswitchContainer(element) {
        return element ? element.closest('.lightswitch') : null;
    }

    function isLogoEnabled() {
        if (logo.enabled === false) {
            return false;
        }

        if (!fields.logoSwitch) {
            return logo.enabled === true;
        }

        const element = field(fields.logoSwitch);
        const container = getLightswitchContainer(element);

        return !!element && (
            element.checked ||
            element.value === '1' ||
            element.classList.contains('on') ||
            (container && container.classList.contains('on'))
        );
    }

    function getLogoId() {
        const logoElement = logo.fieldSelector ? document.querySelector(logo.fieldSelector) : null;
        return logoElement ? logoElement.getAttribute('data-id') : (logo.defaultLogoId || null);
    }

    function appendIfPresent(params, key, paramName, fallback) {
        const currentValue = value(key, fallback);
        if (currentValue !== null && currentValue !== undefined && currentValue !== '') {
            params.append(paramName || key, currentValue);
        }
    }

    function appendColorIfPresent(params, key, paramName, fallback) {
        const currentValue = colorValue(key, fallback);
        if (currentValue !== null && currentValue !== undefined && currentValue !== '') {
            params.append(paramName || key, currentValue);
        }
    }

    function buildParams(sizeOverride) {
        const params = new URLSearchParams(config.staticParams || {});

        params.set('size', sizeOverride || value('size', config.defaults?.size || '256'));
        appendIfPresent(params, 'format', 'format', config.defaults?.format || 'png');
        appendColorIfPresent(params, 'color', 'color', config.defaults?.color || '#000000');
        appendColorIfPresent(params, 'bg', 'bg', config.defaults?.bg || '#FFFFFF');
        appendIfPresent(params, 'margin', 'margin', config.defaults?.margin || '4');
        appendIfPresent(params, 'errorCorrection', 'errorCorrection', config.defaults?.errorCorrection || 'M');
        appendIfPresent(params, 'moduleStyle', 'moduleStyle', config.defaults?.moduleStyle || 'square');
        appendIfPresent(params, 'eyeStyle', 'eyeStyle', config.defaults?.eyeStyle || 'square');

        const eyeColor = colorValue('eyeColor', '');
        if (eyeColor) {
            params.append('eyeColor', eyeColor);
        }

        if (isLogoEnabled()) {
            const logoId = getLogoId();
            if (logoId) {
                params.append('logo', logoId);
                appendIfPresent(params, 'logoSize', 'logoSize', config.defaults?.logoSize || '20');
            }
        }

        return params;
    }

    function buildPreviewUrl() {
        const params = buildParams(preview.size || null);
        const action = config.generateAction || 'shortlink-manager/qr-code/generate';

        if (config.generateUrl) {
            const separator = config.generateUrl.indexOf('?') === -1 ? '?' : '&';
            return config.generateUrl + separator + params.toString();
        }

        return Craft.getCpUrl(action + '?' + params.toString());
    }

    function updateLinkedQrUrl() {
        if (!link.baseUrl || !link.selector) {
            return;
        }

        const target = document.querySelector(link.selector);
        if (!target) {
            return;
        }

        target.href = link.baseUrl;
    }

    function initPreviewToggle() {
        const container = document.querySelector(preview.containerSelector || '.qr-preview-floating');
        const header = document.querySelector(preview.headerSelector || '.qr-preview-header');
        if (!container || !header) {
            return;
        }

        const storageKey = preview.collapseStorageKey || 'shortlink-qr-preview-collapsed';
        if (localStorage.getItem(storageKey) === 'true') {
            container.classList.add('collapsed');
        }

        header.addEventListener('click', function() {
            container.classList.toggle('collapsed');
            localStorage.setItem(storageKey, container.classList.contains('collapsed'));
        });
    }

    function initLogoValidation() {
        if (!form || !logo.required) {
            return;
        }

        form.addEventListener('submit', function(e) {
            const logoField = field(logo.fieldId);
            if (!isLogoEnabled() || (logoField && logoField.querySelector('.elements .element'))) {
                return;
            }

            e.preventDefault();

            const existingError = logoField.querySelector('.errors');
            if (existingError) {
                existingError.remove();
            }

            const errorList = document.createElement('ul');
            errorList.className = 'errors';
            const errorItem = document.createElement('li');
            errorItem.textContent = config.messages?.logoRequired || 'A logo is required when logo overlay is enabled.';
            errorList.appendChild(errorItem);
            logoField.appendChild(errorList);
            logoField.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
    }

    function initLivePreview() {
        let previewTimeout = null;
        let isGenerating = false;

        const previewImg = document.querySelector(preview.imageSelector || '#qr-preview');
        const loadingDiv = document.querySelector(preview.loadingSelector || '#qr-preview-loading');
        const errorDiv = document.querySelector(preview.errorSelector || '#qr-preview-error');
        const warningDiv = document.querySelector(preview.warningSelector || '#qr-preview-warning');

        if (!previewImg) {
            return;
        }

        function updatePreview(immediate) {
            if (previewTimeout) {
                clearTimeout(previewTimeout);
            }

            if (isGenerating && !immediate) {
                return;
            }

            previewTimeout = setTimeout(generatePreview, immediate ? 0 : (preview.debounce || 250));
        }

        function generatePreview() {
            if (isGenerating) {
                return;
            }

            isGenerating = true;

            previewImg.style.opacity = '0.5';
            if (errorDiv) {
                errorDiv.style.display = 'none';
            }
            if (warningDiv) {
                warningDiv.style.display = 'none';
            }

            const qrUrl = buildPreviewUrl();
            const testImg = new Image();

            testImg.onload = function() {
                previewImg.src = qrUrl;
                previewImg.style.display = 'block';
                previewImg.style.opacity = '1';

                const bgColor = colorValue('bg', config.defaults?.bg || '#FFFFFF');
                if (bgColor) {
                    previewImg.style.background = '#' + bgColor;
                }

                if (loadingDiv) {
                    loadingDiv.style.display = 'none';
                }
                if (errorDiv) {
                    errorDiv.style.display = 'none';
                }

                if (warningDiv && isLogoEnabled() && value('format', config.defaults?.format || 'png') === 'svg') {
                    warningDiv.style.display = 'block';
                    const warningText = warningDiv.querySelector('.warning-text');
                    if (warningText) {
                        warningText.textContent = config.messages?.logoWarning || 'Logo requires PNG format';
                    }
                } else if (warningDiv) {
                    warningDiv.style.display = 'none';
                }

                updateLinkedQrUrl();
                isGenerating = false;
            };

            testImg.onerror = function() {
                previewImg.style.opacity = '1';
                if (loadingDiv) {
                    loadingDiv.style.display = 'none';
                }
                if (errorDiv) {
                    errorDiv.style.display = 'block';
                    errorDiv.textContent = config.messages?.failed || 'Failed to generate preview';
                }
                isGenerating = false;
            };

            const separator = qrUrl.indexOf('?') === -1 ? '?' : '&';
            testImg.src = qrUrl + separator + 't=' + Date.now();
        }

        function bindFieldEvents(element) {
            if (!element) {
                return;
            }

            ['change', 'input', 'keyup', 'blur', 'paste'].forEach(function(event) {
                element.addEventListener(event, function() {
                    updatePreview(event === 'change' || event === 'blur');
                });
            });
        }

        Object.keys(fields).forEach(function(key) {
            if (key !== 'logoSwitch') {
                bindFieldEvents(field(fields[key]));
            }
        });

        const logoSizeField = field(fields.logoSize);
        if (logoSizeField && config.logoSizeMax) {
            const enforceLogoSizeMax = function() {
                const currentValue = parseInt(logoSizeField.value, 10);
                if (currentValue > config.logoSizeMax) {
                    logoSizeField.value = String(config.logoSizeMax);
                }
            };

            logoSizeField.addEventListener('input', enforceLogoSizeMax);
            logoSizeField.addEventListener('paste', function() {
                setTimeout(enforceLogoSizeMax, 0);
            });
        }

        ['color', 'bg', 'eyeColor'].forEach(function(key) {
            const element = field(fields[key]);
            const fieldContainer = element ? element.closest('.field') : null;
            if (!element || !fieldContainer) {
                return;
            }

            new MutationObserver(function() {
                updatePreview(true);
            }).observe(element, {
                attributes: true,
                attributeFilter: ['value']
            });

            new MutationObserver(function() {
                updatePreview(true);
            }).observe(fieldContainer, {
                childList: true,
                subtree: true,
                characterData: true
            });
        });

        const logoSwitch = field(fields.logoSwitch);
        const logoSwitchContainer = getLightswitchContainer(logoSwitch);
        if (logoSwitchContainer) {
            if (logoSwitchContainer.lsInstance) {
                logoSwitchContainer.lsInstance.on('change', function() {
                    updatePreview(true);
                });
            }

            new MutationObserver(function() {
                updatePreview(true);
            }).observe(logoSwitchContainer, {
                attributes: true,
                attributeFilter: ['class']
            });
        }

        if (logoSwitch) {
            logoSwitch.addEventListener('change', function() {
                updatePreview(true);
            });

            new MutationObserver(function() {
                updatePreview(true);
            }).observe(logoSwitch, {
                attributes: true,
                attributeFilter: ['value']
            });
        }

        const logoFieldContainer = field(logo.fieldId);
        if (logoFieldContainer) {
            new MutationObserver(function(mutations) {
                const logoChanged = mutations.some(function(mutation) {
                    return mutation.type === 'childList' ||
                        mutation.type === 'attributes' ||
                        mutation.type === 'characterData';
                });

                if (logoChanged) {
                    updatePreview(true);
                }
            }).observe(logoFieldContainer, {
                childList: true,
                subtree: true,
                attributes: true,
                characterData: true
            });
        }

        let lastValues = readPolledValues();
        setInterval(function() {
            const currentValues = readPolledValues();
            if (JSON.stringify(currentValues) !== JSON.stringify(lastValues)) {
                lastValues = currentValues;
                updatePreview(true);
            }
        }, 500);

        function readPolledValues() {
            return {
                color: value('color', ''),
                bg: value('bg', ''),
                eyeColor: value('eyeColor', ''),
                logoEnabled: isLogoEnabled(),
                logoId: getLogoId()
            };
        }

        setTimeout(generatePreview, 100);
    }

    function init() {
        initPreviewToggle();
        initLogoValidation();
        initLivePreview();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
