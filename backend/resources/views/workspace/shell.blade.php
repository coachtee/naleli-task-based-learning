<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Naleli Workspace — KCS</title>
<link rel="manifest" href="{{ route('workspace.manifest') }}">
<link rel="icon" href="{{ route('workspace.icon') }}" type="image/svg+xml">
<meta name="theme-color" content="#0B1F3A">
<meta name="robots" content="noindex">
@verbatim
<style>
:root{
  --navy:#0B1F3A; --navy-2:#13304f; --coral:#E8613C; --coral-dim:#c74e2c;
  --ink:#152238; --muted:#5b6a80; --line:#dde3ec; --paper:#f4f6fa; --card:#fff;
  --ok:#1c7c54; --warn:#a8620a; --warn-bg:#fdf3e3;
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:var(--paper);color:var(--ink);
  font:15px/1.5 "Segoe UI",system-ui,-apple-system,Roboto,Helvetica,Arial,sans-serif}
button{font:inherit;cursor:pointer}
.hide{display:none!important}

/* ---------------------------------------------------------------- sign in */
#signin{min-height:100%;display:grid;place-items:center;padding:24px;
  background:linear-gradient(160deg,var(--navy) 0%,var(--navy-2) 100%)}
.card{width:100%;max-width:380px;background:var(--card);border-radius:14px;
  padding:30px 28px;box-shadow:0 18px 50px rgba(8,20,40,.35)}
.mark{width:44px;height:44px;border-radius:10px;background:var(--navy);color:#fff;
  display:grid;place-items:center;font-weight:700;font-size:20px;letter-spacing:-1px}
.card h1{font-size:20px;margin:16px 0 2px;letter-spacing:-.2px}
.card p.sub{margin:0 0 22px;color:var(--muted);font-size:13.5px}
label{display:block;font-size:12.5px;font-weight:600;color:var(--muted);
  text-transform:uppercase;letter-spacing:.4px;margin:0 0 6px}
input{width:100%;padding:12px 13px;border:1.5px solid var(--line);border-radius:9px;
  font:inherit;background:#fbfcfe;margin-bottom:16px}
input:focus{outline:none;border-color:var(--navy);background:#fff}
#pin{letter-spacing:6px;font-size:19px;text-align:center}
.btn{width:100%;padding:13px;border:0;border-radius:9px;background:var(--coral);
  color:#fff;font-weight:600;font-size:15px}
.btn:hover{background:var(--coral-dim)}
.btn[disabled]{opacity:.55;cursor:default}
.btn.ghost{background:transparent;color:var(--ink);border:1.5px solid var(--line)}
.btn.ghost:hover{background:#f0f3f8}
.err{background:#fdecea;color:#98241a;border-radius:8px;padding:10px 12px;
  font-size:13.5px;margin-bottom:14px}
.hint{margin:18px 0 0;font-size:12.5px;color:var(--muted);text-align:center}

/* ------------------------------------------------------------------ shell */
#work{min-height:100%;display:flex;flex-direction:column}
header{background:var(--navy);color:#fff;display:flex;align-items:center;gap:14px;
  padding:11px 18px}
header .who{flex:1;min-width:0}
header .who b{display:block;font-size:14.5px;font-weight:600}
header .who span{font-size:12px;opacity:.72}
.pill{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.13);
  border-radius:20px;padding:6px 13px;font-size:12.5px;font-weight:600;white-space:nowrap}
.dot{width:8px;height:8px;border-radius:50%;background:#7ee3b0;flex:none}
.pill.busy .dot{background:#ffd479;animation:pulse 1s infinite}
.pill.wait{background:#7a4a12}
.pill.wait .dot{background:#ffc14d}
@keyframes pulse{50%{opacity:.35}}
header button{background:rgba(255,255,255,.12);border:0;color:#fff;padding:8px 14px;
  border-radius:7px;font-size:13.5px;font-weight:600}
header button:hover{background:rgba(255,255,255,.22)}

main{flex:1;display:grid;grid-template-columns:290px 1fr;min-height:0}
nav{border-right:1px solid var(--line);background:#fff;overflow:auto}
nav h2{font-size:11.5px;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);
  margin:0;padding:16px 18px 8px}
.task{display:flex;gap:11px;align-items:flex-start;width:100%;text-align:left;
  padding:11px 18px;background:none;border:0;border-bottom:1px solid #eef1f6}
.task:hover{background:#f7f9fc}
.task[aria-current="true"]{background:#eef3fb;box-shadow:inset 3px 0 0 var(--coral)}
.task .n{font-size:11px;font-weight:700;color:var(--muted);min-width:34px;padding-top:2px}
.task .t{flex:1;font-size:13.5px;line-height:1.35}
.task .c{font-size:11px;color:var(--muted);margin-top:3px}
.task .c.done{color:var(--ok);font-weight:600}

section{overflow:auto;padding:26px 30px 60px;max-width:780px}
section h1{font-size:22px;margin:0 0 6px;letter-spacing:-.3px}
.meta{color:var(--muted);font-size:13px;margin:0 0 20px}
.panel{background:var(--card);border:1px solid var(--line);border-radius:12px;
  padding:18px 20px;margin-bottom:18px}
.panel h3{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);
  margin:0 0 8px}
.panel p{margin:0;font-size:14.5px}
.steps{list-style:none;margin:0;padding:0}
.steps li{border-bottom:1px solid #eef1f6}
.steps li:last-child{border-bottom:0}
.step{display:flex;gap:13px;align-items:flex-start;width:100%;text-align:left;
  background:none;border:0;padding:14px 4px}
.step:hover{background:#f7f9fc}
.box{width:21px;height:21px;border-radius:6px;border:2px solid #c3ccdb;flex:none;
  display:grid;place-items:center;margin-top:1px;color:#fff;font-size:13px;font-weight:700}
.step.on .box{background:var(--ok);border-color:var(--ok)}
.step .lbl{flex:1}
.step .lbl b{display:block;font-size:14.5px;font-weight:600}
.step.on .lbl b{color:var(--muted);text-decoration:line-through}
.step .lbl span{font-size:13px;color:var(--muted)}
.progress{height:6px;border-radius:3px;background:#e6ebf3;overflow:hidden;margin:0 0 22px}
.progress i{display:block;height:100%;background:var(--coral);transition:width .25s}
.empty{color:var(--muted);padding:40px 0;text-align:center}

/* ----------------------------------------------------------------- modals */
.veil{position:fixed;inset:0;background:rgba(11,31,58,.6);display:grid;place-items:center;
  padding:22px;z-index:20}
.sheet{background:#fff;border-radius:14px;max-width:430px;width:100%;padding:26px;
  box-shadow:0 20px 60px rgba(8,20,40,.4)}
.sheet h2{margin:0 0 8px;font-size:19px}
.sheet p{margin:0 0 16px;color:var(--muted);font-size:14px}
.sheet .row{display:flex;gap:10px;flex-direction:column}
.warnbox{background:var(--warn-bg);border-left:3px solid var(--warn);color:#6b3f06;
  padding:11px 13px;border-radius:0 8px 8px 0;font-size:13.5px;margin-bottom:16px}

@media (max-width:820px){
  main{grid-template-columns:1fr}
  nav{max-height:200px;border-right:0;border-bottom:1px solid var(--line)}
  section{padding:20px 18px 50px}
}
</style>
@endverbatim
</head>
<body>

<div id="signin">
  <form class="card" id="signinForm" autocomplete="off">
    <div class="mark">N</div>
    <h1>Naleli Workspace</h1>
    <p class="sub">Katlehong Computer School</p>
    <div class="err hide" id="signinErr"></div>
    <label for="ref">Student number</label>
    <input id="ref" name="ref" placeholder="NAL-2026-00001" autocapitalize="characters" required>
    <label for="pin">PIN</label>
    <input id="pin" name="pin" inputmode="numeric" pattern="[0-9]*"
           maxlength="{{ $config['pinLength'] }}" placeholder="••••••" required>
    <button class="btn" type="submit" id="signinBtn">Sign in</button>
    <p class="hint">Forgotten your PIN? Ask your facilitator to set a new one.</p>
  </form>
</div>

<div id="work" class="hide">
  <header>
    <div class="mark" style="width:34px;height:34px;font-size:16px;background:rgba(255,255,255,.14)">N</div>
    <div class="who"><b id="whoName"></b><span id="whoRef"></span></div>
    <div class="pill" id="syncPill"><i class="dot"></i><span id="syncText">Saved</span></div>
    <button id="logoutBtn">Log out</button>
  </header>
  <main>
    <nav><h2 id="navTitle">Your work</h2><div id="taskList"></div></nav>
    <section id="detail"><p class="empty">Loading your work…</p></section>
  </main>
</div>

<div class="veil hide" id="modal"><div class="sheet" id="sheet"></div></div>

<script>window.NALELI = @json($config);</script>
@verbatim
<script>
(() => {
"use strict";
const CFG = window.NALELI;
const $ = (id) => document.getElementById(id);

/* ============================================================== local store
 * IndexedDB, not localStorage: the queue must survive a browser that runs out
 * of room mid-morning, and it is keyed by learner so a machine shared by three
 * classes a day never hands one student's pending work to the next.
 */
const DB_NAME = "naleli-workspace", DB_VER = 1;
let _db;
function db() {
  if (_db) return Promise.resolve(_db);
  return new Promise((res, rej) => {
    const r = indexedDB.open(DB_NAME, DB_VER);
    r.onupgradeneeded = () => {
      const d = r.result;
      if (!d.objectStoreNames.contains("queue")) {
        d.createObjectStore("queue", { keyPath: "id", autoIncrement: true })
         .createIndex("owner", "owner");
      }
      if (!d.objectStoreNames.contains("cache")) d.createObjectStore("cache", { keyPath: "key" });
    };
    r.onsuccess = () => { _db = r.result; res(_db); };
    r.onerror = () => rej(r.error);
  });
}
const tx = async (store, mode, fn) => {
  const d = await db();
  return new Promise((res, rej) => {
    const t = d.transaction(store, mode), s = t.objectStore(store);
    let out; try { out = fn(s); } catch (e) { rej(e); return; }
    t.oncomplete = () => res(out && out.result !== undefined ? out.result : out);
    t.onerror = () => rej(t.error);
  });
};
const cacheGet = async (key) => {
  try { const r = await tx("cache", "readonly", (s) => s.get(key)); return r ? r.value : null; }
  catch { return null; }
};
const cachePut = (key, value) => tx("cache", "readwrite", (s) => s.put({ key, value })).catch(() => {});

const queueAll = async (owner) => {
  const rows = await tx("queue", "readonly", (s) => s.index("owner").getAll(owner)).catch(() => []);
  return rows || [];
};
const queueAdd = (item) => tx("queue", "readwrite", (s) => s.add(item));
const queueDrop = (ids) => tx("queue", "readwrite", (s) => { ids.forEach((id) => s.delete(id)); });

/* ==================================================================== state */
const S = {
  token: null, learner: null, programme: null, contentCode: null,
  content: null, record: null, pending: [], selected: null,
  syncing: false, idleTimer: null,
};
const owner = () => S.learner ? `${S.learner.learner_ref}:${S.programme}` : "";

/* ===================================================================== http */
async function api(path, opts = {}) {
  const headers = { Accept: "application/json", ...(opts.headers || {}) };
  if (S.token) headers.Authorization = `Bearer ${S.token}`;
  if (opts.json !== undefined) {
    headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(opts.json);
  }
  const res = await fetch(CFG.api + path, { ...opts, headers, cache: "no-store" });
  if (res.status === 204) return null;
  const body = await res.json().catch(() => null);
  if (!res.ok) throw Object.assign(new Error("http"), { status: res.status, body });
  return body;
}

/* ================================================================== sign in */
$("signinForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const btn = $("signinBtn"), err = $("signinErr");
  btn.disabled = true; btn.textContent = "Signing in…"; err.classList.add("hide");
  try {
    const out = await api("/sessions", {
      method: "POST",
      json: {
        learner_ref: $("ref").value.trim().toUpperCase(),
        pin: $("pin").value.trim(),
        device_name: "Workspace (browser)",
      },
    });
    S.token = out.token;
    S.learner = out.learner;
    sessionStorage.setItem("naleli.session", JSON.stringify({ token: out.token, learner: out.learner }));
    await openWorkspace(out.entitlements);
  } catch (ex) {
    err.textContent = ex.body?.errors?.pin?.[0] || ex.body?.message
      || "We could not sign you in. Check your connection and try again.";
    err.classList.remove("hide");
  } finally {
    btn.disabled = false; btn.textContent = "Sign in"; $("pin").value = "";
  }
});

async function openWorkspace(entitlements) {
  // Whichever programme is actually open. `content_code` is what binds it to
  // a content pack; nothing in the catalogue sets it yet, so fall back.
  const open = (entitlements || []).find((e) => e.state === "active")
            || (entitlements || []).find((e) => e.unlocked_at);
  S.programme = open ? open.programme_code : null;
  S.contentCode = (open && open.content_code) || "digital-foundation";

  $("whoName").textContent = `${S.learner.first_name} ${S.learner.last_name}`.trim();
  $("whoRef").textContent = S.learner.learner_ref + (S.programme ? ` · ${S.programme}` : "");
  $("signin").classList.add("hide");
  $("work").classList.remove("hide");

  S.pending = await queueAll(owner());
  S.record = await cacheGet(`record:${owner()}`);
  S.content = await cacheGet(`content:${S.contentCode}`);
  render();

  // Cached first so the screen is usable instantly, then refreshed. A lab PC
  // on a bad line shows yesterday's work rather than a spinner.
  await Promise.allSettled([pullContent(), pullRecord()]);
  render();
  drain();
  armIdle();
}

async function pullContent() {
  try {
    const c = await api(`/content/${S.contentCode}`);
    if (c) { S.content = c; await cachePut(`content:${S.contentCode}`, c); }
  } catch { /* the cached pack, if any, still works */ }
}
async function pullRecord() {
  if (!S.programme) return;
  try {
    const r = await api(`/me/progress?programme=${encodeURIComponent(S.programme)}`);
    S.record = r; await cachePut(`record:${owner()}`, r);
  } catch { /* offline: the cached record stands */ }
}

/* ===================================================================== sync
 * Every tick is queued and pushed straight away. Nothing waits for logout, so
 * the most a machine can be holding on its own is a few seconds of work.
 */
let drainTimer = null;
const scheduleDrain = () => { clearTimeout(drainTimer); drainTimer = setTimeout(drain, 600); };

async function drain() {
  if (S.syncing || !S.token || !S.programme) return;
  const batch = await queueAll(owner());
  if (!batch.length) { S.pending = []; paintSync(); return; }
  if (!navigator.onLine) { S.pending = batch; paintSync(); return; }

  S.syncing = true; paintSync();
  try {
    const r = await api("/me/progress", {
      method: "POST",
      json: {
        programme: S.programme,
        device: deviceLabel(),
        sub_steps: batch.map((b) => ({
          sub_step_id: b.sub_step_id, task_id: b.task_id, complete: b.complete,
          completed_at: b.completed_at, client_updated_at: b.client_updated_at,
        })),
      },
    });
    await queueDrop(batch.map((b) => b.id));
    S.record = r; await cachePut(`record:${owner()}`, r);
    S.pending = await queueAll(owner());
  } catch (ex) {
    // A rejected session is the one failure retrying cannot fix.
    if (ex.status === 401) return forceSignOut("Your session ended. Please sign in again.");
    S.pending = batch;
  } finally {
    S.syncing = false; paintSync(); render();
  }
}
window.addEventListener("online", drain);
window.addEventListener("offline", paintSync);

function deviceLabel() {
  const ua = navigator.userAgent;
  return /Android|iPhone|iPad/i.test(ua) ? "Workspace (phone browser)" : "Workspace (computer)";
}

/* ==================================================================== ticks */
async function toggle(step, task) {
  const done = !isComplete(step.subStepId);
  const now = new Date().toISOString();

  // Show it immediately; the queue is what makes it true.
  const list = (S.record?.sub_steps || []).filter((s) => s.sub_step_id !== step.subStepId);
  list.push({ sub_step_id: step.subStepId, task_id: task.taskId, complete: done, completed_at: done ? now : null });
  S.record = { ...(S.record || {}), sub_steps: list };
  await cachePut(`record:${owner()}`, S.record);

  await queueAdd({
    owner: owner(), sub_step_id: step.subStepId, task_id: task.taskId,
    complete: done, completed_at: done ? now : null, client_updated_at: now,
  });
  S.pending = await queueAll(owner());

  render(); paintSync(); scheduleDrain(); armIdle();
}
const isComplete = (id) => !!(S.record?.sub_steps || []).find((s) => s.sub_step_id === id && s.complete);

/* =================================================================== render */
function tasks() {
  return (S.content?.workstreams || [])
    .flatMap((w) => (w.tasks || []).map((t) => ({ ...t, workstream: w.name })))
    .sort((a, b) => (a.dayNumber || 0) - (b.dayNumber || 0));
}

function render() {
  const all = tasks();
  if (!all.length) {
    $("taskList").innerHTML = "";
    $("detail").innerHTML = `<p class="empty">Your course content has not reached this computer yet.<br>Connect to the internet once and it will be saved here.</p>`;
    return;
  }
  if (!S.selected || !all.find((t) => t.taskId === S.selected)) {
    const next = all.find((t) => (t.subSteps || []).some((s) => !isComplete(s.subStepId)));
    S.selected = (next || all[0]).taskId;
  }

  $("navTitle").textContent = `Your work · ${all.length} tasks`;
  $("taskList").innerHTML = "";
  all.forEach((t) => {
    const steps = t.subSteps || [], done = steps.filter((s) => isComplete(s.subStepId)).length;
    const b = document.createElement("button");
    b.className = "task";
    b.setAttribute("aria-current", String(t.taskId === S.selected));
    b.innerHTML = `<span class="n">DAY ${t.dayNumber || "–"}</span>
      <span class="t">${esc(t.title)}
        <span class="c ${done === steps.length && steps.length ? "done" : ""}">
          ${steps.length ? (done === steps.length ? "Complete" : `${done} of ${steps.length} steps`) : "No steps"}
        </span>
      </span>`;
    b.onclick = () => { S.selected = t.taskId; render(); armIdle(); };
    $("taskList").appendChild(b);
  });

  const t = all.find((x) => x.taskId === S.selected);
  const steps = t.subSteps || [], done = steps.filter((s) => isComplete(s.subStepId)).length;
  const sec = $("detail");
  sec.innerHTML = `
    <h1>${esc(t.title)}</h1>
    <p class="meta">Day ${t.dayNumber} · ${esc(t.workstream || "")} · about ${t.estimatedMinutes || "—"} minutes</p>
    <div class="progress"><i style="width:${steps.length ? (done / steps.length) * 100 : 0}%"></i></div>
    ${t.whatYoureDoing ? `<div class="panel"><h3>What you are doing</h3><p>${esc(t.whatYoureDoing)}</p></div>` : ""}
    ${t.whyItMatters ? `<div class="panel"><h3>Why it matters</h3><p>${esc(t.whyItMatters)}</p></div>` : ""}
    <div class="panel"><h3>Your steps</h3><ul class="steps" id="stepList"></ul></div>`;

  const ul = $("stepList");
  steps.forEach((s) => {
    const on = isComplete(s.subStepId);
    const li = document.createElement("li");
    const b = document.createElement("button");
    b.className = "step" + (on ? " on" : "");
    b.innerHTML = `<span class="box">${on ? "✓" : ""}</span>
      <span class="lbl"><b>${esc(s.title)}</b>
      <span>${esc(s.instructions || "")}</span></span>`;
    b.onclick = () => toggle(s, t);
    li.appendChild(b); ul.appendChild(li);
  });
}
const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) =>
  ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

function paintSync() {
  const pill = $("syncPill"), text = $("syncText");
  pill.className = "pill";
  if (S.syncing) { pill.classList.add("busy"); text.textContent = "Saving…"; return; }
  const n = S.pending.length;
  if (!n) { text.textContent = navigator.onLine ? "Saved" : "Saved · offline"; return; }
  pill.classList.add("wait");
  text.textContent = `${n} ${n === 1 ? "change" : "changes"} waiting${navigator.onLine ? "" : " · offline"}`;
}

/* =================================================================== log out
 * The one moment work can be lost, so it is the one moment that is not
 * allowed to be quiet. Queue empty: go. Queue full and online: send it first.
 * Queue full and offline: say so, and never pretend otherwise.
 */
$("logoutBtn").onclick = async () => {
  S.pending = await queueAll(owner());
  if (!S.pending.length) return signOut();
  if (navigator.onLine) {
    sheet(`<h2>Saving your work</h2>
      <p>Sending ${S.pending.length} change${S.pending.length === 1 ? "" : "s"} to the school before you log out.</p>`);
    await drain();
    S.pending = await queueAll(owner());
    if (!S.pending.length) { closeSheet(); return signOut(); }
  }
  sheet(`<h2>Your work is not saved yet</h2>
    <div class="warnbox"><b>${S.pending.length} change${S.pending.length === 1 ? "" : "s"}</b>
      cannot reach the school because this computer is offline.</div>
    <p>Your work is safe on this computer and will be sent automatically when the
       internet comes back — but only if you stay signed in here.</p>
    <div class="row">
      <button class="btn" id="stay">Keep me signed in</button>
      <button class="btn ghost" id="save">Save a copy and log out</button>
      <button class="btn ghost" id="cancel">Cancel</button>
    </div>`);
  $("stay").onclick = () => { closeSheet(); drain(); };
  $("cancel").onclick = closeSheet;
  $("save").onclick = async () => { await downloadBackup(); signOut(); };
};

async function downloadBackup() {
  const rows = await queueAll(owner());
  const blob = new Blob([JSON.stringify({
    learner_ref: S.learner.learner_ref, programme: S.programme,
    saved_at: new Date().toISOString(), sub_steps: rows,
  }, null, 2)], { type: "application/json" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = `naleli-${S.learner.learner_ref}-${Date.now()}.json`;
  a.click(); URL.revokeObjectURL(a.href);
}

async function signOut() {
  try { await api("/sessions", { method: "DELETE" }); } catch { /* leaving anyway */ }
  forceSignOut();
}
function forceSignOut(message) {
  sessionStorage.removeItem("naleli.session");
  S.token = null; S.learner = null; S.record = null; S.pending = []; S.selected = null;
  clearTimeout(S.idleTimer); closeSheet();
  $("work").classList.add("hide");
  $("signin").classList.remove("hide");
  $("ref").value = ""; $("pin").value = "";
  const err = $("signinErr");
  if (message) { err.textContent = message; err.classList.remove("hide"); } else { err.classList.add("hide"); }
}

/* ===================================================================== idle
 * Students leave without logging out. The next one must not inherit the seat.
 */
function armIdle() {
  clearTimeout(S.idleTimer);
  if (!S.token) return;
  S.idleTimer = setTimeout(async () => { await drain(); signOut(); }, CFG.idleMinutes * 60000);
}
["click", "keydown", "pointerdown"].forEach((e) => document.addEventListener(e, () => { if (S.token) armIdle(); }));

/* =================================================================== modals */
function sheet(html) { $("sheet").innerHTML = html; $("modal").classList.remove("hide"); }
function closeSheet() { $("modal").classList.add("hide"); }

/* ===================================================== boot / offline / PWA */
(async function boot() {
  // Ask to keep our storage. On an installed app Chromium grants this without
  // prompting; without it the browser may drop a learner's queued work when
  // the disk fills, which is the one failure that would end all trust.
  if (navigator.storage?.persist) { try { await navigator.storage.persist(); } catch {} }

  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register(CFG.base + "/sw.js", { scope: CFG.base + "/" }).catch(() => {});
  }

  const saved = sessionStorage.getItem("naleli.session");
  if (saved) {
    try {
      const { token, learner } = JSON.parse(saved);
      S.token = token; S.learner = learner;
      const ents = await api("/me/entitlements").catch(() => null);
      if (ents) { await openWorkspace(ents.data || ents); return; }
    } catch { /* fall through to sign in */ }
    sessionStorage.removeItem("naleli.session");
  }
  paintSync();
})();
})();
</script>
@endverbatim
</body>
</html>
