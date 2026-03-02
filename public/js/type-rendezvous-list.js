(function () {
    const config = document.getElementById('type-rdv-config');
    if (!config) {
        return;
    }

    const endpoint = config.dataset.endpoint || '';
    const searchInput = document.getElementById('type-rdv-search');
    const sortSelect = document.getElementById('type-rdv-sort');
    const dirSelect = document.getElementById('type-rdv-dir');
    const tbody = document.getElementById('type-rdv-tbody');

    if (!endpoint || !tbody) {
        return;
    }

    const state = {
        q: (searchInput && searchInput.value || '').trim(),
        sort: (sortSelect && sortSelect.value || config.dataset.sort || 'nom').trim(),
        dir: (dirSelect && dirSelect.value || config.dataset.dir || 'asc').trim(),
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

    function render(items) {
        if (!Array.isArray(items) || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4">Aucun type enregistre.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((item) => {
            return '<tr>'
                + '<td>' + escapeHtml(item.id || '') + '</td>'
                + '<td>' + escapeHtml(item.nomType || '') + '</td>'
                + '<td>' + escapeHtml(item.description || '-') + '</td>'
                + '<td>'
                + '<a class="btn btn-sm btn-outline-primary" href="' + escapeHtml(item.showUrl || '#') + '">Voir</a> '
                + '<a class="btn btn-sm btn-outline-secondary" href="' + escapeHtml(item.editUrl || '#') + '">Editer</a>'
                + '</td>'
                + '</tr>';
        }).join('');
    }

    function updateUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set('q', state.q);
        url.searchParams.set('sort', state.sort);
        url.searchParams.set('dir', state.dir);
        window.history.replaceState({}, '', url.toString());
    }

    async function fetchRows() {
        const params = new URLSearchParams();
        params.set('q', state.q);
        params.set('sort', state.sort);
        params.set('dir', state.dir);

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

    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            state.sort = sortSelect.value.trim() || 'nom';
            fetchRows();
        });
    }

    if (dirSelect) {
        dirSelect.addEventListener('change', function () {
            state.dir = dirSelect.value.trim() || 'asc';
            fetchRows();
        });
    }
})();
