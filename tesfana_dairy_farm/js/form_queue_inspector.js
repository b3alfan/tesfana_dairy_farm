(function (Drupal, drupalSettings) {
  Drupal.behaviors.tesfanaFormQueueInspector = {
    attach: function (context) {
      const onceKey = 'tesfana-fqi-v2';
      if (context.body && context.body.dataset[onceKey]) return;
      if (context.body) context.body.dataset[onceKey] = '1';

      const container = document.querySelector('#form-queue-inspector');
      if (!container) return;

      const cowFilterSel = document.querySelector('#fqi-cow-filter');
      const textFilter = document.querySelector('#fqi-text-filter');

      // Populate cow filter from drupalSettings.
      (drupalSettings.tesfana?.cowTags || []).forEach((t) => {
        const opt = document.createElement('option');
        opt.value = t;
        opt.textContent = t;
        cowFilterSel && cowFilterSel.appendChild(opt);
      });

      function findQueueKeys() {
        const keys = [];
        for (let i = 0; i < localStorage.length; i++) {
          const k = localStorage.key(i);
          if (!k) continue;
          const kl = k.toLowerCase();
          if (
            kl.includes('tesfana_queue') ||
            kl.includes('form_queue') ||
            kl.includes('tesfana_form_queue')
          ) {
            keys.push(k);
          }
        }
        return keys.sort();
      }

      function parseItem(value) {
        try {
          return JSON.parse(value);
        } catch (_) {
          return value;
        }
      }

      function rowMatchesFilters(row) {
        const cow = cowFilterSel?.value || '';
        const q = (textFilter?.value || '').toLowerCase();

        const blob = (row.value ?? row.raw);
        const s = (typeof blob === 'string') ? blob : JSON.stringify(blob);

        const cowHit = !cow || s.includes(cow);
        const textHit = !q || s.toLowerCase().includes(q);

        return cowHit && textHit;
      }

      function readQueue() {
        const keys = findQueueKeys();
        const rows = [];
        keys.forEach((k) => {
          const raw = localStorage.getItem(k);
          const val = parseItem(raw);
          rows.push({ key: k, value: val, raw });
        });
        return rows;
      }

      function escapeHtml(str) {
        return String(str)
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;');
      }
      function escapeAttr(str) {
        return String(str).replaceAll('"', '&quot;');
      }

      function toArray(x) {
        return Array.isArray(x) ? x : (x ? [x] : []);
      }

      function normalizeEntries(row) {
        // Supports shapes:
        // - { action, method, payload } (single)
        // - { entries: [ {action, method, payload}, ... ] } (multi)
        // - raw array [...]
        const v = row.value;
        if (Array.isArray(v)) return v;
        if (v && typeof v === 'object') {
          if (Array.isArray(v.entries)) return v.entries;
          if (v.action || v.url || v.payload) return [v];
        }
        return [{ action: null, method: 'POST', payload: v }];
      }

      async function getCsrfToken() {
        const res = await fetch('/session/token');
        return res.ok ? res.text() : '';
      }

      function urlEncode(obj) {
        const p = new URLSearchParams();
        Object.keys(obj || {}).forEach((k) => {
          const val = obj[k];
          if (val === undefined || val === null) return;
          if (typeof val === 'object') {
            p.append(k, JSON.stringify(val));
          } else {
            p.append(k, String(val));
          }
        });
        return p.toString();
      }

      async function resubmitOne(entry, token) {
        const method = (entry.method || 'POST').toUpperCase();
        const action = entry.action || entry.url || endpointForType(entry.type);
        if (!action) throw new Error('No action/url and no type mapping for entry');

        const headers = { 'X-CSRF-Token': token };
        let body, contentType;

        // Prefer form-encoded if the item looks like a form submission.
        if (entry.formEncoded || entry.enctype === 'application/x-www-form-urlencoded') {
          body = urlEncode(entry.payload || {});
          contentType = 'application/x-www-form-urlencoded; charset=UTF-8';
        } else {
          body = JSON.stringify(entry.payload || entry.data || {});
          contentType = 'application/json';
        }
        headers['Content-Type'] = contentType;

        const res = await fetch(action, {
          method,
          headers,
          body,
          credentials: 'same-origin',
        });

        if (!res.ok) {
          const text = await res.text();
          throw new Error(`HTTP ${res.status} ${res.statusText}: ${text.slice(0, 200)}`);
        }
        return true;
      }

      function endpointForType(type) {
        const map = drupalSettings.tesfana?.queueTypeEndpoints || {};
        return type ? map[type] : null;
      }

      async function resubmitRow(row) {
        const entries = normalizeEntries(row);
        const token = await getCsrfToken();
        for (const e of entries) {
          await resubmitOne(e, token);
        }
        // If all succeeded, remove the key.
        localStorage.removeItem(row.key);
      }

      function render() {
        const rows = readQueue().filter(rowMatchesFilters);
        if (!rows.length) {
          container.innerHTML = '<p class="fqi-empty">No queued forms found (with current filters).</p>';
          return;
        }

        const html = [
          '<table class="fqi-table">',
          '<thead><tr>',
          '<th><input type="checkbox" id="fqi-checkall"/></th>',
          '<th>Key</th><th>Entries / Preview</th><th>Actions</th>',
          '</tr></thead><tbody>',
        ];

        rows.forEach((r, idx) => {
          const entries = normalizeEntries(r);
          let preview = '';

          if (entries.length) {
            const short = escapeHtml(JSON.stringify(entries.slice(0, 2), null, 2));
            preview = `<div>${entries.length} entr${entries.length > 1 ? 'ies' : 'y'}</div><pre>${short}${entries.length > 2 ? '\n…' : ''}</pre>`;
          } else if (typeof r.raw === 'string') {
            preview = `<pre>${escapeHtml(r.raw.slice(0, 800))}${r.raw.length > 800 ? '\n…' : ''}</pre>`;
          } else {
            preview = `<pre>${escapeHtml(String(r.raw)).slice(0, 800)}</pre>`;
          }

          html.push(
            `<tr>
              <td><input type="checkbox" class="fqi-check" data-key="${escapeAttr(r.key)}"/></td>
              <td class="fqi-key"><code>${escapeHtml(r.key)}</code></td>
              <td class="fqi-preview">${preview}</td>
              <td class="fqi-actions">
                <button data-key="${escapeAttr(r.key)}" class="fqi-resubmit">Resubmit</button>
                <button data-key="${escapeAttr(r.key)}" class="fqi-delete">Delete</button>
              </td>
            </tr>`
          );
        });

        html.push('</tbody></table>');
        container.innerHTML = html.join('');

        // Wire actions
        container.querySelectorAll('.fqi-delete').forEach((btn) => {
          btn.addEventListener('click', async (e) => {
            const key = e.currentTarget.getAttribute('data-key');
            if (!confirm(`Delete queue key "${key}"?`)) return;
            localStorage.removeItem(key);
            render();
          });
        });

        container.querySelectorAll('.fqi-resubmit').forEach((btn) => {
          btn.addEventListener('click', async (e) => {
            const key = e.currentTarget.getAttribute('data-key');
            const row = readQueue().find((r) => r.key === key);
            if (!row) return;
            try {
              btn.disabled = true;
              btn.textContent = 'Resubmitting...';
              await resubmitRow(row);
              render();
              alert('Resubmitted successfully and removed from queue.');
            } catch (err) {
              console.error(err);
              alert('Resubmit failed: ' + err.message);
            } finally {
              btn.disabled = false;
              btn.textContent = 'Resubmit';
            }
          });
        });

        const checkAll = document.getElementById('fqi-checkall');
        checkAll && checkAll.addEventListener('change', (e) => {
          const on = e.currentTarget.checked;
          container.querySelectorAll('.fqi-check').forEach((c) => (c.checked = on));
        });
      }

      function exportCSV() {
        const rows = readQueue().filter(rowMatchesFilters);
        if (!rows.length) {
          alert('No items to export.');
          return;
        }
        // Flatten to CSV with columns: key, type(s), action(s), cowTag(s), payload
        const lines = [['key', 'types', 'actions', 'cowTags', 'payload_snippet']];
        rows.forEach((r) => {
          const entries = normalizeEntries(r);
          const types = entries.map((e) => e.type || '').filter(Boolean).join('|');
          const actions = entries.map((e) => e.action || e.url || '').filter(Boolean).join('|');

          // Try to extract cow tags in common fields.
          const cowTags = [];
          entries.forEach((e) => {
            const p = e.payload || e.data || {};
            const candidates = [p.tag, p.tag_number, p.cow_tag, p.cow?.tag_number, p.cowTag];
            candidates.forEach((c) => { if (c) cowTags.push(c); });
          });

          const snippet = JSON.stringify(entries.slice(0, 1)[0] || {}).replace(/\s+/g, ' ').slice(0, 300);
          lines.push([r.key, types, actions, cowTags.join('|'), snippet]);
        });

        const csv = lines.map((cols) =>
          cols.map((c) => `"${String(c).replaceAll('"', '""')}"`).join(',')
        ).join('\n');

        const blob = new Blob([csv], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'tesfana_form_queue.csv';
        a.click();
      }

      // Top controls
      document.querySelector('.fqi-refresh')?.addEventListener('click', render);
      document.querySelector('.fqi-apply-filters')?.addEventListener('click', render);
      document.querySelector('.fqi-clear-all')?.addEventListener('click', () => {
        if (!confirm('Clear ALL detected queue keys in this browser?')) return;
        findQueueKeys().forEach((k) => localStorage.removeItem(k));
        render();
      });
      document.querySelector('.fqi-download')?.addEventListener('click', () => {
        const data = readQueue().filter(rowMatchesFilters).reduce((acc, r) => {
          acc[r.key] = r.value ?? r.raw;
          return acc;
        }, {});
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'tesfana_form_queue.json';
        a.click();
      });
      document.querySelector('.fqi-download-csv')?.addEventListener('click', exportCSV);

      document.querySelector('.fqi-resubmit-all')?.addEventListener('click', async () => {
        if (!confirm('Resubmit ALL filtered items?')) return;
        const toSubmit = readQueue().filter(rowMatchesFilters);
        const token = await getCsrfToken();
        for (const row of toSubmit) {
          try {
            const entries = normalizeEntries(row);
            for (const e of entries) {
              await resubmitOne(e, token);
            }
            localStorage.removeItem(row.key);
          } catch (err) {
            console.error('Resubmit failed for key:', row.key, err);
            alert('Failed on key ' + row.key + ': ' + err.message);
            break;
          }
        }
        render();
      });

      document.querySelector('.fqi-resubmit-selected')?.addEventListener('click', async () => {
        const checked = Array.from(container.querySelectorAll('.fqi-check:checked'));
        if (!checked.length) return alert('No rows selected.');
        const token = await getCsrfToken();
        for (const cb of checked) {
          const key = cb.getAttribute('data-key');
          const row = readQueue().find((r) => r.key === key);
          if (!row) continue;
          try {
            const entries = normalizeEntries(row);
            for (const e of entries) {
              await resubmitOne(e, token);
            }
            localStorage.removeItem(row.key);
          } catch (err) {
            console.error('Resubmit failed for key:', key, err);
            alert('Failed on key ' + key + ': ' + err.message);
            break;
          }
        }
        render();
      });

      render();
    },
  };
})(Drupal, drupalSettings);
