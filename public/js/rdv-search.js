(function () {
    const config = document.getElementById('rdv-config');
    if (!config) {
        return;
    }

    const endpoint = config.dataset.endpoint || '';
    const tbody = document.getElementById('rdv-tbody');

    const searchInput = document.getElementById('rdv-search');
    const statutSelect = document.getElementById('rdv-statut');
    const typeSelect = document.getElementById('rdv-type');
    const sortSelect = document.getElementById('rdv-sort');
    const directionSelect = document.getElementById('rdv-direction');

    const sortButtons = document.querySelectorAll('.rdv-sort[data-sort]');

    if (!endpoint || !tbody) {
        return;
    }

    const state = {
        q: (searchInput && searchInput.value || '').trim(),
        statut: (statutSelect && statutSelect.value || '').trim(),
        type: (typeSelect && typeSelect.value || '').trim(),
        sort: (sortSelect && sortSelect.value || config.dataset.sort || 'dateRdv').trim(),
        direction: (directionSelect && directionSelect.value || config.dataset.direction || 'desc').trim(),
        medecin_id: (config.dataset.medecinId || '').trim(),
    };

    let searchTimer = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function statusBadge(status) {
        if (status === 'EN_COURS') {
            return '<span class="badge bg-warning text-dark">EN_COURS</span>';
        }
        if (status === 'ACCEPTE') {
            return '<span class="badge bg-success">ACCEPTE</span>';
        }
        if (status === 'REFUSE') {
            return '<span class="badge bg-danger">REFUSE</span>';
        }

        return '<span class="badge bg-secondary">' + escapeHtml(status || '-') + '</span>';
    }

    function buildActionButtons(item) {
        const id = Number(item.id || 0);
        if (!id) {
            return '';
        }

        const showUrl = (config.dataset.showUrlTemplate || '').replace('__id__', String(id));
        const editUrl = (config.dataset.editUrlTemplate || '').replace('__id__', String(id));
        const acceptUrl = (config.dataset.acceptUrlTemplate || '').replace('__id__', String(id));
        const refuseUrl = (config.dataset.refuseUrlTemplate || '').replace('__id__', String(id));
        const deleteUrl = (config.dataset.deleteUrlTemplate || '').replace('__id__', String(id));

        const csrfActionToken = escapeHtml(item.csrfActionToken || '');
        const csrfDeleteToken = escapeHtml(item.csrfDeleteToken || '');

        let html = '';
        html += '<a class="btn btn-sm btn-outline-primary" href="' + escapeHtml(showUrl) + '">Voir</a> ';
        html += '<a class="btn btn-sm btn-outline-secondary" href="' + escapeHtml(editUrl) + '">Edit</a> ';

        if (item.canAcceptOrReject) {
            html += '<form class="d-inline" method="post" action="' + escapeHtml(acceptUrl) + '">';
            html += '<input type="hidden" name="_token" value="' + csrfActionToken + '">';
            html += '<button class="btn btn-sm btn-success" type="submit">Accepter</button>';
            html += '</form> ';

            html += '<form class="d-inline" method="post" action="' + escapeHtml(refuseUrl) + '">';
            html += '<input type="hidden" name="_token" value="' + csrfActionToken + '">';
            html += '<button class="btn btn-sm btn-danger" type="submit">Refuser</button>';
            html += '</form> ';
        }

        html += '<form class="d-inline" method="post" action="' + escapeHtml(deleteUrl) + '" onsubmit="return confirm(\'Confirmer la suppression de ce rendez-vous ?\');">';
        html += '<input type="hidden" name="_token" value="' + csrfDeleteToken + '">';
        html += '<button class="btn btn-sm btn-dark" type="submit">Supprimer</button>';
        html += '</form>';

        return html;
    }

    function renderRows(items) {
        if (!Array.isArray(items) || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6">Aucun rendez-vous</td></tr>';
            return;
        }

        const rows = items.map((item) => {
            return '<tr>'
                + '<td>' + escapeHtml(item.id || '') + '</td>'
                + '<td>' + escapeHtml(item.date || '-') + '</td>'
                + '<td>' + escapeHtml(item.heure || '-') + '</td>'
                + '<td>' + escapeHtml(item.patient || 'Inconnu') + '</td>'
                + '<td>' + statusBadge(item.statut || '') + '</td>'
                + '<td>' + buildActionButtons(item) + '</td>'
                + '</tr>';
        });

        tbody.innerHTML = rows.join('');
    }

    function updateUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set('q', state.q);
        url.searchParams.set('sort', state.sort);
        url.searchParams.set('direction', state.direction);

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

        if (state.medecin_id) {
            url.searchParams.set('medecin_id', state.medecin_id);
        }

        window.history.replaceState({}, '', url.toString());
    }

    async function fetchRows() {
        const params = new URLSearchParams();
        params.set('q', state.q);
        params.set('sort', state.sort);
        params.set('direction', state.direction);

        if (state.statut) {
            params.set('statut', state.statut);
        }
        if (state.type) {
            params.set('type', state.type);
        }
        if (state.medecin_id) {
            params.set('medecin_id', state.medecin_id);
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
        renderRows(data.items || []);
        updateUrl();
    }

    function scheduleFetch() {
        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(fetchRows, 250);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            state.q = searchInput.value.trim();
            scheduleFetch();
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

    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            state.sort = sortSelect.value.trim() || 'dateRdv';
            fetchRows();
        });
    }

    if (directionSelect) {
        directionSelect.addEventListener('change', function () {
            state.direction = directionSelect.value.trim() || 'desc';
            fetchRows();
        });
    }

    sortButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const requestedSort = (button.dataset.sort || '').trim();
            if (!requestedSort) {
                return;
            }

            if (state.sort === requestedSort) {
                state.direction = state.direction === 'asc' ? 'desc' : 'asc';
            } else {
                state.sort = requestedSort;
                state.direction = 'asc';
            }

            if (sortSelect) {
                sortSelect.value = state.sort;
            }
            if (directionSelect) {
                directionSelect.value = state.direction;
            }

            fetchRows();
        });
    });
})();
