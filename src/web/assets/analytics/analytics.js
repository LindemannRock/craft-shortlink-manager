(function(window) {
    'use strict';

    window.lrShortlinkAnalyticsInit = function(initConfig) {
        const config = initConfig || {};

        if (window.lrShortlinkAnalyticsBound) {
            if (window.lrAnalyticsInit) {
                window.lrAnalyticsInit(config);
            }
            return;
        }
        window.lrShortlinkAnalyticsBound = true;

        if (window.lrAnalyticsInit) {
            window.lrAnalyticsInit(config);
        }

        const chartColors = window.lrChartColors || [
            '#0d78f2', '#10b981', '#ef4444', '#f59e0b', '#8b5cf6', '#06b6d4',
            '#ec4899', '#84cc16', '#f97316', '#6366f1', '#14b8a6', '#f43f5e'
        ];

        const strings = config.strings || {};
        const dataEndpoint = (window.Craft && Craft.getActionUrl && config.dataEndpoint)
            ? Craft.getActionUrl(config.dataEndpoint)
            : config.dataEndpoint;

        function destroyChart(canvasId, prefix) {
            const chartKey = canvasId.replace(/-/g, '_');
            if (window.lrChartInstances && window.lrChartInstances[prefix] && window.lrChartInstances[prefix][chartKey]) {
                window.lrChartInstances[prefix][chartKey].destroy();
                delete window.lrChartInstances[prefix][chartKey];
            }
        }

        function resetChartState(canvas) {
            if (!canvas) return;
            canvas.style.display = '';
            const parent = canvas.parentElement || canvas.parentNode;
            if (!parent) return;
            parent.querySelectorAll('.zilch').forEach(el => el.remove());
        }

        function renderEmptyState(canvasId, message, prefix) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;
            resetChartState(ctx);
            destroyChart(canvasId, prefix);
            ctx.style.display = 'none';
            const parent = ctx.parentElement || ctx.parentNode;
            if (!parent) return;
            const emptyMsg = document.createElement('div');
            emptyMsg.className = 'zilch';
            emptyMsg.style.padding = '48px 24px';
            emptyMsg.style.textAlign = 'center';
            emptyMsg.innerHTML = '<p>' + message + '</p>';
            parent.appendChild(emptyMsg);
        }

        function setPeakInfo(text) {
            const el = document.getElementById('peak-hour-info');
            if (el) {
                el.innerHTML = text || '';
            }
        }

        function requestData(type, params, onSuccess, onError) {
            if (!dataEndpoint) {
                if (onError) onError();
                return;
            }

            const data = Object.assign({ type: type }, params || {});

            if (config.csrfName && config.csrfToken) {
                data[config.csrfName] = config.csrfToken;
            }

            if (typeof $ !== 'undefined' && $.ajax) {
                $.ajax({
                    url: dataEndpoint,
                    type: 'POST',
                    dataType: 'json',
                    data: data,
                    success: function(response) {
                        if (response && response.success) {
                            onSuccess(response.data || {});
                        } else if (onError) {
                            onError();
                        }
                    },
                    error: function() {
                        if (onError) onError();
                    }
                });
                return;
            }

            const formData = new FormData();
            Object.keys(data).forEach(function(key) {
                formData.append(key, data[key]);
            });

            fetch(dataEndpoint, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(response) {
                if (response && response.success) {
                    onSuccess(response.data || {});
                } else if (onError) {
                    onError();
                }
            })
            .catch(function() {
                if (onError) onError();
            });
        }

        document.addEventListener('lr:analyticsInit', function(e) {
            const eventConfig = e.detail && e.detail.config ? e.detail.config : (window.lrAnalyticsConfig || {});
            const prefix = eventConfig.prefix || 'analytics';
            const dateRange = eventConfig.dateRange || config.dateRange || 'last7days';
            const siteId = eventConfig.siteId || config.siteId || '';

            loadAllCharts(dateRange, siteId, prefix);
        });

        function loadAllCharts(dateRange, siteId, prefix) {
            const baseParams = { dateRange: dateRange, siteId: siteId };

            requestData('clicks', baseParams, function(data) {
                if (data.labels && data.labels.length > 0) {
                    renderClicksChart(data);
                } else {
                    renderEmptyState('clicks-chart', strings.noInteraction || 'No interaction data available.', prefix);
                }
            }, function() {
                renderEmptyState('clicks-chart', strings.noInteraction || 'No interaction data available.', prefix);
            });

            requestData('devices', baseParams, function(data) {
                const hasData = Array.isArray(data.values) && data.values.some(value => Number(value) > 0);
                if (data.labels && data.labels.length > 0 && hasData) {
                    renderDeviceChart(data);
                } else {
                    renderEmptyState('device-chart', strings.noDevice || 'No device data available.', prefix);
                }
            }, function() {
                renderEmptyState('device-chart', strings.noDevice || 'No device data available.', prefix);
            });

            requestData('device-brands', baseParams, function(data) {
                const hasData = Array.isArray(data.values) && data.values.some(value => Number(value) > 0);
                if (data.labels && data.labels.length > 0 && hasData) {
                    renderBrandChart(data);
                } else {
                    renderEmptyState('brand-chart', strings.noBrand || 'No device brand data available.', prefix);
                }
            }, function() {
                renderEmptyState('brand-chart', strings.noBrand || 'No device brand data available.', prefix);
            });

            requestData('os-breakdown', baseParams, function(data) {
                const hasData = Array.isArray(data.values) && data.values.some(value => Number(value) > 0);
                if (data.labels && data.labels.length > 0 && hasData) {
                    renderOsChart(data);
                } else {
                    renderEmptyState('os-chart', strings.noOs || 'No OS data available.', prefix);
                }
            }, function() {
                renderEmptyState('os-chart', strings.noOs || 'No OS data available.', prefix);
            });

            requestData('browsers', baseParams, function(data) {
                const hasData = Array.isArray(data.values) && data.values.some(value => Number(value) > 0);
                if (data.labels && data.labels.length > 0 && hasData) {
                    renderBrowserChart(data);
                } else {
                    renderEmptyState('browser-chart', strings.noBrowser || 'No browser data available.', prefix);
                }
            }, function() {
                renderEmptyState('browser-chart', strings.noBrowser || 'No browser data available.', prefix);
            });

            requestData('hourly', baseParams, function(data) {
                const hasHourly = Array.isArray(data.data) && data.data.some(value => Number(value) > 0);
                if (data.data && data.data.length > 0 && hasHourly) {
                    renderHourlyChart(data);
                } else {
                    renderEmptyState('hourly-chart', strings.noHourly || 'No hourly data available.', prefix);
                    setPeakInfo('');
                }
            }, function() {
                renderEmptyState('hourly-chart', strings.noHourly || 'No hourly data available.', prefix);
                setPeakInfo('');
            });
        }

        function renderClicksChart(data) {
            const ctx = document.getElementById('clicks-chart');
            if (!ctx) return;
            resetChartState(ctx);
            window.lrCreateChart('clicks-chart', 'line', {
                labels: data.labels,
                datasets: [{
                    label: strings.interactionsLabel || 'Interactions',
                    data: data.values,
                    borderColor: chartColors[0],
                    backgroundColor: chartColors[0] + '20',
                    tension: 0.1,
                    fill: true
                }]
            }, {
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } },
                plugins: { legend: { display: false } }
            });
        }

        function renderDeviceChart(data) {
            const ctx = document.getElementById('device-chart');
            if (!ctx) return;
            resetChartState(ctx);
            window.lrCreateChart('device-chart', 'doughnut', {
                labels: data.labels,
                datasets: [{ data: data.values, backgroundColor: chartColors.slice(0, 6) }]
            }, {
                plugins: { legend: { position: 'bottom' } }
            });
        }

        function renderBrandChart(data) {
            const ctx = document.getElementById('brand-chart');
            if (!ctx) return;
            resetChartState(ctx);

            window.lrCreateChart('brand-chart', 'bar', {
                labels: data.labels,
                datasets: [{
                    label: strings.interactionsLabel || 'Interactions',
                    data: data.values,
                    backgroundColor: chartColors[0],
                    borderColor: chartColors[0],
                    borderWidth: 1
                }]
            }, {
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } },
                plugins: { legend: { display: false } }
            });
        }

        function renderOsChart(data) {
            const ctx = document.getElementById('os-chart');
            if (!ctx) return;
            resetChartState(ctx);
            window.lrCreateChart('os-chart', 'doughnut', {
                labels: data.labels,
                datasets: [{ data: data.values, backgroundColor: chartColors }]
            }, {
                plugins: { legend: { position: 'bottom' } }
            });
        }

        function renderBrowserChart(data) {
            const ctx = document.getElementById('browser-chart');
            if (!ctx) return;
            resetChartState(ctx);
            window.lrCreateChart('browser-chart', 'doughnut', {
                labels: data.labels,
                datasets: [{ data: data.values, backgroundColor: chartColors }]
            }, {
                plugins: { legend: { position: 'bottom' } }
            });
        }

        function renderHourlyChart(data) {
            const ctx = document.getElementById('hourly-chart');
            if (!ctx) return;
            resetChartState(ctx);

            window.lrCreateChart('hourly-chart', 'bar', {
                labels: Array.from({ length: 24 }, (_, i) => i + ':00'),
                datasets: [{
                    label: strings.interactionsLabel || 'Interactions',
                    data: data.data,
                    backgroundColor: chartColors[0],
                    borderColor: chartColors[0],
                    borderWidth: 1
                }]
            }, {
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } },
                plugins: { legend: { display: false } }
            });

            if (data.peakHourFormatted) {
                setPeakInfo((strings.peakUsageAt || 'Peak usage at') + ' <strong>' + data.peakHourFormatted + '</strong>');
            } else {
                setPeakInfo('');
            }
        }
    };
})(window);
