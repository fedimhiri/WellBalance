(function () {
    const config = document.getElementById('rdv-patient-config');
    if (!config) {
        return;
    }

    const endpoint = config.dataset.endpoint || '';
    const tbody = document.getElementById('rdv-patient-tbody');
    const searchInput = document.getElementById('rdv-patient-search');
    const statutSelect = document.getElementById('rdv-patient-statut');
    const typeSelect = document.getElementById('rdv-patient-type');
    const sortInput = document.getElementById('rdv-patient-sort-input');
    const dirInput = document.getElementById('rdv-patient-dir-input');

    if (!endpoint || !tbody) {
        return;
    }

    const state = {
        q: (searchInput && searchInput.value || '').trim(),
        statut: (statutSelect && statutSelect.value || '').trim(),
        type: (typeSelect && typeSelect.value || '').trim(),
        sort: (sortInput && sortInput.value || config.dataset.sort || 'date').trim(),
        dir: (dirInput && dirInput.value || config.dataset.dir || 'asc').trim(),
    };

    let timer = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function badge(status, badgeClass) {
        return '<span class="badge ' + escapeHtml(badgeClass || 'bg-secondary') + '">' + escapeHtml(status || '-') + '</span>';
    }

    function render(items) {
        if (!Array.isArray(items) || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">Aucune demande de rendez-vous.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((item) => {
            return '<tr>'
                + '<td>' + escapeHtml(item.id || '') + '</td>'
                + '<td>' + escapeHtml(item.titre || '') + '</td>'
                + '<td>' + escapeHtml(item.date || '—') + '</td>'
                + '<td>' + escapeHtml(item.heure || '—') + '</td>'
                + '<td>' + escapeHtml(item.medecin || '—') + '</td>'
                + '<td>' + badge(item.statut, item.statutBadgeClass) + '</td>'
                + '</tr>';
        }).join('');
    }

    function updateUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set('q', state.q);
        url.searchParams.set('sort', state.sort);
        url.searchParams.set('dir', state.dir);

        if (state.statut) {
            url.searchParams.set('statut', state.statut);
        } else {
            url.searchParams.delete('statut');
        }

        if (state.type) {
            url.searchParams.set('type', state.type);
        } else {
            url.searchParams.delete('type');
        }

        window.history.replaceState({}, '', url.toString());
    }

    async function fetchRows() {
        const params = new URLSearchParams();
        params.set('q', state.q);
        params.set('sort', state.sort);
        params.set('dir', state.dir);

        if (state.statut) {
            params.set('statut', state.statut);
        }
        if (state.type) {
            params.set('type', state.type);
        }

        const response = await fetch(endpoint + '?' + params.toString(), {
            headers: {
                Accept: 'application/json'
            }
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        render(data.items || []);
        updateUrl();
    }

    function debounceFetch() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(fetchRows, 250);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            state.q = searchInput.value.trim();
            debounceFetch();
        });
    }

    if (statutSelect) {
        statutSelect.addEventListener('change', function () {
            state.statut = statutSelect.value.trim();
            fetchRows();
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            state.type = typeSelect.value.trim();
            fetchRows();
        });
    }
})();
