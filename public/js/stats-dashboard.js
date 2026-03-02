(function () {
  "use strict";

  var configEl = document.getElementById("stats-dashboard-config");
  if (!configEl) {
    return;
  }

  var mainEndpoint = configEl.dataset.mainEndpoint || "";
  var monthlyEndpoint = configEl.dataset.monthlyEndpoint || "";
  var pdfEndpoint = configEl.dataset.pdfEndpoint || "";
  var defaultIncludeZero = configEl.dataset.defaultIncludeZero === "1";

  var formEl = document.getElementById("stats-filter-form");
  var medecinIdEl = document.getElementById("stats-medecin-id");
  var fromEl = document.getElementById("stats-from");
  var toEl = document.getElementById("stats-to");
  var yearEl = document.getElementById("stats-year");
  var errorEl = document.getElementById("stats-error");
  var exportBtn = document.getElementById("stats-export-pdf-btn");

  var kpiTotalEl = document.getElementById("kpi-total");
  var kpiCancelRateEl = document.getElementById("kpi-cancel-rate");
  var kpiConfirmRateEl = document.getElementById("kpi-confirm-rate");
  var kpiAvgPerDayEl = document.getElementById("kpi-average-per-day");
  var metaEl = document.getElementById("stats-meta");
  var busiestEl = document.getElementById("stats-busiest-day");
  var leastEl = document.getElementById("stats-least-day");
  var topTypesListEl = document.getElementById("top-types-list");

  var charts = {
    byDay: null,
    byStatus: null,
    byType: null,
    topHours: null,
    byMonth: null,
  };

  function createParams() {
    return {
      medecinId: (medecinIdEl && medecinIdEl.value || "").trim(),
      from: (fromEl && fromEl.value || "").trim(),
      to: (toEl && toEl.value || "").trim(),
      year: (yearEl && yearEl.value || "").trim(),
    };
  }

  function buildQuery(params, withYear) {
    var qs = new URLSearchParams();
    qs.set("medecinId", params.medecinId);
    qs.set("from", params.from);
    qs.set("to", params.to);
    qs.set("includeZeroDaysForMin", defaultIncludeZero ? "1" : "0");
    if (withYear) {
      qs.set("year", params.year);
    }
    return qs.toString();
  }

  function safeText(value, fallback) {
    if (value === null || value === undefined || value === "") {
      return fallback;
    }
    return String(value);
  }

  function showError(message) {
    if (!errorEl) {
      return;
    }
    errorEl.textContent = message;
    errorEl.classList.remove("d-none");
  }

  function clearError() {
    if (!errorEl) {
      return;
    }
    errorEl.textContent = "";
    errorEl.classList.add("d-none");
  }

  function setNoDataState() {
    if (kpiTotalEl) kpiTotalEl.textContent = "0";
    if (kpiCancelRateEl) kpiCancelRateEl.textContent = "0%";
    if (kpiConfirmRateEl) kpiConfirmRateEl.textContent = "0%";
    if (kpiAvgPerDayEl) kpiAvgPerDayEl.textContent = "0";
    if (metaEl) metaEl.textContent = "Aucune periode chargee.";
    if (busiestEl) busiestEl.textContent = "-";
    if (leastEl) leastEl.textContent = "-";
    if (topTypesListEl) {
      topTypesListEl.innerHTML = "<li><span>Aucune donnee</span><span>-</span></li>";
    }
  }

  function upsertChart(key, canvasId, chartType, labels, values, label) {
    var canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === "undefined") {
      return;
    }

    if (charts[key]) {
      charts[key].destroy();
    }

    charts[key] = new Chart(canvas, {
      type: chartType,
      data: {
        labels: labels || [],
        datasets: [
          {
            label: label || "",
            data: values || [],
            backgroundColor: chartType === "line"
              ? "rgba(99, 102, 241, 0.2)"
              : [
                  "#6366f1",
                  "#22c55e",
                  "#f59e0b",
                  "#ef4444",
                  "#06b6d4",
                  "#8b5cf6",
                  "#14b8a6",
                  "#f97316",
                  "#3b82f6",
                  "#84cc16",
                  "#e11d48",
                  "#a855f7",
                ],
            borderColor: chartType === "line" ? "#6366f1" : "rgba(0,0,0,0.1)",
            borderWidth: chartType === "line" ? 2 : 1,
            fill: chartType === "line",
            tension: chartType === "line" ? 0.3 : 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: chartType !== "bar",
          },
        },
        scales: chartType === "pie" || chartType === "doughnut"
          ? {}
          : {
              y: {
                beginAtZero: true,
                ticks: { precision: 0 },
              },
            },
      },
    });
  }

  function renderTopTypes(topTypes) {
    if (!topTypesListEl) {
      return;
    }

    if (!Array.isArray(topTypes) || topTypes.length === 0) {
      topTypesListEl.innerHTML = "<li><span>Aucune donnee</span><span>-</span></li>";
      return;
    }

    var html = "";
    topTypes.forEach(function (item) {
      html += "<li><span>Type #" + safeText(item.typeRendezVousId, "-") + "</span><span>" + safeText(item.count, "0") + "</span></li>";
    });
    topTypesListEl.innerHTML = html;
  }

  function renderMainStats(payload) {
    var data = payload && payload.data ? payload.data : null;
    if (!data) {
      setNoDataState();
      return;
    }

    var kpi = data.kpi || {};
    var meta = data.meta || {};
    var extremes = data.extremes || {};
    var busiest = extremes.jourPlusCharge || null;
    var least = extremes.jourMoinsCharge || null;

    if (kpiTotalEl) kpiTotalEl.textContent = safeText(kpi.totalRendezVous, "0");
    if (kpiCancelRateEl) kpiCancelRateEl.textContent = safeText(kpi.tauxAnnulation, "0") + "%";
    if (kpiConfirmRateEl) kpiConfirmRateEl.textContent = safeText(kpi.tauxConfirmation, "0") + "%";
    if (kpiAvgPerDayEl) kpiAvgPerDayEl.textContent = safeText(kpi.moyenneParJour, "0");

    if (metaEl) {
      metaEl.textContent = safeText(meta.from, "-") + " - " + safeText(meta.to, "-") +
        " (Total: " + safeText(kpi.totalRendezVous, "0") +
        ", Jours: " + safeText(meta.days, "0") + ")";
    }

    if (busiestEl) {
      busiestEl.textContent = busiest ? (safeText(busiest.date, "-") + " (" + safeText(busiest.count, "0") + ")") : "-";
    }
    if (leastEl) {
      leastEl.textContent = least ? (safeText(least.date, "-") + " (" + safeText(least.count, "0") + ")") : "-";
    }

    var byDay = data.byDay || {};
    var byStatus = data.byStatus || {};
    var byType = data.byType || {};
    var topHours = data.topHours || {};

    upsertChart(
      "byDay",
      "chart-by-day",
      "line",
      byDay.labels || [],
      byDay.datasets && byDay.datasets[0] ? byDay.datasets[0].data : [],
      byDay.datasets && byDay.datasets[0] ? byDay.datasets[0].label : "Rendez-vous par jour"
    );

    upsertChart(
      "byStatus",
      "chart-by-status",
      "doughnut",
      byStatus.labels || [],
      byStatus.datasets && byStatus.datasets[0] ? byStatus.datasets[0].data : [],
      byStatus.datasets && byStatus.datasets[0] ? byStatus.datasets[0].label : "Par statut"
    );

    upsertChart(
      "byType",
      "chart-by-type",
      "bar",
      byType.labels || [],
      byType.datasets && byType.datasets[0] ? byType.datasets[0].data : [],
      byType.datasets && byType.datasets[0] ? byType.datasets[0].label : "Par type"
    );

    upsertChart(
      "topHours",
      "chart-top-hours",
      "bar",
      topHours.labels || [],
      topHours.datasets && topHours.datasets[0] ? topHours.datasets[0].data : [],
      topHours.datasets && topHours.datasets[0] ? topHours.datasets[0].label : "Top heures"
    );

    renderTopTypes(data.topTypes || []);
  }

  function renderMonthlyStats(payload) {
    var data = payload && payload.data ? payload.data : null;
    var byMonth = data && data.byMonth ? data.byMonth : {};

    upsertChart(
      "byMonth",
      "chart-by-month",
      "bar",
      byMonth.labels || [],
      byMonth.datasets && byMonth.datasets[0] ? byMonth.datasets[0].data : [],
      byMonth.datasets && byMonth.datasets[0] ? byMonth.datasets[0].label : "Rendez-vous par mois"
    );
  }

  function setPdfLink(params) {
    if (!exportBtn || !pdfEndpoint) {
      return;
    }
    exportBtn.href = pdfEndpoint + "?" + buildQuery(params, false);
  }

  function validateParams(params) {
    if (!params.medecinId || !/^\d+$/.test(params.medecinId) || Number(params.medecinId) <= 0) {
      return "Le Medecin ID est obligatoire et doit etre > 0.";
    }
    if (!params.from || !params.to) {
      return "Les dates Du et Au sont obligatoires.";
    }
    if (!params.year || !/^\d{4}$/.test(params.year)) {
      return "L'annee est invalide.";
    }
    return null;
  }

  function fetchJson(url) {
    return fetch(url, {
      headers: {
        "Accept": "application/json",
      },
      credentials: "same-origin",
    }).then(function (response) {
      return response.json().then(function (body) {
        if (!response.ok || body.success === false) {
          var msg = body && body.message ? body.message : "Erreur de chargement.";
          throw new Error(msg);
        }
        return body;
      });
    });
  }

  function loadAll() {
    var params = createParams();
    var validationError = validateParams(params);
    setPdfLink(params);

    if (validationError) {
      showError(validationError);
      setNoDataState();
      return;
    }

    clearError();

    var mainUrl = mainEndpoint + "?" + buildQuery(params, false);
    var monthlyUrl = monthlyEndpoint + "?" + buildQuery(params, true);

    Promise.all([fetchJson(mainUrl), fetchJson(monthlyUrl)])
      .then(function (results) {
        renderMainStats(results[0]);
        renderMonthlyStats(results[1]);
      })
      .catch(function (error) {
        showError(error.message || "Erreur lors du chargement des statistiques.");
        setNoDataState();
      });
  }

  if (formEl) {
    formEl.addEventListener("submit", function (event) {
      event.preventDefault();
      loadAll();
    });
  }

  setNoDataState();
  loadAll();
})();

