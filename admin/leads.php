<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_admin_auth();

$user = isset($_SESSION['admin_username']) ? (string) $_SESSION['admin_username'] : 'admin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Leads</title>
  <link rel="stylesheet" href="admin.css" />
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
</head>
<body>
  <div class="admin-shell">
    <?php $active = 'leads'; $theme = 'teal'; $logoH = 220; require __DIR__ . '/_sidebar.php'; ?>

    <div class="overlay" data-sidebar-close></div>

    <main class="content">
    <div class="mobile-topbar">
      <button class="menu-btn" type="button" data-sidebar-toggle aria-label="Open menu">☰</button>
      <div class="pill">Leads</div>
    </div>
    <div class="grid">
      <div class="card">
        <h2>Search / filter</h2>
        <form id="filters" class="row" method="get" action="">
          <div class="field" style="flex:1; min-width: 220px;">
            <label for="q">Search</label>
            <input id="q" name="q" value="" placeholder="name, email, phone, message..." />
          </div>
          <div class="field" style="min-width: 220px;">
            <label for="service">Service</label>
            <input list="services" id="service" name="service" value="" placeholder="All services" />
            <datalist id="services">
            </datalist>
          </div>
          <div class="row" style="align-self: end;">
            <button class="btn primary" type="submit">Apply</button>
            <button id="reset" class="btn" type="button">Reset</button>
          </div>
        </form>
        <div class="spacer"></div>
        <div id="summary" class="muted">Loading…</div>
      </div>

      <div class="card">
        <h2>Leads</h2>
        <form id="csvImport" class="row" method="post" enctype="multipart/form-data" style="align-items: end; justify-content: space-between;">
          <div class="field" style="flex:1; min-width: 280px;">
            <label for="csvFile">Upload leads (CSV)</label>
            <input id="csvFile" name="csv" type="file" accept=".csv,text/csv" />
            <div class="muted" style="font-size: 12px; margin-top: 6px;">
              Columns supported: name, email, phone, service, message (header optional).
            </div>
          </div>
          <div class="row">
            <a class="btn" href="<?= e(admin_url('sample-leads.csv')) ?>" download>Download sample CSV</a>
            <button id="csvBtn" class="btn primary" type="submit">Upload CSV</button>
          </div>
          <div id="csvStatus" class="muted" style="min-width: 220px; text-align: right;"></div>
        </form>
        <div class="spacer"></div>
        <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Invite</th>
              <th>Phone</th>
              <th>Service</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody id="tbody">
            <tr><td colspan="6" class="muted">Loading…</td></tr>
          </tbody>
        </table>
        </div>

        <div class="spacer"></div>
        <div class="row" style="justify-content: space-between;">
          <div id="pageInfo" class="muted">Page 1 / 1</div>
          <div class="row">
            <button id="first" class="btn" type="button">First</button>
            <button id="prev" class="btn" type="button">Prev</button>
            <button id="next" class="btn" type="button">Next</button>
            <button id="last" class="btn" type="button">Last</button>
          </div>
        </div>
      </div>
    </div>
    </main>
  </div>

  <script>
    (function () {
      const sidebar = document.querySelector('.sidebar');
      const overlay = document.querySelector('.overlay');
      const toggle = document.querySelector('[data-sidebar-toggle]');
      function open() {
        sidebar.classList.add('open');
        document.body.classList.add('sidebar-open');
      }
      function close() {
        sidebar.classList.remove('open');
        document.body.classList.remove('sidebar-open');
      }
      if (sidebar && overlay && toggle) {
        toggle.addEventListener('click', open);
        overlay.addEventListener('click', close);
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') close();
        });
      }

      const apiUrl = <?= json_encode(admin_url('api/leads.php')) ?>;
      const importUrl = <?= json_encode(admin_url('api/leads-import.php')) ?>;
      let state = { page: 1, totalPages: 1, q: '', service: '' };

      function esc(s) {
        return String(s ?? '')
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;')
          .replaceAll('"', '&quot;')
          .replaceAll("'", '&#039;');
      }

      function setButtons() {
        $('#prev, #first').prop('disabled', state.page <= 1);
        $('#next, #last').prop('disabled', state.page >= state.totalPages);
      }

      function inviteHref(r) {
        const email = String(r?.email ?? '').trim();
        if (!email) return '';

        const name = String(r?.name ?? '').trim() || 'there';
        const service = String(r?.service ?? '').trim();
        const serviceLine = service ? ` regarding ${service}` : '';

        const subject = `Invitation - Finexa Solution`;
        const body =
`Hi ${name},

Thank you for reaching out${serviceLine}.

We’d love to connect and understand your requirements. Please share a good time for a quick call/meeting.

Regards,
Finexa Solution`;

        return `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      }

      function renderRows(rows) {
        const $tbody = $('#tbody');
        if (!rows || rows.length === 0) {
          $tbody.html('<tr><td colspan="6" class="muted">No results.</td></tr>');
          return;
        }

        const html = rows.map(r => `
          <tr>
            <td>${esc(r.name)}</td>
            <td><a href="mailto:${esc(r.email)}">${esc(r.email)}</a></td>
            <td>
              ${r.email ? `<a class="btn primary sm" href="${inviteHref(r)}" title="Draft invitation email">Invite</a>` : `<span class="muted">—</span>`}
            </td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.service)}</td>
            <td>${esc(r.message)}</td>
          </tr>
        `).join('');
        $tbody.html(html);
      }

      function renderServices(services) {
        const $dl = $('#services');
        if (!$dl.children().length && Array.isArray(services)) {
          $dl.html(services.map(s => `<option value="${esc(s)}"></option>`).join(''));
        }
      }

      function load(page) {
        const q = $('#q').val().trim();
        const service = $('#service').val().trim();

        $('#summary').text('Loading…');
        $('#tbody').html('<tr><td colspan="6" class="muted">Loading…</td></tr>');

        $.getJSON(apiUrl, { page, q, service })
          .done(function (res) {
            if (!res || res.ok !== true) throw new Error('Bad response');
            state.page = res.page || 1;
            state.totalPages = res.totalPages || 1;
            state.q = q;
            state.service = service;

            renderServices(res.services);
            renderRows(res.rows);
            $('#summary').text(`Showing ${res.rows.length} of ${res.totalRows} lead(s).`);
            $('#pageInfo').text(`Page ${state.page} / ${state.totalPages}`);
            setButtons();
          })
          .fail(function () {
            $('#summary').text('Failed to load leads.');
            $('#tbody').html('<tr><td colspan="6" class="muted">Failed to load leads.</td></tr>');
          });
      }

      $('#filters').on('submit', function (e) {
        e.preventDefault();
        load(1);
      });
      $('#reset').on('click', function () {
        $('#q').val('');
        $('#service').val('');
        load(1);
      });
      $('#first').on('click', () => load(1));
      $('#prev').on('click', () => load(Math.max(1, state.page - 1)));
      $('#next').on('click', () => load(Math.min(state.totalPages, state.page + 1)));
      $('#last').on('click', () => load(state.totalPages));

      $('#csvImport').on('submit', function (e) {
        e.preventDefault();
        const file = $('#csvFile')[0]?.files?.[0];
        if (!file) {
          $('#csvStatus').text('Please choose a CSV file.');
          return;
        }

        $('#csvStatus').text('Uploading…');
        $('#csvBtn').prop('disabled', true);

        const fd = new FormData();
        fd.append('csv', file);

        $.ajax({
          url: importUrl,
          method: 'POST',
          data: fd,
          processData: false,
          contentType: false,
          dataType: 'json',
        })
          .done(function (res) {
            if (!res || res.ok !== true) throw new Error(res?.error || 'Import failed');
            const skipped = res.skipped || 0;
            $('#csvStatus').text(`Imported ${res.inserted || 0} row(s).${skipped ? ` Skipped ${skipped}.` : ''}`);
            $('#csvFile').val('');
            load(1);
          })
          .fail(function (xhr) {
            const msg = xhr?.responseJSON?.error || 'CSV import failed.';
            $('#csvStatus').text(msg);
          })
          .always(function () {
            $('#csvBtn').prop('disabled', false);
          });
      });

      load(1);
    })();
  </script>
</body>
</html>

