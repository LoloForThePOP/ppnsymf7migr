document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('source-urls-table');
    const toggleHideDone = document.getElementById('toggle-hide-done');
    const queueLabel = document.getElementById('queue-state-label');
    const runBtn = document.getElementById('queue-run-btn');
    const pauseBtn = document.getElementById('queue-pause-btn');
    const resumeBtn = document.getElementById('queue-resume-btn');
    const workerBadge = document.querySelector('.js-worker-badge');
    const workerText = document.querySelector('.js-worker-text');
    const workerCopyBtn = document.querySelector('.js-copy-worker-command');
    const workerCopyFeedback = document.querySelector('.js-copy-worker-feedback');
    const aiInputModalEl = document.getElementById('ai-input-modal');
    const aiInputModalContent = document.getElementById('ai-input-modal-content');
    const aiInputModalMeta = document.getElementById('ai-input-modal-meta');
    const aiInputModal = aiInputModalEl && window.bootstrap ? new bootstrap.Modal(aiInputModalEl) : null;
    const resultModalEl = document.getElementById('result-modal');
    const resultModalLabel = document.getElementById('result-modal-label');
    const resultModalContent = document.getElementById('result-modal-content');
    const resultModal = resultModalEl && window.bootstrap ? new bootstrap.Modal(resultModalEl) : null;
    const sourceRunForm = document.getElementById('source-run-form');

    const pollDelayActive = 4000;
    const finishedStatuses = new Set(['done', 'normalized', 'skipped', 'error']);
    const currentSource = table ? (table.dataset.source || '') : '';

    let pollTimer = null;
    let pollInFlight = false;
    let formActionInFlight = false;
    let queueState = queueLabel ? queueLabel.dataset.queueState : 'stopped';

    const statusClass = (status) => {
        if (status === 'done') return 'success';
        if (status === 'normalized') return 'info text-dark';
        if (status === 'error') return 'danger';
        if (status === 'processing') return 'primary';
        if (status === 'queued') return 'secondary';
        if (status === 'pending') return 'warning text-dark';
        return 'secondary';
    };

    const escapeHtml = (value) => {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const payloadClass = (status) => {
        if (status === 'ok') return 'success';
        if (status === 'weak') return 'warning text-dark';
        if (status === 'too_thin') return 'danger';
        return 'secondary';
    };

    const buildResultFetchUrl = (source, resultKey) => {
        if (!table || !table.dataset.resultUrl) return null;
        const url = new URL(table.dataset.resultUrl, window.location.origin);
        url.searchParams.set('source', source);
        url.searchParams.set('key', resultKey);
        return url.toString();
    };

    const buildResultActionsHtml = (resultKey, source) => {
        const safeKey = escapeHtml(resultKey || '');
        const safeSource = escapeHtml(source || '');
        return '<div class="d-flex flex-column gap-1">'
            + '<button type="button" class="btn btn-link p-0 text-start js-result-modal" data-kind="raw" data-result-key="' + safeKey + '" data-source="' + safeSource + '">JSON brut (modal)</button>'
            + '<button type="button" class="btn btn-link p-0 text-start js-result-modal" data-kind="debug" data-result-key="' + safeKey + '" data-source="' + safeSource + '">Debug (modal)</button>'
            + '<button type="button" class="btn btn-link p-0 js-ai-input" data-result-key="' + safeKey + '" data-source="' + safeSource + '">Entrée IA (modal)</button>'
            + '</div>';
    };

    const fetchResultPayload = async (source, resultKey) => {
        const requestUrl = buildResultFetchUrl(source, resultKey);
        if (!requestUrl) {
            throw new Error('URL résultat indisponible');
        }
        const response = await fetch(requestUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) {
            throw new Error('Chargement impossible.');
        }
        return await response.json();
    };

    const applyHideDone = () => {
        if (!table || !toggleHideDone) return;
        const hideDone = toggleHideDone.checked;
        table.querySelectorAll('tbody tr[data-status]').forEach((row) => {
            const status = (row.dataset.status || '').toLowerCase();
            const isCurrentSessionDone = row.dataset.sessionDone === '1';
            const shouldHide = hideDone && finishedStatuses.has(status) && !isCurrentSessionDone;
            row.classList.toggle('d-none', shouldHide);
        });
    };

    const updateQueueState = (queue) => {
        if (!queueLabel || !queue) return;
        let label = 'arrêtée';
        let state = 'stopped';
        if (queue.paused) {
            label = 'en pause';
            state = 'paused';
        } else if (queue.running) {
            label = 'en cours';
            state = 'running';
        }
        queueLabel.textContent = `File : ${label}`;
        queueLabel.dataset.queueState = state;
        queueState = state;
        if (runBtn) runBtn.disabled = queue.running && !queue.paused;
        if (pauseBtn) pauseBtn.disabled = queue.paused;
        if (resumeBtn) resumeBtn.disabled = !queue.paused;
        if (state !== 'running') {
            stopPolling();
        }
    };

    const shouldPoll = () => document.visibilityState === 'visible' && queueState === 'running';

    const stopPolling = () => {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
    };

    const schedulePoll = (delay) => {
        stopPolling();
        if (!shouldPoll()) return;
        pollTimer = window.setTimeout(() => refresh(), delay);
    };

    const updateWorkerStatus = (worker) => {
        if (!worker || !workerBadge || !workerText) return;
        const active = Boolean(worker.active);
        workerBadge.classList.toggle('bg-success', active);
        workerBadge.classList.toggle('bg-secondary', !active);
        workerBadge.textContent = active ? 'Worker actif' : 'Worker inactif';
        workerBadge.dataset.workerActive = active ? '1' : '0';
        workerText.textContent = `Derniere activite : ${worker.last_seen_label || 'jamais'}`;
    };

    const bindWorkerCopy = () => {
        if (!workerCopyBtn) return;
        workerCopyBtn.addEventListener('click', async () => {
            const command = workerCopyBtn.dataset.command || '';
            if (!command) return;
            let copied = false;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                try {
                    await navigator.clipboard.writeText(command);
                    copied = true;
                } catch (e) {
                    copied = false;
                }
            }
            if (!copied) {
                const temp = document.createElement('textarea');
                temp.value = command;
                temp.setAttribute('readonly', 'readonly');
                temp.style.position = 'absolute';
                temp.style.left = '-9999px';
                document.body.appendChild(temp);
                temp.select();
                copied = document.execCommand('copy');
                document.body.removeChild(temp);
            }
            if (copied && workerCopyFeedback) {
                workerCopyFeedback.classList.remove('d-none');
                window.setTimeout(() => workerCopyFeedback.classList.add('d-none'), 1200);
            }
        });
    };

    const updateSummary = (summary) => {
        if (!summary) return;
        document.querySelectorAll('[data-summary]').forEach((badge) => {
            const key = badge.getAttribute('data-summary');
            if (!key || summary[key] === undefined) return;
            const parts = badge.textContent.split(' ');
            parts[parts.length - 1] = summary[key];
            badge.textContent = parts.join(' ');
        });
    };

    const updateRow = (row, entry) => {
        if (!row || !entry) return;
        const previousStatus = (row.dataset.status || '').toLowerCase();
        const status = (entry.status || 'pending').toLowerCase();
        row.dataset.status = status;
        row.dataset.recentResult = entry.has_result ? '1' : '0';
        if (finishedStatuses.has(status) && !finishedStatuses.has(previousStatus)) {
            row.dataset.sessionDone = '1';
        }

        const statusCell = row.querySelector('[data-col="status"]');
        if (statusCell) {
            statusCell.innerHTML = `<span class="badge bg-${statusClass(status)}">${status}</span>`;
        }

        const progressCell = row.querySelector('[data-col="progress"]');
        if (progressCell) {
            const isActive = status === 'processing' || status === 'queued';
            progressCell.innerHTML = isActive
                ? '<span class="processing-indicator d-inline-flex align-items-center gap-1"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span>' + (status === 'queued' ? 'en file' : 'processing') + '</span></span>'
                : '<span class="processing-indicator d-none align-items-center gap-1"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span>processing</span></span>';
        }

        const payloadCell = row.querySelector('[data-col="payload"]');
        if (payloadCell) {
            const payloadStatus = entry.payload_status || '';
            const aiStatus = entry.ai_payload_status || '';
            const aiReason = entry.ai_payload_reason || '';
            const parts = [];
            if (payloadStatus) {
                parts.push('<span class="badge bg-' + payloadClass(payloadStatus) + '">' + payloadStatus + '</span>'
                    + '<div class="text-muted small">' + (entry.payload_text_chars || 0) + ' car., ' + (entry.payload_links || 0) + ' liens, ' + (entry.payload_images || 0) + ' images</div>');
            }
            if (aiStatus) {
                const aiBadge = payloadClass(aiStatus);
                const reasonHtml = aiReason ? '<div class="text-muted small">' + escapeHtml(aiReason) + '</div>' : '';
                parts.push('<div class="' + (payloadStatus ? 'mt-1' : '') + '"><span class="badge bg-' + aiBadge + '">AI ' + aiStatus + '</span>' + reasonHtml + '</div>');
            }
            payloadCell.innerHTML = parts.length ? parts.join('') : '-';
        }

        const lastRunCell = row.querySelector('[data-col="last-run"]');
        if (lastRunCell) lastRunCell.textContent = entry.last_run_at || '-';

        const createdCell = row.querySelector('[data-col="created-url"]');
        if (createdCell) {
            createdCell.innerHTML = entry.created_url
                ? '<a href="' + entry.created_url + '" target="_blank" rel="noopener noreferrer">Ouvrir</a>'
                : '-';
        }

        const lastResultCell = row.querySelector('[data-col="last-result"]');
        if (lastResultCell) {
            lastResultCell.innerHTML = entry.created_url
                ? '<a href="' + entry.created_url + '" target="_blank" rel="noopener noreferrer">Ouvrir</a>'
                : '-';
        }

        const jsonCell = row.querySelector('[data-col="json"]');
        if (jsonCell) {
            if (entry.has_result && entry.result_key) {
                jsonCell.innerHTML = buildResultActionsHtml(entry.result_key, currentSource);
            } else {
                jsonCell.textContent = '-';
            }
        }

        const errorCell = row.querySelector('[data-col="error"]');
        if (errorCell) errorCell.textContent = entry.error || '-';
    };

    const refresh = async () => {
        if (!table || !table.dataset.statusUrl) return;
        if (pollInFlight) return;
        pollInFlight = true;
        try {
            const urls = Array.from(table.querySelectorAll('tbody tr[data-url]'))
                .map((row) => row.dataset.url)
                .filter((value) => value && value.length > 0);
            const data = new FormData();
            urls.forEach((url) => data.append('urls[]', url));
            const response = await fetch(table.dataset.statusUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (payload.queue) updateQueueState(payload.queue);
            if (payload.summary) updateSummary(payload.summary);
            if (payload.worker) updateWorkerStatus(payload.worker);

            if (Array.isArray(payload.entries)) {
                const rowMap = new Map();
                table.querySelectorAll('tbody tr[data-url]').forEach((row) => rowMap.set(row.dataset.url, row));
                payload.entries.forEach((entry) => {
                    const row = rowMap.get(entry.url);
                    if (row) updateRow(row, entry);
                });
                applyHideDone();
            }
        } catch (e) {
            // ignore polling errors
        } finally {
            pollInFlight = false;
            schedulePoll(pollDelayActive);
        }
    };

    const handleAiInputClick = async (event) => {
        const button = event.target.closest('.js-ai-input');
        if (!button) return;
        const resultKey = button.dataset.resultKey;
        const source = button.dataset.source;
        if (!resultKey || !source || !aiInputModalContent) return;
        try {
            const payload = await fetchResultPayload(source, resultKey);
            const input = payload?.debug?.ai_input || '';
            const inputType = payload?.debug?.ai_input_type || 'n/a';
            const structuredChars = payload?.debug?.structured_description_chars;
            const sourceUrl = payload?.debug?.source_url || '';
            if (aiInputModalMeta) {
                const parts = [];
                parts.push(`Type: ${inputType}`);
                if (structuredChars !== undefined) {
                    parts.push(`Description: ${structuredChars} caractères`);
                }
                if (sourceUrl) {
                    const safeUrl = sourceUrl.replace(/"/g, '&quot;');
                    parts.push(`<a href="${safeUrl}" target="_blank" rel="noopener noreferrer">Source</a>`);
                }
                aiInputModalMeta.innerHTML = parts.join(' • ');
            }
            aiInputModalContent.textContent = input || 'Aucune entrée IA disponible pour ce résultat.';
            if (aiInputModal) aiInputModal.show();
        } catch (e) {
            if (aiInputModalMeta) aiInputModalMeta.textContent = '';
            aiInputModalContent.textContent = 'Impossible de charger l’entrée IA.';
            if (aiInputModal) aiInputModal.show();
        }
    };

    const handleQueueActionClick = async (event) => {
        const button = event.target.closest('#source-run-form button[name="action"]');
        if (!button || !sourceRunForm) return;
        const action = button.value || '';
        if (!['run_source', 'pause_source', 'resume_source'].includes(action)) return;
        event.preventDefault();
        if (formActionInFlight) return;
        formActionInFlight = true;
        button.disabled = true;
        try {
            const formData = new FormData(sourceRunForm);
            formData.set('action', action);
            const submitUrl = sourceRunForm.getAttribute('action') || window.location.href;
            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
            });
            if (!response.ok) throw new Error('Action impossible');
            await refresh();
        } catch (e) {
            // keep UI stable; next poll/manual refresh will resync
        } finally {
            formActionInFlight = false;
            button.disabled = false;
        }
    };

    const handleResultModalClick = async (event) => {
        const button = event.target.closest('.js-result-modal');
        if (!button) return;
        const resultKey = button.dataset.resultKey;
        const source = button.dataset.source;
        const kind = button.dataset.kind || 'raw';
        if (!resultKey || !source || !resultModalContent) return;
        try {
            const payload = await fetchResultPayload(source, resultKey);
            if (kind === 'debug') {
                if (resultModalLabel) resultModalLabel.textContent = 'Debug';
                resultModalContent.textContent = JSON.stringify(payload?.debug || {}, null, 2) || '{}';
            } else {
                if (resultModalLabel) {
                    resultModalLabel.textContent = 'JSON brut (= what the model returned as text in its answer)';
                }
                const raw = payload?.raw || '';
                try {
                    const parsed = JSON.parse(raw);
                    resultModalContent.textContent = JSON.stringify(parsed, null, 2);
                } catch (e) {
                    resultModalContent.textContent = raw || 'Aucun JSON brut disponible.';
                }
            }
            if (resultModal) resultModal.show();
        } catch (e) {
            if (resultModalLabel) {
                resultModalLabel.textContent = kind === 'debug'
                    ? 'Debug'
                    : 'JSON brut (= what the model returned as text in its answer)';
            }
            resultModalContent.textContent = 'Impossible de charger le contenu.';
            if (resultModal) resultModal.show();
        }
    };

    if (toggleHideDone) {
        toggleHideDone.addEventListener('change', applyHideDone);
        applyHideDone();
    }

    if (table && table.dataset.statusUrl) {
        bindWorkerCopy();
        refresh();
        schedulePoll(pollDelayActive);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                if (shouldPoll()) refresh();
            } else {
                stopPolling();
            }
        });
    }

    document.addEventListener('click', handleAiInputClick);
    document.addEventListener('click', handleResultModalClick);
    document.addEventListener('click', handleQueueActionClick);
});
