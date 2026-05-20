/**
 * EventHub Pro - Fetch API & temps reel.
 *
 * Partie 4 :
 * - chargement AJAX des evenements depuis api/events.php ;
 * - inscription sans rechargement via events/register.php ;
 * - recherche live avec debounce 400 ms ;
 * - dashboard temps reel via api/stats.php avec refresh automatique.
 */

'use strict';

const STATE = {
    currentTab: 'all',
    currentFilters: {},
    selectedEvent: null,
    eventsById: new Map(),
    debounceTimer: null,
    dashboardInterval: null,
    dashboardRetry: null,
    previousFullState: new Map(),
    dashboardInitialized: false,
};

const CATEGORY_COLORS = {
    tech: { bg: '#DBEAFE', text: '#1D4ED8', primary: '#2563EB' },
    design: { bg: '#EDE9FE', text: '#6D28D9', primary: '#7C3AED' },
    business: { bg: '#FEF3C7', text: '#B45309', primary: '#EA580C' },
    science: { bg: '#DCFCE7', text: '#15803D', primary: '#16A34A' },
};

const APP_BASE_URL = (() => {
    if (window.location.protocol === 'file:') {
        return 'http://localhost/phpexam/eventhub_mvp/';
    }

    const projectPath = '/phpexam/eventhub_mvp/';
    const index = window.location.pathname.indexOf(projectPath);
    if (index !== -1) {
        return window.location.origin + projectPath;
    }

    return '';
})();

function appUrl(path) {
    if (/^https?:\/\//i.test(path)) {
        return path;
    }

    return APP_BASE_URL ? APP_BASE_URL + path.replace(/^\/+/, '') : path;
}

function qs(selector) {
    return document.querySelector(selector);
}

function byId(id) {
    return document.getElementById(id);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function debounce(fn, delay) {
    return (...args) => {
        clearTimeout(STATE.debounceTimer);
        STATE.debounceTimer = setTimeout(() => fn(...args), delay);
    };
}

function getInputValue(...ids) {
    for (const id of ids) {
        const el = byId(id);
        if (el) {
            return el.value.trim();
        }
    }
    return '';
}

function getCurrentFilters() {
    return {
        q: getInputValue('search-input', 'event-search'),
        category: getInputValue('filter-cat', 'filter-category'),
        date_from: getInputValue('date-from', 'filter-date-from'),
        date_to: getInputValue('date-to', 'filter-date-to'),
        has_places: getInputValue('filter-places') === '1',
        tab: STATE.currentTab,
    };
}

async function fetchJson(url, options = {}) {
    let response;
    try {
        response = await fetch(appUrl(url), {
            cache: 'no-store',
            ...options,
            headers: { Accept: 'application/json', ...(options.headers || {}) },
        });
    } catch (error) {
        throw new Error("Impossible de joindre l'API PHP. Ouvre le site via http://localhost/phpexam/eventhub_mvp/index.php et verifie qu'Apache est demarre.");
    }

    const text = await response.text();
    let data;
    try {
        data = text ? JSON.parse(text) : {};
    } catch (error) {
        throw new Error('Reponse JSON invalide.');
    }

    if (!response.ok || data.success === false) {
        throw new Error(data.error || data.message || `Erreur HTTP ${response.status}`);
    }

    return data;
}

// ============================================================================
// Partie 4.1 - Chargement AJAX des evenements
// ============================================================================

async function loadEvents(filters = {}) {
    const grid = byId('events-grid');
    if (!grid) {
        return;
    }

    const mergedFilters = { ...getCurrentFilters(), ...filters };
    STATE.currentFilters = mergedFilters;

    const params = new URLSearchParams();
    Object.entries(mergedFilters).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== false && value !== undefined) {
            params.set(key, String(value));
        }
    });

    showSkeletons(6);

    try {
        const data = await fetchJson(`api/events.php?${params.toString()}`);
        const events = Array.isArray(data.events) ? data.events : (data.data || []);
        renderEventCards(events);
        refreshHeroStats();
    } catch (error) {
        showToast(error.message || 'Impossible de charger les evenements.', 'error');
        showGridError('Impossible de charger les evenements. Verifiez la connexion au serveur.');
    }
}

function normalizeEvent(event) {
    const registered = Number(event.registered_count ?? event.registered ?? 0);
    const capacity = Number(event.capacity ?? 0);
    const remaining = Number(event.remaining_places ?? event.available_places ?? Math.max(0, capacity - registered));
    const rawFillRate = Number(event.fill_rate ?? event.fill_percentage ?? (capacity > 0 ? Math.round((registered / capacity) * 100) : 0));
    const fillRate = Math.max(0, Math.min(100, rawFillRate));

    return {
        id: Number(event.id),
        title: event.title || 'Evenement',
        description: event.description || '',
        event_date: event.event_date || '',
        location: event.location || '',
        capacity,
        registered_count: registered,
        remaining_places: remaining,
        fill_rate: fillRate,
        category: event.category || 'general',
        category_label: event.category_label || event.category || 'General',
        color_primary: event.color_primary || (CATEGORY_COLORS[event.category]?.primary) || '#2563EB',
        color_light: event.color_light || (CATEGORY_COLORS[event.category]?.bg) || '#DBEAFE',
        is_full: Boolean(event.is_full || remaining <= 0),
    };
}

function renderEventCards(events) {
    const grid = byId('events-grid');
    if (!grid) {
        return;
    }

    STATE.eventsById.clear();
    const normalizedEvents = events.map(normalizeEvent);
    normalizedEvents.forEach(event => STATE.eventsById.set(event.id, event));

    if (normalizedEvents.length === 0) {
        grid.innerHTML = `
            <div class="col-span-3 text-center py-16">
                <div class="text-5xl mb-4">Recherche</div>
                <p class="font-display font-bold text-slate-600 text-lg">Aucun evenement trouve</p>
                <p class="text-slate-400 text-sm mt-2">Modifiez vos criteres de recherche</p>
            </div>`;
        return;
    }

    grid.innerHTML = normalizedEvents.map(event => {
        const colors = CATEGORY_COLORS[event.category] || {
            bg: event.color_light,
            text: '#334155',
            primary: event.color_primary,
        };
        const isWarn = event.fill_rate >= 80 && !event.is_full;
        const barColor = event.is_full ? '#DC2626' : isWarn ? '#F59E0B' : event.color_primary;
        const remainingLabel = `${event.remaining_places} place${event.remaining_places > 1 ? 's' : ''} restante${event.remaining_places > 1 ? 's' : ''}`;

        return `
            <article class="event-card bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col shadow-sm"
                     data-event-id="${event.id}">
                <div class="h-2" style="background:${escapeHtml(event.color_primary)}"></div>
                <div class="p-5 flex flex-col flex-1">
                    <div class="flex items-start gap-2 mb-3 flex-wrap">
                        <span class="badge" style="background:${escapeHtml(colors.bg)};color:${escapeHtml(colors.text)}">${escapeHtml(event.category_label)}</span>
                        ${event.is_full ? '<span class="badge" style="background:#FEE2E2;color:#DC2626">Complet</span>' : ''}
                        ${isWarn ? '<span class="badge" style="background:#FEF3C7;color:#B45309">Quasi plein</span>' : ''}
                    </div>
                    <h3 class="font-display font-bold text-base text-slate-900 mb-1 leading-snug">${escapeHtml(event.title)}</h3>
                    <p class="text-xs text-slate-500 mb-1">${escapeHtml(formatDate(event.event_date))}</p>
                    <p class="text-xs text-slate-500 mb-3">${escapeHtml(event.location)}</p>
                    <p class="text-xs text-slate-600 leading-relaxed flex-1">${escapeHtml(event.description)}</p>
                    <div class="mt-4">
                        <div class="flex justify-between text-xs font-display font-bold mb-1">
                            <span class="text-slate-500">Capacite</span>
                            <span style="color:${barColor}" id="places-${event.id}">${event.registered_count} / ${event.capacity}</span>
                        </div>
                        <div class="cap-bar">
                            <div class="cap-bar-fill" id="bar-${event.id}" style="width:${event.fill_rate}%; background:${barColor}"></div>
                        </div>
                        <p class="text-xs text-slate-400 mt-1" id="remaining-${event.id}">${event.is_full ? 'Evenement complet' : remainingLabel}</p>
                    </div>
                    <button
                        id="btn-${event.id}"
                        ${event.is_full ? 'disabled' : `onclick="openRegisterModal(${event.id})"`}
                        class="mt-4 w-full py-2.5 rounded-xl font-display font-bold text-xs text-white tracking-wide ${event.is_full ? 'opacity-40 cursor-not-allowed' : 'hover:opacity-90 transition'}"
                        style="background:${event.is_full ? '#94A3B8' : escapeHtml(event.color_primary)}">
                        ${event.is_full ? 'Complet' : "S'inscrire ->"}
                    </button>
                </div>
            </article>`;
    }).join('');
}

function filterTab(tab, el) {
    STATE.currentTab = tab;
    document.querySelectorAll('.tab-btn').forEach(button => button.classList.remove('active'));
    if (el) {
        el.classList.add('active');
    }
    loadEvents();
}

const debouncedLoadEvents = debounce(() => loadEvents(getCurrentFilters()), 400);

function debounceSearch() {
    debouncedLoadEvents();
}

// ============================================================================
// Partie 4.1 - Inscription AJAX sans rechargement
// ============================================================================

function openRegisterModal(eventId) {
    const event = STATE.eventsById.get(Number(eventId));
    if (!event || event.is_full) {
        showToast('Cet evenement est complet.', 'error');
        return;
    }

    STATE.selectedEvent = event;
    setText('m-title', event.title);
    setText('m-info', `${formatDate(event.event_date)} - ${event.location}`);
    setText('m-places', `${event.remaining_places} place${event.remaining_places > 1 ? 's' : ''} restante${event.remaining_places > 1 ? 's' : ''}`);

    const modalBar = byId('m-bar');
    if (modalBar) {
        modalBar.style.width = `${event.fill_rate}%`;
        modalBar.style.background = event.fill_rate >= 80 ? '#F59E0B' : event.color_primary;
    }

    const modal = byId('modal-reg');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function closeRegisterModal() {
    const modal = byId('modal-reg');
    if (modal) {
        modal.classList.add('hidden');
    }
    STATE.selectedEvent = null;
}

function closeReg() {
    closeRegisterModal();
}

async function submitReg() {
    if (!STATE.selectedEvent) {
        showToast('Selectionnez un evenement.', 'error');
        return;
    }

    await registerToEvent(STATE.selectedEvent.id);
}

async function registerToEvent(eventId, nameArg = '', emailArg = '') {
    const name = (nameArg || getInputValue('r-name')).trim();
    const email = (emailArg || getInputValue('r-email')).trim().toLowerCase();

    if (!name || !email) {
        showToast('Remplissez le nom et l email.', 'error');
        return;
    }

    setLoad('btn-reg', 'lbl-reg', 'spn-reg', true, 'Inscription...');

    try {
        const data = await fetchJson('events/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_id: Number(eventId), name, email }),
        });

        updateEventCardAfterRegistration(data);
        closeRegisterModal();
        clearRegistrationForm();
        showToast(data.message || 'Inscription reussie.', 'success');

        if (data.alert_sent) {
            showToast("Alerte 80% envoyee a l'organisateur.", 'info');
        }

        await loadEvents(STATE.currentFilters);
        refreshHeroStats();
    } catch (error) {
        showToast(error.message || "Erreur lors de l'inscription.", 'error');
    } finally {
        setLoad('btn-reg', 'lbl-reg', 'spn-reg', false, "S'inscrire & recevoir le ticket PDF");
    }
}

function updateEventCardAfterRegistration(data) {
    const eventId = Number(data.event_id);
    if (!eventId) {
        return;
    }

    const registered = Number(data.registered_count ?? 0);
    const capacity = STATE.eventsById.get(eventId)?.capacity || registered;
    const fillRate = Number(data.fill_rate ?? data.capacity_pct ?? 0);
    const remaining = Number(data.remaining_places ?? data.available_places ?? Math.max(0, capacity - registered));
    const isFull = Boolean(data.is_full || remaining <= 0);

    setText(`places-${eventId}`, `${registered} / ${capacity}`);
    setText(`pl-${eventId}`, `${registered} / ${capacity}`);
    setText(`remaining-${eventId}`, isFull ? 'Evenement complet' : `${remaining} place${remaining > 1 ? 's' : ''} restante${remaining > 1 ? 's' : ''}`);

    const bar = byId(`bar-${eventId}`);
    if (bar) {
        bar.style.width = `${fillRate}%`;
        bar.style.background = isFull ? '#DC2626' : fillRate >= 80 ? '#F59E0B' : bar.style.background;
    }

    const button = byId(`btn-${eventId}`);
    if (button && isFull) {
        button.disabled = true;
        button.textContent = 'Complet';
        button.style.background = '#94A3B8';
        button.classList.add('opacity-40', 'cursor-not-allowed');
    }
}

function clearRegistrationForm() {
    ['r-name', 'r-email'].forEach(id => {
        const input = byId(id);
        if (input) {
            input.value = '';
        }
    });
}

// ============================================================================
// Partie 4.2 - Dashboard temps reel
// ============================================================================

function startDashboard() {
    if (STATE.dashboardInterval) {
        clearInterval(STATE.dashboardInterval);
    }
    if (STATE.dashboardRetry) {
        clearTimeout(STATE.dashboardRetry);
        STATE.dashboardRetry = null;
    }

    loadDashboardStats();
    STATE.dashboardInterval = setInterval(loadDashboardStats, 30000);
}

function startDash() {
    startDashboard();
}

async function fetchDashboardStats() {
    return loadDashboardStats();
}

async function fetchStats() {
    return loadDashboardStats();
}

async function loadDashboardStats() {
    if (!hasDashboardUi()) {
        return;
    }

    setDashboardLoading(true);

    try {
        const data = await fetchJson(`api/stats.php?ts=${Date.now()}`);
        renderDashboard(data);
        detectNewFullEvents(data.events || []);
        setDashboardStatus('Connecte a l API', false);

        // Bonus AJAX : l'organisateur voit l'heure exacte de fraicheur des donnees
        // et peut forcer un refresh sans recharger la page.
        updateLastRefreshTime(data.generated_at);
    } catch (error) {
        setDashboardStatus('Mode hors-ligne temporaire - nouvelle tentative en cours', true);
        showToast(error.message || 'Erreur dashboard. Nouvelle tentative dans 10s.', 'error');

        if (STATE.dashboardInterval) {
            clearInterval(STATE.dashboardInterval);
            STATE.dashboardInterval = null;
        }
        if (!STATE.dashboardRetry) {
            STATE.dashboardRetry = setTimeout(() => {
                STATE.dashboardRetry = null;
                startDashboard();
            }, 10000);
        }
    } finally {
        setDashboardLoading(false);
    }
}

function hasDashboardUi() {
    return Boolean(
        byId('dashboard-events-body') ||
        byId('top-events-list') ||
        byId('top-list') ||
        byId('d-total')
    );
}

function renderDashboard(data) {
    const summary = data.summary || {};
    const events = data.events || data.per_event || [];
    const topEvents = data.top_events || data.top3 || [];
    const recent = data.recent_registrations || [];

    updateKpi('stat-total-events', summary.total_events ?? 0);
    updateKpi('stat-total-registrations', summary.total_registrations ?? summary.total_registered ?? 0);
    updateKpi('stat-new-registrations-24h', summary.new_registrations_24h ?? summary.new_last_24h ?? 0);
    updateKpi('stat-full-events', summary.full_events ?? 0);

    updateKpi('d-total', summary.total_registrations ?? summary.total_registered ?? 0);
    updateKpi('d-new', summary.new_registrations_24h ?? summary.new_last_24h ?? 0);
    updateKpi('d-alert', summary.alert_count ?? summary.full_events ?? 0);
    setText('d-taux', `${summary.avg_fill_rate ?? summary.avg_fill_pct ?? 0}%`, true);

    updateHeroFromStats(summary);
    renderDashboardEvents(events);
    renderTopEvents(topEvents);
    renderRecentRegistrations(recent);
}

function renderDashboardEvents(events) {
    const body = byId('dashboard-events-body');
    if (!body) {
        return;
    }

    if (!events.length) {
        body.innerHTML = '<tr><td colspan="6">Aucun evenement disponible.</td></tr>';
        return;
    }

    body.innerHTML = events.map(event => {
        const fill = Number(event.fill_rate ?? event.fill_pct ?? 0);
        const full = Boolean(event.is_full);
        return `
            <tr>
                <td>${escapeHtml(event.title)}</td>
                <td>${Number(event.capacity || 0)}</td>
                <td>${Number(event.registered_count ?? event.registered ?? 0)}</td>
                <td>${Number(event.remaining_places ?? 0)}</td>
                <td>
                    <div class="progress"><span style="width:${fill}%;background:${full ? '#dc2626' : fill >= 80 ? '#f59e0b' : '#2563eb'}"></span></div>
                    <small>${fill}%</small>
                </td>
                <td><span class="badge ${full ? 'full' : 'open'}">${full ? 'Complet' : 'Ouvert'}</span></td>
            </tr>`;
    }).join('');
}

function renderTopEvents(events) {
    const standalone = byId('top-events-list');
    const embedded = byId('top-list');

    const html = events.length ? events.map((event, index) => {
        const fill = Number(event.fill_rate ?? event.fill_pct ?? 0);
        const registered = Number(event.registered_count ?? event.registered ?? 0);
        const capacity = Number(event.capacity ?? 0);
        return `
            <div class="list-item">
                <strong>${index + 1}. ${escapeHtml(event.title)}</strong>
                <div class="progress"><span style="width:${fill}%;background:${fill >= 100 ? '#dc2626' : fill >= 80 ? '#f59e0b' : '#2563eb'}"></span></div>
                <span class="muted">${fill}% - ${registered} / ${capacity}</span>
            </div>`;
    }).join('') : '<div class="list-item">Aucun evenement.</div>';

    if (standalone) {
        standalone.innerHTML = html;
    }
    if (embedded) {
        embedded.innerHTML = html;
    }
}

function renderRecentRegistrations(registrations) {
    const list = byId('recent-registrations-list');
    if (!list) {
        return;
    }

    if (!registrations.length) {
        list.innerHTML = '<div class="list-item">Aucune inscription dans les dernieres 24h.</div>';
        return;
    }

    list.innerHTML = registrations.map(registration => `
        <div class="list-item">
            <strong>${escapeHtml(registration.name)}</strong>
            <span class="muted">${escapeHtml(registration.email)} - ${escapeHtml(registration.event_title)}</span><br>
            <small>${escapeHtml(registration.registered_at)}</small>
        </div>`).join('');
}

function detectNewFullEvents(events) {
    events.forEach(event => {
        const eventId = Number(event.id);
        const wasFull = STATE.previousFullState.get(eventId) === true;
        const isFull = Boolean(event.is_full);

        if (STATE.dashboardInitialized && !wasFull && isFull) {
            showToast(`L'evenement ${event.title} est maintenant complet.`, 'success');
        }

        STATE.previousFullState.set(eventId, isFull);
    });

    STATE.dashboardInitialized = true;
}

// ============================================================================
// UI helpers
// ============================================================================

function showSkeletons(count = 3) {
    const grid = byId('events-grid');
    if (!grid) {
        return;
    }

    grid.innerHTML = Array.from({ length: count }, () => `
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <div class="skeleton h-2 w-full mb-4 -mx-5 -mt-5" style="width:calc(100% + 40px); border-radius:0"></div>
            <div class="skeleton h-5 w-3/4 mb-2 mt-2"></div>
            <div class="skeleton h-3 w-1/2 mb-1"></div>
            <div class="skeleton h-3 w-2/3 mb-4"></div>
            <div class="skeleton h-2 w-full mb-4"></div>
            <div class="skeleton h-9 w-full rounded-xl"></div>
        </div>`).join('');
}

function showGridError(message) {
    const grid = byId('events-grid');
    if (!grid) {
        return;
    }
    grid.innerHTML = `
        <div class="col-span-3 text-center py-16">
            <p class="font-display font-bold text-red-600">${escapeHtml(message)}</p>
            <button onclick="loadEvents()" class="mt-4 px-6 py-2 rounded-lg text-sm font-display font-bold text-white" style="background:#2563eb">Reessayer</button>
        </div>`;
}

function showToast(message, type = 'info') {
    let container = byId('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.cssText = 'opacity:0; transform:translateX(120%); transition:all .3s ease;';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

function toast(message, type = 'info') {
    showToast(message, type);
}

function setLoad(buttonId, labelId, spinnerId, loading, text) {
    const button = byId(buttonId);
    const label = byId(labelId);
    const spinner = byId(spinnerId);

    if (button) {
        button.disabled = loading;
    }
    if (spinner) {
        spinner.classList.toggle('hidden', !loading);
    }
    if (label && text) {
        label.textContent = text;
    }
}

function setDashboardLoading(loading) {
    const spinner = byId('dashboard-spinner');
    const button = byId('refresh-dashboard');
    if (spinner) {
        spinner.classList.toggle('hidden', !loading);
    }
    if (button) {
        button.disabled = loading;
    }
}

function setDashboardStatus(message, isError) {
    const status = byId('dashboard-status');
    if (!status) {
        return;
    }
    status.textContent = message;
    status.classList.toggle('error', Boolean(isError));
}

function updateLastRefreshTime(generatedAt) {
    const time = generatedAt ? generatedAt.split(' ').pop() : new Date().toLocaleTimeString('fr-FR');
    setText('last-update', `Derniere mise a jour : ${time}`, true);
    setText('last-update-time', `Derniere mise a jour : ${time}`, true);
}

function updateKpi(id, value) {
    const el = byId(id);
    if (!el) {
        return;
    }

    const previous = el.textContent.trim();
    animateCounter(id, Number(value || 0));
    if (previous !== String(value)) {
        el.classList.add('value-changed');
        setTimeout(() => el.classList.remove('value-changed'), 650);
    }
}

function animateCounter(elementId, target) {
    const el = byId(elementId);
    if (!el) {
        return;
    }

    const start = parseInt(el.textContent, 10) || 0;
    const diff = Number(target) - start;
    const steps = 20;
    let step = 0;

    if (diff === 0) {
        el.textContent = String(target);
        return;
    }

    const timer = setInterval(() => {
        step++;
        el.textContent = String(Math.round(start + diff * (step / steps)));
        if (step >= steps) {
            el.textContent = String(target);
            clearInterval(timer);
        }
    }, 18);
}

function anim(id, target) {
    animateCounter(id, target);
}

function setText(id, value) {
    const el = byId(id);
    if (el) {
        el.textContent = value;
    }
}

function formatDate(dateStr) {
    if (!dateStr) {
        return '-';
    }

    const parsed = new Date(String(dateStr).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
        return dateStr;
    }

    return parsed.toLocaleDateString('fr-FR', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).replace(':', 'h');
}

async function refreshHeroStats() {
    if (!byId('h-total')) {
        return;
    }

    try {
        const data = await fetchJson(`api/stats.php?ts=${Date.now()}`);
        updateHeroFromStats(data.summary || {});
    } catch (error) {
        // Les cartes d'evenements restent utilisables meme si les KPI hero echouent.
    }
}

function updateHeroFromStats(summary) {
    updateKpi('h-total', summary.total_events ?? 0);
    updateKpi('h-inscrits', summary.total_registrations ?? summary.total_registered ?? 0);
    updateKpi('h-complets', summary.full_events ?? 0);
    updateKpi('h-new24', summary.new_registrations_24h ?? summary.new_last_24h ?? 0);
}

function updateHero() {
    refreshHeroStats();
}

function showSection(id, btn) {
    ['events', 'dashboard', 'create'].forEach(section => {
        const el = byId(`sec-${section}`);
        if (el) {
            el.classList.toggle('hidden', section !== id);
        }
    });

    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    if (btn) {
        btn.classList.add('active');
    }

    if (id === 'events') {
        loadEvents();
        refreshHeroStats();
    }
    if (id === 'dashboard') {
        startDashboard();
    }
}

async function submitCreate() {
    const payload = {
        title: getInputValue('f-title'),
        description: getInputValue('f-desc'),
        event_date: getInputValue('f-date'),
        location: getInputValue('f-lieu'),
        capacity: getInputValue('f-cap'),
        category: getInputValue('f-cat'),
        organizer_email: getInputValue('f-email'),
    };

    setLoad('btn-create', 'lbl-create', 'spn-create', true, 'Creation...');
    try {
        const data = await fetchJson('events/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        showToast(data.message || 'Evenement cree avec succes.', 'success');
        ['f-title', 'f-desc', 'f-date', 'f-lieu', 'f-cap', 'f-cat', 'f-email'].forEach(id => {
            const el = byId(id);
            if (el) {
                el.value = '';
            }
        });
        loadEvents();
    } catch (error) {
        showToast(error.message || "Erreur lors de la creation.", 'error');
    } finally {
        setLoad('btn-create', 'lbl-create', 'spn-create', false, "Creer l'evenement");
    }
}

function openLogin() {
    const modal = byId('modal-login');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function fakeLogin() {
    const modal = byId('modal-login');
    if (modal) {
        modal.classList.add('hidden');
    }
    showToast("Connecte en tant qu'organisateur", 'success');
}

function attachUiEvents() {
    const search = byId('search-input');
    if (search) {
        search.addEventListener('input', debounceSearch);
    }

    ['filter-cat', 'filter-category', 'filter-places', 'date-from', 'date-to'].forEach(id => {
        const input = byId(id);
        if (input) {
            input.addEventListener('change', () => loadEvents());
        }
    });

    const refresh = byId('refresh-dashboard');
    if (refresh) {
        refresh.addEventListener('click', () => loadDashboardStats());
    }

    const registrationModal = byId('modal-reg');
    if (registrationModal) {
        registrationModal.addEventListener('click', event => {
            if (event.target === event.currentTarget) {
                closeRegisterModal();
            }
        });
    }

    const loginModal = byId('modal-login');
    if (loginModal) {
        loginModal.addEventListener('click', event => {
            if (event.target === event.currentTarget) {
                event.currentTarget.classList.add('hidden');
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    attachUiEvents();

    if (byId('events-grid')) {
        loadEvents();
        refreshHeroStats();
    }

    if (document.body.dataset.page === 'dashboard' || byId('dashboard-events-body')) {
        startDashboard();
    }
});

window.loadEvents = loadEvents;
window.registerToEvent = registerToEvent;
window.debounce = debounce;
window.debounceSearch = debounceSearch;
window.openRegisterModal = openRegisterModal;
window.openReg = openRegisterModal;
window.closeRegisterModal = closeRegisterModal;
window.closeReg = closeReg;
window.submitReg = submitReg;
window.filterTab = filterTab;
window.startDashboard = startDashboard;
window.startDash = startDash;
window.fetchDashboardStats = fetchDashboardStats;
window.fetchStats = fetchStats;
window.showSection = showSection;
window.showToast = showToast;
window.toast = toast;
window.submitCreate = submitCreate;
window.openLogin = openLogin;
window.fakeLogin = fakeLogin;
window.updateHero = updateHero;
