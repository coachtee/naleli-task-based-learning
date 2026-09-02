<x-staff-shell active="leads" title="Leads">
  <x-slot:topActions>
    <button class="iconbtn" id="filterBtn" aria-label="Filter" style="opacity:.5" disabled>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
    </button>
  </x-slot:topActions>

  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:14px 16px 6px" id="tiles"></div>

  <div class="section-hd">
    <h2>Call these people</h2>
    <span class="link" id="queueCount" style="color:var(--muted);font-weight:700"></span>
  </div>
  <div class="card-list" id="feed"><div class="row-card" style="text-align:center;color:var(--muted)">Loading…</div></div>

  <button class="fab" id="importBtn" aria-label="Import leads">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M8 11l4 4 4-4"/><path d="M5 19h14"/></svg>
  </button>

  <x-slot:scripts>
    <script>window.CFG = {
      csrf: document.querySelector('meta[name=csrf-token]').content,
      leadShowUrl: '{{ route('staff.calls.show', ['application' => '__ID__']) }}',
    };</script>
    @verbatim
    <style>
      #tiles .tile{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:10px 4px 8px;display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center}
      #tiles .tile .n{font-size:17px;font-weight:800}
      #tiles .tile .lbl{font-size:10.5px;color:var(--muted);line-height:1.2;font-weight:600}
      #tiles .tile .n.danger{color:var(--danger)}
      #tiles .tile .n.warn{color:var(--warning)}
      .urgency{font-size:10.5px;font-weight:800;padding:2.5px 8px;border-radius:100px;white-space:nowrap;flex-shrink:0}
      .urgency.danger{background:var(--danger-bg);color:var(--danger)}
      .urgency.warn{background:var(--warning-bg);color:var(--warning)}
      .urgency.gray{background:var(--gray-bg);color:var(--muted)}
      .tried-pill{font-size:11px;font-weight:700;color:var(--faint)}
      .lastnote{background:var(--bg);border-radius:10px;padding:8px 10px;font-size:12.5px;color:var(--ink);margin-top:8px;border-left:3px solid var(--line)}
      #veil{position:fixed;inset:0;background:rgba(10,31,61,.42);z-index:20;display:flex;align-items:flex-end}
      #veil.hide{display:none}
      #sheet{background:#fff;border-radius:20px 20px 0 0;padding:20px 18px calc(20px + env(safe-area-inset-bottom));width:100%;max-height:88vh;overflow:auto}
      #sheet h2{font-size:17px;margin:0 0 4px}
      #sheet .sub{font-size:12.5px;color:var(--muted);margin:0 0 16px;font-weight:600}
      #sheet label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin:14px 0 8px}
      .chips2{display:flex;flex-wrap:wrap;gap:8px}
      .chip2{padding:9px 13px;border-radius:11px;border:1.4px solid var(--line);font-size:12.5px;font-weight:700;color:var(--muted);background:#fff}
      .chip2.on{background:var(--navy);border-color:var(--navy);color:#fff}
      #sheet textarea,#sheet input[type=text],#sheet input[type=date]{width:100%;border:1.4px solid var(--line);border-radius:12px;padding:11px 12px;font:inherit;resize:vertical;margin-top:2px;background:var(--bg)}
      #sheet .row{display:flex;gap:10px;margin-top:20px}
      #sheet .row button{flex:1;height:46px;border-radius:12px;font-size:14px;font-weight:800}
      #sheet .cancel{background:var(--gray-bg);color:var(--ink)}
      #sheet .save{background:var(--coral);color:#fff}
      #sheet .save:disabled{opacity:.5}
      .upload{border:2px dashed var(--line);border-radius:14px;padding:26px 16px;text-align:center;color:var(--muted);font-size:13.5px}
      .toast{position:fixed;left:16px;right:16px;bottom:96px;background:var(--ink);color:#fff;padding:12px 16px;border-radius:12px;font-size:13px;font-weight:600;z-index:30;text-align:center}
    </style>
    <div id="veil" class="hide"><div id="sheet"></div></div>
    <script>
    (() => {
      "use strict";
      const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
      const $ = (id) => document.getElementById(id);

      const CHANNELS = [["phone","Phone call"],["whatsapp","WhatsApp"],["email","Email"],["sms","SMS"],["in_person","In person"]];
      const OUTCOMES = [
        ["no_answer","No answer"],["left_message","Left a message"],["spoke","Spoke to them"],
        ["sent_info","Sent the course info"],["will_register","Says they will register"],
        ["not_now","Interested, not this intake"],["not_interested","Not interested"],["wrong_number","Wrong number"],
      ];

      let LEADS = [];

      async function api(path, opts = {}) {
        const headers = { "Accept": "application/json", "X-CSRF-TOKEN": window.CFG.csrf, ...(opts.headers || {}) };
        if (opts.json !== undefined) { headers["Content-Type"] = "application/json"; opts.body = JSON.stringify(opts.json); }
        const res = await fetch(path, { ...opts, headers });
        const body = await res.json().catch(() => null);
        if (!res.ok) throw Object.assign(new Error("http"), { status: res.status, body });
        return body;
      }

      async function load() {
        const { leads } = await api("/calls/api/leads");
        LEADS = leads;
        render();
      }

      function render() {
        const overdue = LEADS.filter((l) => l.overdue).length;
        const urgent = LEADS.filter((l) => l.urgent).length;
        const neverCalled = LEADS.filter((l) => l.touch_count === 0).length;

        $("tiles").innerHTML = `
          <div class="tile"><div class="n ${urgent ? 'danger' : ''}">${urgent}</div><div class="lbl">3+ days<br>overdue</div></div>
          <div class="tile"><div class="n ${overdue ? 'warn' : ''}">${overdue}</div><div class="lbl">Due<br>today</div></div>
          <div class="tile"><div class="n">${neverCalled}</div><div class="lbl">Never<br>called</div></div>
          <div class="tile"><div class="n">${LEADS.length}</div><div class="lbl">In the<br>queue</div></div>`;

        $("queueCount").textContent = `${LEADS.length} waiting`;

        if (!LEADS.length) {
          $("feed").innerHTML = `<div class="row-card" style="text-align:center;padding:40px 16px"><b style="display:block;font-size:15px;margin-bottom:6px">Nobody is waiting</b><span style="color:var(--muted);font-size:13px">Every lead has been called, or is booked for later. Import a Facebook export to add more.</span></div>`;
          return;
        }

        $("feed").innerHTML = "";
        LEADS.forEach((l) => $("feed").appendChild(card(l)));
      }

      function waitLabel(l) {
        if (!l.next_action_at) return "—";
        const d = new Date(l.next_action_at), now = new Date();
        const days = Math.round((now - d) / 86400000);
        if (days > 0) return days === 1 ? "1 day ago" : `${days} days ago`;
        if (days < 0) return -days === 1 ? "in 1 day" : `in ${-days} days`;
        const hrs = Math.round((now - d) / 3600000);
        return hrs <= 0 ? "due now" : (hrs === 1 ? "1 hour ago" : `${hrs} hours ago`);
      }

      function card(l) {
        const el = document.createElement("div");
        el.className = "row-card";
        const pillClass = l.urgent ? "danger" : (l.overdue ? "warn" : "gray");
        const triedPill = l.touch_count === 0
          ? `never called`
          : `${l.touch_count}× tried`;

        el.innerHTML = `
          <div class="row-top">
            <div class="row-avatar" style="background:var(--navy)">${esc((l.name || "?").split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase())}</div>
            <div class="row-body">
              <div class="row-name">${esc(l.name)}</div>
              <div class="row-meta">${esc(l.campaign || l.source)} &middot; <span class="tried-pill">${triedPill}</span></div>
            </div>
            <span class="urgency ${pillClass}">${esc(waitLabel(l))}</span>
          </div>
          ${l.last_note ? `<div class="lastnote">&ldquo;${esc(l.last_note)}&rdquo;</div>` : ""}
          <div class="row-actions">
            <button class="btn btn-coral" data-open style="flex:1">View profile</button>
            <button class="iconround wa" data-wa ${l.can_whatsapp ? "" : "disabled"} aria-label="WhatsApp">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20l1.3-3.9A7.9 7.9 0 1 1 8.5 19L4 20Z"/><path d="M9 10c0 3 2.5 5 5 5"/></svg>
            </button>
            <button class="iconround" style="background:var(--gray-bg);color:var(--muted)" data-log aria-label="Log a call">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4v6h6"/><path d="M4.6 15a8 8 0 1 0 2-8.4L4 10"/></svg>
            </button>
          </div>`;

        el.querySelector("[data-open]").onclick = () => location.href = window.CFG.leadShowUrl.replace("__ID__", l.id);
        el.querySelector("[data-wa]").onclick = () => openWhatsApp(l);
        el.querySelector("[data-log]").onclick = () => openLogSheet(l);
        return el;
      }

      async function openWhatsApp(l) {
        try {
          const { url } = await api(`/calls/api/leads/${l.id}/whatsapp`, { method: "POST" });
          window.open(url, "_blank");
          toast("Logged as sent — WhatsApp opened.");
          await load();
        } catch (e) {
          toast(e.body?.message || "Could not open WhatsApp.");
        }
      }

      function openLogSheet(l) {
        let channel = "phone", outcome = null;

        const paint = () => {
          sheet(`
            <h2>How did it go with ${esc(l.name)}?</h2>
            <p class="sub">${l.last_note ? "Last time: “" + esc(l.last_note) + "”" : "First contact with this person."}</p>
            <label>How did you reach out?</label>
            <div class="chips2" id="chChannel">${CHANNELS.map(([v, label]) => `<button type="button" class="chip2 ${v === channel ? "on" : ""}" data-v="${v}">${label}</button>`).join("")}</div>
            <label>What happened?</label>
            <div class="chips2" id="chOutcome">${OUTCOMES.map(([v, label]) => `<button type="button" class="chip2 ${v === outcome ? "on" : ""}" data-v="${v}">${label}</button>`).join("")}</div>
            <label>What did they say? (optional)</label>
            <textarea id="note" placeholder="The next person to call them will read this."></textarea>
            <div class="row">
              <button type="button" class="cancel" onclick="document.getElementById('veil').classList.add('hide')">Cancel</button>
              <button type="button" class="save" id="saveTouch" ${outcome ? "" : "disabled"}>Save it</button>
            </div>`);

          $("chChannel").querySelectorAll(".chip2").forEach((b) => b.onclick = () => { channel = b.dataset.v; paint(); });
          $("chOutcome").querySelectorAll(".chip2").forEach((b) => b.onclick = () => { outcome = b.dataset.v; paint(); });

          const save = $("saveTouch");
          if (save) save.onclick = async () => {
            save.disabled = true; save.textContent = "Saving…";
            try {
              await api(`/calls/api/leads/${l.id}/log`, {
                method: "POST",
                json: { channel, outcome, note: $("note").value || null },
              });
              $("veil").classList.add("hide");
              toast("Logged — " + OUTCOMES.find(([v]) => v === outcome)[1]);
              await load();
            } catch (e) {
              toast("Could not save that.");
              save.disabled = false; save.textContent = "Save it";
            }
          };
        };
        paint();
        $("veil").classList.remove("hide");
      }

      function sheet(html) { $("sheet").innerHTML = html; $("veil").classList.remove("hide"); }
      $("veil").addEventListener("click", (e) => { if (e.target.id === "veil") $("veil").classList.add("hide"); });

      let toastTimer = null;
      function toast(msg) {
        document.querySelector(".toast")?.remove();
        const el = document.createElement("div");
        el.className = "toast"; el.textContent = msg;
        document.body.appendChild(el);
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.remove(), 2600);
      }

      function openImportSheet() {
        sheet(`
          <h2>Import Facebook leads</h2>
          <p class="sub">Download the CSV from the Leads Center on your phone, then choose it here. Importing the same file twice is safe.</p>
          <div class="upload">
            <div>Choose the CSV file</div>
            <input type="file" id="csvFile" accept=".csv,text/csv,text/plain">
          </div>
          <label>Campaign name (optional)</label>
          <div style="font-size:12.5px;color:var(--muted);margin-bottom:6px">Leave blank to use the ad name from the file.</div>
          <input type="text" id="campaignName">
          <div class="row">
            <button type="button" class="cancel" onclick="document.getElementById('veil').classList.add('hide')">Cancel</button>
            <button type="button" class="save" id="doImport">Import them</button>
          </div>`);

        $("doImport").onclick = async () => {
          const file = $("csvFile").files[0];
          if (!file) { toast("Choose a file first."); return; }
          const btn = $("doImport"); btn.disabled = true; btn.textContent = "Importing…";
          const form = new FormData();
          form.append("file", file);
          if ($("campaignName").value) form.append("campaign", $("campaignName").value);
          try {
            const res = await fetch("/calls/api/import", {
              method: "POST", headers: { "X-CSRF-TOKEN": window.CFG.csrf, "Accept": "application/json" }, body: form,
            });
            const body = await res.json();
            if (!res.ok) throw new Error(body.message || "Import failed");
            $("veil").classList.add("hide");
            toast(`${body.imported} new leads imported.`);
            await load();
          } catch (e) {
            toast(e.message || "That file could not be read.");
            btn.disabled = false; btn.textContent = "Import them";
          }
        };
      }

      $("importBtn").onclick = openImportSheet;

      load();
      setInterval(load, 60000);

      if (new URLSearchParams(location.search).get("import") === "1") {
        openImportSheet();
      }
    })();
    </script>
    @endverbatim
  </x-slot:scripts>
</x-staff-shell>
