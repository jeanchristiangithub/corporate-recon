<div class="error-debug-modal" id="errorDebugModal" role="dialog" aria-modal="true" aria-label="Debug Console">
  <div class="error-debug-modal__panel">
    <div class="error-debug-modal__head">
      <h3>Error Details</h3>
      <button type="button" class="error-debug-modal__close" data-action="close-debug-modal" aria-label="Close">×</button>
    </div>

    <div class="error-debug-modal__body">
      <div class="error-debug-modal__summary" data-role="errorMessage">An error occurred.</div>

      <div class="error-debug-modal__console">
        <div class="error-debug-modal__console-head">Debug Console</div>
        <pre class="error-debug-modal__console-pre" data-role="debugConsole">{
  "payload": null
}</pre>
      </div>
    </div>

    <div class="error-debug-modal__foot">
      <button type="button" class="material-btn" data-action="copy-debug">Copy JSON</button>
      <button type="button" class="material-btn" data-action="compare-again">Compare Again</button>
      <button type="button" class="material-btn material-btn--primary" data-action="close-debug-modal">Close</button>
    </div>
  </div>
</div>

<script>
(function(){
  document.addEventListener('click', (e) => {
    if (e.target.matches('[data-action="close-debug-modal"]')) {
      const m = document.getElementById('errorDebugModal');
      if (m) m.classList.remove('is-open');
    }

    if (e.target.matches('[data-action="copy-debug"]')) {
      const pre = document.querySelector('[data-role="debugConsole"]');
      if (!pre) return;
      navigator.clipboard?.writeText(pre.textContent || '').then(()=>{
        alert('Copied debug JSON to clipboard');
      }).catch(()=>{
        alert('Copy failed');
      });
    }

    if (e.target.matches('[data-action="compare-again"]')) {
      // clear recent submissions on server then retry the comparison for the originating batch
      const clearUrl = '../../controllers/excelcontrol/clear-recent.php';
      fetch(clearUrl, { method: 'GET', credentials: 'same-origin' })
        .then((res)=>res.json())
        .then((data)=>{
          const msgEl = document.querySelector('[data-role="errorMessage"]');
          const pre = document.querySelector('[data-role="debugConsole"]');
          if (msgEl) msgEl.textContent = data.message || 'Cleared recent submissions';
          if (pre) pre.textContent = JSON.stringify(data, null, 2);

          const m = document.getElementById('errorDebugModal');
          try {
            const batchId = m && m.dataset && m.dataset.batchId ? m.dataset.batchId : null;
            if (batchId && window.recon && typeof window.recon.retryComparison === 'function') {
              // small delay to ensure session updated on server
              setTimeout(()=>{
                window.recon.retryComparison(batchId);
              }, 200);
            }
          } catch (xx) {
            console.error('retryComparison failed', xx);
          }

          if (m) m.classList.remove('is-open');
        })
        .catch((err)=>{
          alert('Failed to clear recent submissions');
          console.error('clear-recent error', err);
        });
    }
  });
})();
</script>
