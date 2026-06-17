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

        var geoLoaded = false;
        var recentClicksLoaded = false;
        var currentDateRange = config.dateRange || 'last7days';
        var currentSiteId = config.siteId || '';

        function esc(str) {
            if (typeof Craft !== 'undefined' && Craft.escapeHtml) {
                return Craft.escapeHtml(str);
            }
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        function fmtNum(val) {
            var n = Number(val);
            return isNaN(n) ? '0' : n.toLocaleString();
        }

        function destroyChart(canvasId, prefix) {
            var chartKey = canvasId.replace(/-/g, '_');
            if (window.lrChartInstances && window.lrChartInstances[prefix] && window.lrChartInstances[prefix][chartKey]) {
                window.lrChartInstances[prefix][chartKey].destroy();
                delete window.lrChartInstances[prefix][chartKey];
            }
        }

        function resetChartState(canvas) {
            if (!canvas) return;
            canvas.style.display = '';
            var parent = canvas.parentElement || canvas.parentNode;
            if (!parent) return;
            parent.querySelectorAll('.zilch').forEach(function(el) { el.remove(); });
        }

        function renderEmptyState(canvasId, message, prefix) {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return;
            resetChartState(ctx);
            destroyChart(canvasId, prefix);
            ctx.style.display = 'none';
            var parent = ctx.parentElement || ctx.parentNode;
            if (!parent) return;
            var emptyMsg = document.createElement('div');
            emptyMsg.className = 'zilch';
            emptyMsg.style.padding = '48px 24px';
            emptyMsg.style.textAlign = 'center';
            var p = document.createElement('p');
            p.textContent = message;
            emptyMsg.appendChild(p);
            parent.appendChild(emptyMsg);
        }

        function setPeakInfo(text) {
            var el = document.getElementById('peak-hour-info');
            if (el) {
                el.textContent = text || '';
            }
        }

        function renderTableEmpty(tbodyId, colspan, message) {
            var tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="thin light lr-text-center">' + esc(message) + '</td></tr>';
        }

        function requestData(type, params, onSuccess, onError) {
            if (!dataEndpoint) {
                if (onError) onError();
                return;
            }

            var data = Object.assign({ type: type }, params || {});

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

            var formData = new FormData();
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

        function getActiveTabId() {
            var hash = window.location.hash ? window.location.hash.substring(1) : '';
            if (hash && document.getElementById(hash)) {
                return hash;
            }
            var visible = document.querySelector('.lr-tab-content:not(.hidden)');
            return visible ? visible.id : 'overview';
        }

        document.addEventListener('lr:analyticsInit', function(e) {
            var eventConfig = e.detail && e.detail.config ? e.detail.config : (window.lrAnalyticsConfig || {});
            var prefix = eventConfig.prefix || 'analytics';
            currentDateRange = eventConfig.dateRange || config.dateRange || 'last7days';
            currentSiteId = eventConfig.siteId || config.siteId || '';

            // Reset guard flags on re-init (date range / site change)
            geoLoaded = false;
            recentClicksLoaded = false;

            loadAllCharts(currentDateRange, currentSiteId, prefix);
            loadRecentClicks(currentDateRange, currentSiteId);

            // Reload the currently active tab (e.g. geographic) if not overview
            var activeTab = getActiveTabId();
            if (activeTab === 'geographic') {
                loadGeographic(currentDateRange, currentSiteId);
            }
        });

        document.addEventListener('lr:tabChanged', function(e) {
            var tabId = e.detail && e.detail.tabId ? e.detail.tabId : '';

            if (tabId === 'geographic' && !geoLoaded) {
                loadGeographic(currentDateRange, currentSiteId);
            }
        });

        function loadAllCharts(dateRange, siteId, prefix) {
            var baseParams = { dateRange: dateRange, siteId: siteId };

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
                var hasData = Array.isArray(data.values) && data.values.some(function(v) { return Number(v) > 0; });
                if (data.labels && data.labels.length > 0 && hasData) {
                    renderDeviceChart(data);
                } else {
                    renderEmptyState('device-chart', strings.noDevice || 'No device data available.', prefix);
                }
            }, function() {
                renderEmptyState('device-chart', strings.noDevice || 'No device data available.', prefix);
            });

            requestData('traffic-types', baseParams, function(data) {
                var hasData = Array.isArray(data.values) && data.values.some(function(v) { return Number(v) > 0; });
                if (data.types && data.types.length > 0 && hasData) {
                    renderTrafficTypeChart(data);
                } else {
                    renderEmptyState('traffic-type-chart', strings.noTrafficType || 'No traffic type data available.', prefix);
                }
            }, function() {
                renderEmptyState('traffic-type-chart', strings.noTrafficType || 'No traffic type data available.', prefix);
            });

            requestData('top-agents', baseParams, function(data) {
                renderTopAgents(Array.isArray(data) ? data : []);
            }, function() {
                renderTopAgents([]);
            });

            requestData('device-brands', baseParams, function(data) {
                var hasData = Array.isArray(data.values) && data.values.some(function(v) { return Number(v) > 0; });
                if (data.labels && data.labels.length > 0 && hasData) {
                    renderBrandChart(data);
                } else {
                    renderEmptyState('brand-chart', strings.noBrand || 'No device brand data available.', prefix);
                }
            }, function() {
                renderEmptyState('brand-chart', strings.noBrand || 'No device brand data available.', prefix);
            });

            requestData('os-breakdown', baseParams, function(data) {
                var hasData = Array.isArray(data.values) && data.values.some(function(v) { return Number(v) > 0; });
                if (data.labels && data.labels.length > 0 && hasData) {
                    renderOsChart(data);
                } else {
                    renderEmptyState('os-chart', strings.noOs || 'No OS data available.', prefix);
                }
            }, function() {
                renderEmptyState('os-chart', strings.noOs || 'No OS data available.', prefix);
            });

            requestData('browsers', baseParams, function(data) {
                var hasData = Array.isArray(data.values) && data.values.some(function(v) { return Number(v) > 0; });
                if (data.labels && data.labels.length > 0 && hasData) {
                    renderBrowserChart(data);
                } else {
                    renderEmptyState('browser-chart', strings.noBrowser || 'No browser data available.', prefix);
                }
            }, function() {
                renderEmptyState('browser-chart', strings.noBrowser || 'No browser data available.', prefix);
            });

            requestData('hourly', baseParams, function(data) {
                var hasHourly = Array.isArray(data.data) && data.data.some(function(v) { return Number(v) > 0; });
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

        function loadRecentClicks(dateRange, siteId) {
            var baseParams = { dateRange: dateRange, siteId: siteId };
            recentClicksLoaded = true;

            requestData('recent-clicks', baseParams, function(data) {
                renderRecentClicks(data);
            }, function() {
                var geoEnabled = strings.geoEnabled || false;
                renderTableEmpty('recent-clicks-body', geoEnabled ? 11 : 10, strings.noRecentClicks || 'No interactions recorded yet');
            });
        }

        function loadGeographic(dateRange, siteId) {
            var baseParams = { dateRange: dateRange, siteId: siteId };
            geoLoaded = true;

            requestData('top-countries', baseParams, function(data) {
                renderTopCountries(data);
            }, function() {
                renderTableEmpty('top-countries-body', 3, strings.noCountry || 'No country data available');
            });

            requestData('top-cities', baseParams, function(data) {
                renderTopCities(data);
            }, function() {
                renderTableEmpty('top-cities-body', 4, strings.noCity || 'No city data available');
            });
        }

        function renderRecentClicks(data) {
            var tbody = document.getElementById('recent-clicks-body');
            if (!tbody) return;

            var clicks = data.clicks || [];
            var geoEnabled = data.geoEnabled || false;
            var colSpan = geoEnabled ? 11 : 10;

            if (clicks.length === 0) {
                renderTableEmpty('recent-clicks-body', colSpan, strings.noRecentClicks || 'No interactions recorded yet');
                return;
            }

            var editUrl = strings.shortlinksEditUrl || '';
            var html = '';

            for (var i = 0; i < clicks.length; i++) {
                var c = clicks[i];
                var dest = c.destinationUrl || '';
                var destDisplay = dest.length > 30 ? dest.substring(0, 30) + '...' : dest;
                var sourceLabel = c.source === 'qr' ? (strings.sourceQr || 'QR') : (strings.sourceDirect || 'Direct');

                html += '<tr>';
                html += '<td style="white-space:nowrap">' + esc(c.dateFormatted || '\u2014') + '</td>';
                html += '<td style="white-space:nowrap">' + esc(c.timeFormatted || '\u2014') + '</td>';
                html += '<td>';
                if (c.linkId && editUrl) {
                    html += '<a href="' + esc(editUrl + '/' + c.linkId) + '"><code>' + esc(c.linkCode || '') + '</code></a>';
                } else {
                    html += '<code>' + esc(c.linkCode || '') + '</code>';
                }
                html += '</td>';
                html += '<td>' + esc(c.siteName || '\u2014') + '</td>';
                html += '<td>' + esc(sourceLabel) + '</td>';
                html += '<td>';
                if (dest) {
                    html += '<span title="' + esc(dest) + '">' + esc(destDisplay) + '</span>';
                } else {
                    html += '\u2014';
                }
                html += '</td>';
                html += '<td>' + esc(agentLabel(c)) + '</td>';
                html += '<td>' + esc(c.deviceType ? c.deviceType.charAt(0).toUpperCase() + c.deviceType.slice(1) : '\u2014') + '</td>';
                html += '<td>' + esc(c.browser || '\u2014') + '</td>';
                html += '<td>' + esc(c.osName || '\u2014') + '</td>';
                if (geoEnabled) {
                    html += '<td style="white-space:nowrap">' + esc(c.location || '\u2014') + '</td>';
                }
                html += '</tr>';
            }

            tbody.innerHTML = html;
        }

        function trafficTypeLabel(type) {
            if (type === 'system') return strings.system || 'System';
            if (type === 'bot') return strings.bot || 'Bot';
            return strings.human || 'Human';
        }

        function agentLabel(row) {
            if (row.botName) return row.botName;
            return trafficTypeLabel(row.trafficType || 'human');
        }

        function renderTopCountries(data) {
            var tbody = document.getElementById('top-countries-body');
            if (!tbody) return;

            var items = Array.isArray(data) ? data : [];
            if (items.length === 0) {
                renderTableEmpty('top-countries-body', 3, strings.noCountry || 'No country data available');
                return;
            }

            var html = '';
            for (var i = 0; i < items.length; i++) {
                var c = items[i];
                html += '<tr>';
                html += '<td>' + esc(c.name || c.country || '\u2014') + '</td>';
                html += '<td>' + fmtNum(c.clicks) + '</td>';
                html += '<td>' + esc(String(c.percentage || 0)) + '%</td>';
                html += '</tr>';
            }
            tbody.innerHTML = html;
        }

        function renderTopCities(data) {
            var tbody = document.getElementById('top-cities-body');
            if (!tbody) return;

            var items = Array.isArray(data) ? data : [];
            if (items.length === 0) {
                renderTableEmpty('top-cities-body', 4, strings.noCity || 'No city data available');
                return;
            }

            var html = '';
            for (var i = 0; i < items.length; i++) {
                var c = items[i];
                html += '<tr>';
                html += '<td>' + esc(c.city || '\u2014') + '</td>';
                html += '<td>' + esc(c.countryName || '\u2014') + '</td>';
                html += '<td>' + fmtNum(c.clicks) + '</td>';
                html += '<td>' + esc(String(c.percentage || 0)) + '%</td>';
                html += '</tr>';
            }
            tbody.innerHTML = html;
        }

        function renderClicksChart(data) {
            var ctx = document.getElementById('clicks-chart');
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
            var ctx = document.getElementById('device-chart');
            if (!ctx) return;
            resetChartState(ctx);
            window.lrCreateChart('device-chart', 'doughnut', {
                labels: data.labels,
                datasets: [{ data: data.values, backgroundColor: chartColors.slice(0, 6) }]
            }, {
                plugins: { legend: { position: 'bottom' } }
            });
        }

        function renderTrafficTypeChart(data) {
            var ctx = document.getElementById('traffic-type-chart');
            if (!ctx) return;
            resetChartState(ctx);
            window.lrCreateChart('traffic-type-chart', 'doughnut', {
                labels: (data.types || []).map(trafficTypeLabel),
                datasets: [{ data: data.values, backgroundColor: chartColors.slice(0, 3) }]
            }, {
                plugins: { legend: { position: 'bottom' } }
            });
        }

        function renderTopAgents(agents) {
            var tbody = document.getElementById('top-agents-body');
            if (!tbody) return;

            if (!agents.length) {
                renderTableEmpty('top-agents-body', 5, strings.noAgentData || 'No agent data available');
                return;
            }

            var html = '';
            for (var i = 0; i < agents.length; i++) {
                var agent = agents[i];
                html += '<tr>' +
                    '<td>' + esc(agent.botName || '\u2014') + '</td>' +
                    '<td>' + esc(trafficTypeLabel(agent.trafficType || 'human')) + '</td>' +
                    '<td>' + esc(agent.botCategory || '\u2014') + '</td>' +
                    '<td>' + esc(agent.botProducerName || '\u2014') + '</td>' +
                    '<td>' + Number(agent.clicks || 0).toLocaleString() + '</td>' +
                    '</tr>';
            }
            tbody.innerHTML = html;
        }

        function renderBrandChart(data) {
            var ctx = document.getElementById('brand-chart');
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
            var ctx = document.getElementById('os-chart');
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
            var ctx = document.getElementById('browser-chart');
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
            var ctx = document.getElementById('hourly-chart');
            if (!ctx) return;
            resetChartState(ctx);

            window.lrCreateChart('hourly-chart', 'bar', {
                labels: Array.from({ length: 24 }, function(_, i) { return i + ':00'; }),
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
                setPeakInfo((strings.peakUsageAt || 'Peak usage at') + ' ' + data.peakHourFormatted);
            } else {
                setPeakInfo('');
            }
        }
    };
})(window);
