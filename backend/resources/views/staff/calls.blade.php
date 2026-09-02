<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Calls — KCS</title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0B1F3A">
<meta name="csrf-token" content="{{ csrf_token() }}">
@verbatim
<style>
:root{
  --navy:#0B1F3A; --navy-2:#13304f; --coral:#E8613C; --coral-dim:#c74e2c;
  --ink:#152238; --muted:#5b6a80; --line:#E7DCCF; --paper:#FBF1E6; --card:#fff;
  --ok:#1c7c54; --ok-bg:#e7f5ee; --warn:#a8620a; --warn-bg:#fdf3e3;
  --danger:#b3261e; --danger-bg:#fbeae9; --info:#1d4e89; --info-bg:#eaf1fa;
}
*{box-sizing:border-box; -webkit-tap-highlight-color:transparent}
html,body{height:100%}
body{margin:0;background:var(--paper);color:var(--ink);
  font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  padding-bottom:78px}
button{font:inherit;cursor:pointer;-webkit-tap-highlight-color:transparent}
a{color:inherit}
.hide{display:none!important}
:focus-visible{outline:2.5px solid var(--coral);outline-offset:2px;border-radius:6px}

header{position:sticky;top:0;z-index:10;background:var(--paper);
  display:flex;align-items:center;gap:12px;padding:14px 16px 10px}
header h1{font-size:19px;margin:0;flex:1;letter-spacing:-.2px}
header .avatar{width:34px;height:34px;border-radius:50%;background:var(--navy);color:#fff;
  display:grid;place-items:center;font-size:13px;font-weight:700}
.bell{width:34px;height:34px;border-radius:50%;background:#fff;display:grid;place-items:center;
  border:1px solid var(--line);position:relative}
.bell .dot{position:absolute;top:6px;right:7px;width:8px;height:8px;border-radius:50%;background:var(--coral)}

.tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:2px 16px 14px}
.tile{background:#fff;border:1px solid var(--line);border-radius:14px;padding:10px 4px 8px;
  display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center}
.tile .n{font-size:17px;font-weight:700}
.tile .lbl{font-size:10.5px;color:var(--muted);line-height:1.2}
.tile .n.danger{color:var(--danger)}
.tile .n.warn{color:var(--warn)}

section{padding:0 16px}
.section-head{display:flex;align-items:baseline;justify-content:space-between;margin:18px 0 10px}
.section-head h2{font-size:15.5px;margin:0}
.section-head .sub{font-size:12px;color:var(--muted)}
.section-head .link{font-size:13px;color:var(--info);font-weight:600;background:none;border:0}

.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 14px;
  margin-bottom:10px; box-shadow:0 1px 2px rgba(20,20,10,.03)}
.card .top{display:flex;align-items:flex-start;gap:10px;margin-bottom:4px}
.card .who{flex:1;min-width:0}
.card .who b{display:block;font-size:15.5px;font-weight:700;letter-spacing:-.1px}
.card .who .contact{font-size:12.5px;color:var(--muted);margin-top:1px}
.pill{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;
  padding:4px 9px;border-radius:20px;white-space:nowrap}
.pill.danger{background:var(--danger-bg);color:var(--danger)}
.pill.warn{background:var(--warn-bg);color:var(--warn)}
.pill.ok{background:var(--ok-bg);color:var(--ok)}
.pill.info{background:var(--info-bg);color:var(--info)}
.pill.gray{background:#F1EEE8;color:var(--muted)}
.card .meta{font-size:12px;color:var(--muted);margin:8px 0 12px;display:flex;gap:6px;flex-wrap:wrap}
.card .meta .dot-sep{opacity:.5}
.card .lastnote{background:var(--paper);border-radius:10px;padding:8px 10px;font-size:12.5px;
  color:var(--ink);margin:0 0 12px;border-left:3px solid var(--line)}

.actions{display:grid;grid-template-columns:1fr 1fr auto;gap:8px}
.abtn{display:flex;align-items:center;justify-content:center;gap:6px;padding:11px 10px;
  border-radius:10px;border:0;font-size:13.5px;font-weight:700}
.abtn.wa{background:var(--ok);color:#fff}
.abtn.wa:disabled{background:#e3e7ee;color:#9aa4b5}
.abtn.log{background:var(--navy);color:#fff}
.abtn.more{background:#F1EEE8;color:var(--ink);width:44px;padding:11px 0;flex:none}
.abtn svg{width:16px;height:16px;flex:none}

.empty{text-align:center;color:var(--muted);padding:50px 20px;font-size:14px}
.empty b{display:block;color:var(--ink);font-size:16px;margin-bottom:6px}

.fab{position:fixed;right:18px;bottom:88px;z-index:15;background:var(--coral);color:#fff;
  border:0;border-radius:30px;padding:15px 20px;font-size:14.5px;font-weight:700;
  display:flex;align-items:center;gap:8px;box-shadow:0 8px 22px rgba(232,97,60,.4)}
.fab:active{transform:scale(.97)}

nav.tabs{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid var(--line);
  display:flex;padding:8px 4px calc(8px + env(safe-area-inset-bottom));z-index:20}
nav.tabs button{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;
  background:none;border:0;color:var(--muted);font-size:10.5px;font-weight:600;padding:4px 0}
nav.tabs button.on{color:var(--coral)}
nav.tabs svg{width:21px;height:21px}

.veil{position:fixed;inset:0;background:rgba(11,31,58,.5);z-index:30;display:flex;
  align-items:flex-end}
.sheet{background:#fff;width:100%;border-radius:20px 20px 0 0;padding:20px 18px calc(20px + env(safe-area-inset-bottom));
  max-height:88vh;overflow:auto}
.sheet h2{font-size:17px;margin:0 0 4px}
.sheet .sub{font-size:12.5px;color:var(--muted);margin:0 0 16px}
.sheet label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;
  letter-spacing:.4px;color:var(--muted);margin:14px 0 7px}
.chips{display:flex;flex-wrap:wrap;gap:7px}
.chip{padding:9px 13px;border-radius:20px;border:1.5px solid var(--line);background:#fff;
  font-size:13px;font-weight:600;color:var(--ink)}
.chip.on{background:var(--navy);border-color:var(--navy);color:#fff}
.sheet textarea{width:100%;min-height:70px;padding:11px 12px;border:1.5px solid var(--line);
  border-radius:10px;font:inherit;background:var(--paper);resize:vertical}
.sheet input[type=date]{width:100%;padding:11px 12px;border:1.5px solid var(--line);
  border-radius:10px;font:inherit;background:var(--paper)}
.sheet .row{display:flex;gap:10px;margin-top:20px}
.sheet .row button{flex:1;padding:14px;border-radius:10px;border:0;font-size:14.5px;font-weight:700}
.sheet .save{background:var(--coral);color:#fff}
.sheet .cancel{background:#F1EEE8;color:var(--ink)}
.upload{border:2px dashed var(--line);border-radius:14px;padding:26px 16px;text-align:center;
  color:var(--muted);font-size:13.5px}
.upload input{margin-top:10px}
.toast{position:fixed;left:50%;bottom:96px;transform:translateX(-50%);background:var(--navy);
  color:#fff;padding:11px 18px;border-radius:10px;font-size:13.5px;z-index:40;white-space:nowrap}
</style>
@endverbatim
</head>
<body>

<header>
  <div class="avatar" id="avatar">{{ strtoupper(substr($staffName ?? '?', 0, 2)) }}</div>
  <h1>Calls</h1>
  <button class="bell" aria-label="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span class="dot"></span></button>
</header>

<div class="tiles" id="tiles"></div>

<section>
  <div class="section-head">
    <h2>Call these people</h2>
    <span class="sub" id="queueCount"></span>
  </div>
  <div id="feed"><p class="empty">Loading…</p></div>
</section>

<button class="fab" id="importBtn">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M12 5v14M5 12h14"/></svg>
  Import leads
</button>

<nav class="tabs">
  <button class="on"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>Queue</button>
  <button onclick="location.href=window.CFG.dashboardUrl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>Dashboard</button>
</nav>

<div class="veil hide" id="veil"><div class="sheet" id="sheet"></div></div>

<script>window.CFG = {
  csrf: document.querySelector('meta[name=csrf-token]').content,
  base: '{{ route('staff.calls.shell') }}',
  applicationsUrl: '{{ $applicationsUrl }}',
  dashboardUrl: '{{ $dashboardUrl }}',
};</script>
@verbatim
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
    $("feed").innerHTML = `<div class="empty"><b>Nobody is waiting</b>Every lead has been called, or is booked for later. Import a Facebook export to add more.</div>`;
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
  el.className = "card";
  const pillClass = l.urgent ? "danger" : (l.overdue ? "warn" : "gray");
  const triedPill = l.touch_count === 0
    ? `<span class="pill gray">never called</span>`
    : `<span class="pill info">${l.touch_count}× tried</span>`;

  el.innerHTML = `
    <div class="top">
      <div class="who">
        <b>${esc(l.name)}</b>
        <div class="contact">${esc(l.phone || l.email || "no contact details")}</div>
      </div>
      <span class="pill ${pillClass}">${esc(waitLabel(l))}</span>
    </div>
    <div class="meta">${triedPill}<span class="dot-sep">·</span><span>${esc(l.campaign || l.source)}</span></div>
    ${l.last_note ? `<div class="lastnote">"${esc(l.last_note)}"</div>` : ""}
    <div class="actions">
      <button class="abtn wa" data-wa ${l.can_whatsapp ? "" : "disabled"}>
        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm5.6 14.3c-.3.7-1.4 1.3-2 1.4-.5.1-1.2.1-1.9-.1-.4-.1-1-.3-1.7-.6-3-1.3-4.9-4.3-5-4.5-.2-.2-1.2-1.6-1.2-3.1s.8-2.2 1-2.5c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .6.5.3.6.9 2.1 1 2.2.1.2.1.4 0 .6-.4.8-.8.8-.4 1.4.9 1.4 1.6 1.9 2.9 2.5.2.1.4.1.5-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1.2.1 1.5.7 1.8.9.3.1.4.2.5.3.1.2.1.9-.2 1.6z"/></svg>
        WhatsApp
      </button>
      <button class="abtn log" data-log>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z"/></svg>
        Log a call
      </button>
      <button class="abtn more" data-more aria-label="More">
        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
      </button>
    </div>`;

  el.querySelector("[data-wa]").onclick = () => openWhatsApp(l);
  el.querySelector("[data-log]").onclick = () => openLogSheet(l);
  el.querySelector("[data-more]").onclick = () => openMoreSheet(l);
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

function openMoreSheet(l) {
  sheet(`
    <h2>${esc(l.name)}</h2>
    <p class="sub">${esc(l.phone || "")} ${l.email ? " · " + esc(l.email) : ""}</p>
    <div class="row" style="flex-direction:column">
      ${l.email ? `<button class="abtn log" style="margin-bottom:8px" onclick="location.href='mailto:${esc(l.email)}'">Email them</button>` : ""}
      ${l.phone ? `<button class="abtn log" style="margin-bottom:8px" onclick="location.href='tel:${esc(l.phone)}'">Call ${esc(l.phone)}</button>` : ""}
      ${l.can_register ? `<button class="abtn wa" style="margin-bottom:8px" onclick="location.href=window.CFG.applicationsUrl + '?tableSearch=' + encodeURIComponent(l.name)">Register them</button>` : ""}
      <button class="abtn more" onclick="document.getElementById('veil').classList.add('hide')">Close</button>
    </div>`);
}

function openLogSheet(l) {
  let channel = "phone", outcome = null;

  const paint = () => {
    sheet(`
      <h2>How did it go with ${esc(l.name)}?</h2>
      <p class="sub">${l.last_note ? "Last time: “" + esc(l.last_note) + "”" : "First contact with this person."}</p>
      <label>How did you reach out?</label>
      <div class="chips" id="chChannel">${CHANNELS.map(([v, label]) => `<button class="chip ${v === channel ? "on" : ""}" data-v="${v}">${label}</button>`).join("")}</div>
      <label>What happened?</label>
      <div class="chips" id="chOutcome">${OUTCOMES.map(([v, label]) => `<button class="chip ${v === outcome ? "on" : ""}" data-v="${v}">${label}</button>`).join("")}</div>
      <label>What did they say? (optional)</label>
      <textarea id="note" placeholder="The next person to call them will read this."></textarea>
      <div class="row">
        <button class="cancel" onclick="document.getElementById('veil').classList.add('hide')">Cancel</button>
        <button class="save" id="saveTouch" ${outcome ? "" : "disabled"}>Save it</button>
      </div>`);

    $("chChannel").querySelectorAll(".chip").forEach((b) => b.onclick = () => { channel = b.dataset.v; paint(); });
    $("chOutcome").querySelectorAll(".chip").forEach((b) => b.onclick = () => { outcome = b.dataset.v; paint(); });

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

$("importBtn").onclick = () => {
  sheet(`
    <h2>Import Facebook leads</h2>
    <p class="sub">Download the CSV from the Leads Center on your phone, then choose it here. Importing the same file twice is safe.</p>
    <div class="upload">
      <div>Choose the CSV file</div>
      <input type="file" id="csvFile" accept=".csv,text/csv,text/plain">
    </div>
    <label>Campaign name (optional)</label>
    <div style="font-size:12.5px;color:var(--muted);margin-bottom:6px">Leave blank to use the ad name from the file.</div>
    <input type="text" id="campaignName" style="width:100%;padding:11px 12px;border:1.5px solid var(--line);border-radius:10px;font:inherit;background:var(--paper)">
    <div class="row">
      <button class="cancel" onclick="document.getElementById('veil').classList.add('hide')">Cancel</button>
      <button class="save" id="doImport">Import them</button>
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
};

load();
setInterval(load, 60000);
})();
</script>
@endverbatim
</body>
</html>
