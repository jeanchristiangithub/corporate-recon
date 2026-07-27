<div class="modal fetch-modal" id="webFetchModal" role="dialog" aria-modal="true">
  <div class="modal__panel">
    <div class="modal__head">
      <h3>Web Data Fetch Test</h3>
      <button type="button" class="modal__close" data-action="close-web-fetch">×</button>
    </div>

    <form id="webFetchForm" method="post" enctype="multipart/form-data">
      <input type="hidden" name="mode" value="Test">
      <input type="hidden" name="debug" value="1">
      <input type="hidden" name="csrf_token" value="">

      <label>Choose Web file (.xlsx/.xls/.xlx/.xlsm/.xlsb/.ods/.csv)</label>
      <div id="webFetchDrop" class="dropzone">Drop .xlsx/.xls/.xlx/.xlsm/.xlsb/.ods/.csv here or click to select a file</div>
      <input type="file" name="web_file" id="webFetchInput" accept=".xlsx,.xls,.xlx,.xlsm,.xlsb,.ods,.csv" style="display:none">
      <div style="margin-top:12px">
        <button type="button" id="webFetchSubmit" class="material-btn material-btn--primary">Fetch</button>
        <span id="webFetchStatus" style="margin-left:12px"></span>
      </div>

      <div id="webFetchResults" style="margin-top:12px; display:none">
        <div style="margin-bottom:8px">
          <strong>Extracted:</strong> <span id="webFetchCount">0</span>
          <label style="margin-left:16px">Search: <input id="webFetchSearch" type="search" placeholder="Reference No. or CCREF" style="width:220px"></label>
        </div>
        <div style="max-height:340px; overflow:auto; border:1px solid #eee; background:#fff;">
          <table id="webFetchTable" style="width:100%; border-collapse:collapse;">
            <thead><tr><th style="padding:6px;border-bottom:1px solid #ddd">No.</th><th style="padding:6px;border-bottom:1px solid #ddd">CCREF NO</th><th style="padding:6px;border-bottom:1px solid #ddd">AMOUNT</th><th style="padding:6px;border-bottom:1px solid #ddd">CTP</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <pre id="webFetchDebug" style="margin-top:12px; max-height:140px; overflow:auto; background:#f8f8f8; padding:8px; display:none;"></pre>
      </div>
    </form>
  </div>
</div>
  <link rel="stylesheet" href="<?= htmlspecialchars((string)($appBaseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/src/modals/fetch-test/webfetch.css">
  <script>
  (function(){
    const form = document.getElementById('webFetchForm');
    const input = document.getElementById('webFetchInput');
    const drop = document.getElementById('webFetchDrop');
    const btn = document.getElementById('webFetchSubmit');
    const status = document.getElementById('webFetchStatus');
  const pre = document.getElementById('webFetchDebug');
  const resultsWrap = document.getElementById('webFetchResults');
  const countEl = document.getElementById('webFetchCount');
  const searchEl = document.getElementById('webFetchSearch');
  const tableBody = document.querySelector('#webFetchTable tbody');
    const modal = document.getElementById('webFetchModal');
    let selectedFile = null;

    if (!btn) return;
    // click handler uses selectedFile (from input change or drop)
    btn.addEventListener('click', async () => {
      const fileToSend = selectedFile || (input.files && input.files[0]) || null;
      if (!fileToSend) { alert('Choose a file'); return; }
      status.textContent = 'Uploading...';
      const fd = new FormData();
      // use current global Mode selector value
      const globalMode = (document.getElementById('globalModeSelect') && document.getElementById('globalModeSelect').value) ? document.getElementById('globalModeSelect').value : 'Test';
      fd.append('mode', globalMode);
      fd.append('debug','1');
      fd.append('web_file', fileToSend);
      const token = form.querySelector('input[name="csrf_token"]').value;
      if (token) fd.append('csrf_token', token);
      try {
        const res = await fetch(window.autoreconBaseUrl + '/src/controllers/excelcontrol/test-controller.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const payload = await res.json();
      status.textContent = res.ok ? 'Done' : 'Error';
      const rows = payload.rows || [];
      const webCount = payload.web_count ?? (payload.debug && payload.debug.web_rows_count) ?? rows.length;
      countEl.textContent = String(webCount);
      resultsWrap.style.display = 'block';
      pre.style.display = 'none';

      function render(rowsToShow) {
        tableBody.innerHTML = '';
        rowsToShow.forEach((row, idx) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `<td style="padding:6px;border-bottom:1px solid #eee">${idx+1}</td>`+
            `<td style="padding:6px;border-bottom:1px solid #eee">${(row.web.ccrefNo|| '')}</td>`+
            `<td style="padding:6px;border-bottom:1px solid #eee">${(row.web.amount|| '')}</td>`+
            `<td style="padding:6px;border-bottom:1px solid #eee">${(row.web.ctp|| '')}</td>`;
          tableBody.appendChild(tr);
        });
      }
      render(rows);

      if (searchEl) {
        searchEl.value = '';
        searchEl.oninput = function() {
          const q = (this.value || '').trim().toLowerCase();
          if (!q) return render(rows);
          const filtered = rows.filter(r => {
            const ref = String(r.partners && r.partners.referenceNo || '').toLowerCase();
            const ccref = String(r.web && r.web.ccrefNo || '').toLowerCase();
            return ref.includes(q) || ccref.includes(q);
          });
          render(filtered);
        };
      }
      } catch (e) {
        status.textContent = 'Failed';
        pre.style.display = 'block';
        pre.textContent = String(e);
      }
    });

    // wire dropzone: click to open file picker, drag/drop to set file
    if (drop) {
      drop.addEventListener('click', () => input.click());
      ['dragenter', 'dragover'].forEach((ev) => {
        drop.addEventListener(ev, (e) => {
          e.preventDefault(); e.stopPropagation(); drop.classList.add('is-dragover');
        });
      });
      ['dragleave', 'drop'].forEach((ev) => {
        drop.addEventListener(ev, (e) => {
          e.preventDefault(); e.stopPropagation(); drop.classList.remove('is-dragover');
        });
      });
      drop.addEventListener('drop', (e) => {
        const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) {
          selectedFile = f;
          drop.textContent = f.name;
        }
      });
    }

    if (input) {
      input.addEventListener('change', (ev) => {
        const f = ev.target.files && ev.target.files[0];
        if (f) {
          selectedFile = f;
          if (drop) drop.textContent = f.name;
        }
      });
    }

    document.addEventListener('click', (ev) => {
        if (ev.target.matches('[data-action="close-web-fetch"]')) {
        if (modal) modal.classList.remove('is-open');
        status.textContent = '';
        pre.style.display = 'none';
        pre.textContent = '';
        input.value = '';
        selectedFile = null;
        if (drop) drop.textContent = 'Drop .xlsx/.xlx/.csv here or click to select a file';
      }
    });
  })();
  </script>
