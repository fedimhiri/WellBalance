(function () {
    const config = document.getElementById('rdv-config');
    const grid = document.getElementById('doctor-calendar-grid');
    const summary = document.getElementById('doctor-calendar-day-summary');
    const slots = document.getElementById('doctor-calendar-slots');
    const fromInput = document.getElementById('calendar-from');
    const toInput = document.getElementById('calendar-to');
    const viewSelect = document.getElementById('calendar-view');
    const refreshButton = document.getElementById('calendar-refresh');

    if (!config || !grid || !summary || !slots || !fromInput || !toInput || !viewSelect || !refreshButton) {
        return;
    }

    const endpoint = config.dataset.calendarEndpoint || '';
    const medecinId = (config.dataset.medecinId || '').trim() || '1';

    if (!endpoint) {
        return;
    }

    function formatDate(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');

        return y + '-' + m + '-' + day;
    }

    function ensureDefaultDates() {
        const today = new Date();
        if (!fromInput.value) {
            const from = new Date(today);
            from.setDate(1);
            fromInput.value = formatDate(from);
        }
        if (!toInput.value) {
            const to = new Date(today);
            to.setMonth(to.getMonth() + 1, 0);
            toInput.value = formatDate(to);
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderDay(day) {
        if (!day) {
            summary.textContent = 'Selectionnez un jour.';
            slots.innerHTML = '';
            return;
        }

        const status = day.dayStatus || day.state || 'UNAVAILABLE';
        const totalSlots = day.totalSlots || (day.summary && day.summary.totalSlots) || 0;
        const availableSlots = day.availableSlots || (day.summary && day.summary.availableSlots) || 0;
        const busySlots = day.busySlots || (day.summary && day.summary.busySlots) || 0;
        const unavailableSlots = day.unavailableSlots || (day.summary && day.summary.unavailableSlots) || 0;

        summary.textContent = (day.date || '-') + ' - ' + status
            + ' (Total: ' + totalSlots
            + ', Disponibles: ' + availableSlots
            + ', Occupes: ' + busySlots
            + ', Indisponibles: ' + unavailableSlots + ')';

        const daySlots = Array.isArray(day.slots) ? day.slots : [];
        if (daySlots.length === 0) {
            slots.innerHTML = '<div class="text-muted">Aucun creneau.</div>';
            return;
        }

        slots.innerHTML = daySlots.map((slot) => {
            const slotStatus = slot.state || 'UNAVAILABLE';
            return '<div class="calendar-slot-item">'
                + '<strong>' + escapeHtml((slot.start || '-') + ' - ' + (slot.end || '-')) + '</strong>'
                + ' <span class="calendar-slot-badge ' + escapeHtml(slotStatus) + '">' + escapeHtml(slotStatus) + '</span>'
                + '</div>';
        }).join('');
    }

    function renderCalendar(days) {
        if (!Array.isArray(days) || days.length === 0) {
            grid.innerHTML = '<div class="text-muted">Aucune disponibilite.</div>';
            renderDay(null);
            return;
        }

        grid.innerHTML = days.map((day, index) => {
            const dayStatus = day.dayStatus || day.state || 'UNAVAILABLE';
            const totalSlots = day.totalSlots || (day.summary && day.summary.totalSlots) || (Array.isArray(day.slots) ? day.slots.length : 0);
            return '<button type="button" class="calendar-cell status-' + escapeHtml(dayStatus) + (index === 0 ? ' is-selected' : '') + '" data-day-index="' + index + '">'
                + '<div class="calendar-day-number">' + escapeHtml(day.date || '-') + '</div>'
                + '<span class="calendar-state-badge ' + escapeHtml(dayStatus) + '">' + escapeHtml(dayStatus) + '</span>'
                + '<div class="small text-muted">' + totalSlots + ' creneaux</div>'
                + '</button>';
        }).join('');

        const buttons = grid.querySelectorAll('.calendar-cell[data-day-index]');
        buttons.forEach((button) => {
            button.addEventListener('click', function () {
                buttons.forEach((b) => b.classList.remove('is-selected'));
                button.classList.add('is-selected');
                const index = Number(button.dataset.dayIndex || -1);
                if (index >= 0 && index < days.length) {
                    renderDay(days[index]);
                }
            });
        });

        renderDay(days[0]);
    }

    async function loadCalendar() {
        ensureDefaultDates();

        const params = new URLSearchParams();
        params.set('doctorId', medecinId);
        params.set('view', viewSelect.value || 'month');
        params.set('from', fromInput.value);
        params.set('to', toInput.value);

        const response = await fetch(endpoint + '?' + params.toString(), {
            headers: { Accept: 'application/json' }
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const days = data && data.data && Array.isArray(data.data.days) ? data.data.days : [];
        renderCalendar(days);
    }

    refreshButton.addEventListener('click', loadCalendar);
    ensureDefaultDates();
    loadCalendar();
})();
