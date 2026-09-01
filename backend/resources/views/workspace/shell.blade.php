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
  --ok:#1c7c54; --ok-bg:#e7f5ee; --warn:#a8620a; --warn-bg:#fdf3e3;
  --info:#1d4e89; --info-bg:#eaf1fa;
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:var(--paper);color:var(--ink);
  font:15px/1.5 "Segoe UI",system-ui,-apple-system,Roboto,Helvetica,Arial,sans-serif}
button,textarea,input,select{font:inherit}
button{cursor:pointer}
.hide{display:none!important}

/* A lab keyboard is often the only way in — a stuck mouse, a learner who
   tabs, a screen reader. Filament and the browser both give focus rings by
   default and hand-styled controls quietly lose them. */
:focus-visible{outline:2.5px solid var(--coral);outline-offset:2px;border-radius:4px}
header :focus-visible{outline-color:#fff}

@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}
}

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
input[type=text],input:not([type]){width:100%;padding:12px 13px;border:1.5px solid var(--line);
  border-radius:9px;background:#fbfcfe;margin-bottom:16px}
input:focus,textarea:focus{outline:none;border-color:var(--navy);background:#fff}
#pin{letter-spacing:6px;font-size:19px;text-align:center}
.btn{width:100%;padding:13px;border:0;border-radius:9px;background:var(--coral);
  color:#fff;font-weight:600;font-size:15px}
.btn:hover{background:var(--coral-dim)}
/* Faded coral still reads as "press me". A blocked hand-in is not a button
   having a bad day — it is not a button. */
.btn[disabled]{background:#e6ebf3;color:#8a97ab;cursor:not-allowed}
.btn[disabled]:hover{background:#e6ebf3}
.btn.ghost{background:transparent;color:var(--ink);border:1.5px solid var(--line)}
.btn.ghost:hover{background:#f0f3f8}
.btn.small{width:auto;padding:9px 16px;font-size:13.5px}
.err{background:#fdecea;color:#98241a;border-radius:8px;padding:10px 12px;
  font-size:13.5px;margin-bottom:14px}
.hint{margin:18px 0 0;font-size:12.5px;color:var(--muted);text-align:center}
.micro{margin:-10px 0 16px;font-size:12.5px;color:var(--muted)}

/* ------------------------------------------------------------------ shell */
#work{min-height:100%;display:flex;flex-direction:column}
header{background:var(--navy);color:#fff;display:flex;align-items:center;gap:14px;padding:11px 18px}
header .who{flex:1;min-width:0}
header .who b{display:block;font-size:14.5px;font-weight:600}
header .who span{font-size:12px;opacity:.72}
.pill{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.13);
  border-radius:20px;padding:6px 13px;font-size:13px;font-weight:600;white-space:nowrap;
  font-variant-numeric:tabular-nums}
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
.task .t{flex:1;min-width:0;font-size:13.5px;line-height:1.35}
/* Some days carry two lessons in one title. Left to wrap, a single row grows
   to five lines and the list stops being scannable. */
.task .t > .ttl{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
  overflow:hidden}
.task .c{display:block;font-size:11px;color:var(--muted);margin-top:3px}
.task .c.done{color:var(--ok);font-weight:600}
.task .c.sent{color:var(--info);font-weight:600}
.task .c.redo{color:var(--warn);font-weight:600}

section{overflow:auto;padding:24px 30px 60px;max-width:820px}
section h1{font-size:22px;margin:0 0 6px;letter-spacing:-.3px}
.meta{color:var(--muted);font-size:13px;margin:0 0 18px}
.progress{height:6px;border-radius:3px;background:#e6ebf3;overflow:hidden;margin:0 0 20px}
.progress i{display:block;height:100%;background:var(--coral);transition:width .25s}
.tabs{display:flex;gap:4px;border-bottom:1.5px solid var(--line);margin:0 0 20px}
.tabs button{background:none;border:0;padding:10px 15px;font-size:14px;font-weight:600;
  color:var(--muted);border-bottom:2.5px solid transparent;margin-bottom:-1.5px}
.tabs button[aria-selected="true"]{color:var(--ink);border-bottom-color:var(--coral)}
.tabs .badge{display:inline-block;background:var(--coral);color:#fff;border-radius:9px;
  font-size:11px;padding:0 6px;margin-left:6px;vertical-align:1px}

.panel{background:var(--card);border:1px solid var(--line);border-radius:12px;
  padding:18px 20px;margin-bottom:16px}
.panel h3{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:0 0 8px}
.panel p{margin:0 0 10px;font-size:14.5px}
.panel p:last-child{margin-bottom:0}
.panel ol,.panel ul{margin:0;padding-left:20px;font-size:14.5px}
.panel li{margin-bottom:6px}
.deliver{background:var(--info-bg);border-left:3px solid var(--info);color:#123a68;
  padding:12px 14px;border-radius:0 8px 8px 0;font-size:14px;margin-bottom:16px}

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

textarea{width:100%;min-height:150px;padding:12px 13px;border:1.5px solid var(--line);
  border-radius:9px;background:#fbfcfe;resize:vertical;line-height:1.55}
.filerow{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:4px}
.filerow input[type=file]{flex:1;min-width:200px;font-size:13.5px}
.ev{display:flex;gap:12px;align-items:center;padding:12px 4px;border-bottom:1px solid #eef1f6}
.ev:last-child{border-bottom:0}
.ev .ic{width:34px;height:34px;border-radius:8px;background:#eef3fb;display:grid;
  place-items:center;font-size:15px;flex:none}
.ev .nm{flex:1;min-width:0}
.ev .nm b{display:block;font-size:14px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ev .nm span{font-size:12.5px;color:var(--muted)}
.ev a{font-size:13px;color:var(--info);font-weight:600;text-decoration:none}
.tag{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;
  padding:3px 8px;border-radius:5px;white-space:nowrap}
.tag.wait{background:var(--warn-bg);color:var(--warn)}

.verdict{border-radius:10px;padding:16px 18px;margin-bottom:16px}
.verdict h3{margin:0 0 4px;font-size:16px;text-transform:none;letter-spacing:0}
.verdict p{margin:0;font-size:14px}
.verdict.ok{background:var(--ok-bg);border-left:4px solid var(--ok);color:#0f4d34}
.verdict.redo{background:var(--warn-bg);border-left:4px solid var(--warn);color:#6b3f06}
.verdict.sent{background:var(--info-bg);border-left:4px solid var(--info);color:#123a68}
.rate{display:flex;gap:8px;margin:6px 0 18px}
.rate button{width:46px;height:44px;border:1.5px solid var(--line);background:#fbfcfe;
  border-radius:9px;font-size:16px;font-weight:600;color:var(--muted)}
.rate button[aria-pressed="true"]{border-color:var(--navy);background:var(--navy);color:#fff}
.blocked{color:var(--muted);font-size:13.5px;margin:10px 0 0}
.empty{color:var(--muted);padding:40px 0;text-align:center}
.notice{max-width:520px;margin:60px auto;text-align:center;color:var(--muted)}
.notice h2{color:var(--ink);font-size:19px;margin:0 0 8px}

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
.toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%);background:var(--navy);
  color:#fff;padding:12px 20px;border-radius:9px;font-size:14px;z-index:30;
  box-shadow:0 10px 30px rgba(8,20,40,.35)}

@media (max-width:820px){
  main{grid-template-columns:1fr}
  nav{max-height:190px;border-right:0;border-bottom:1px solid var(--line)}
  section{padding:18px 16px 50px}
  .tabs{overflow-x:auto}
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
    <input id="ref" name="ref" placeholder="NAL-2026-00001" autocapitalize="characters"
           autocomplete="username" spellcheck="false" required>
    <p class="micro">It is on the email we sent you, and starts with NAL.</p>
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
const esc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) =>
  ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const uuid = () => (crypto.randomUUID ? crypto.randomUUID()
  : "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
      const r = Math.random() * 16 | 0;
      return (c === "x" ? r : (r & 0x3 | 0x8)).toString(16);
    }));
const bytes = (n) => n < 1024 ? `${n} B` : n < 1048576 ? `${(n / 1024).toFixed(0)} KB` : `${(n / 1048576).toFixed(1)} MB`;

/* ============================================================== local store
 * IndexedDB, not localStorage: the queue holds file Blobs as well as ticks,
 * and it is keyed by learner so a machine shared by three classes a day never
 * hands one student's pending work to the next.
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
const queueAll = async (owner) =>
  (await tx("queue", "readonly", (s) => s.index("owner").getAll(owner)).catch(() => [])) || [];
const queueAdd = (item) => tx("queue", "readwrite", (s) => s.add(item));
const queueDrop = (ids) => tx("queue", "readwrite", (s) => { ids.forEach((id) => s.delete(id)); });

/* ==================================================================== state */
const S = {
  token: null, learner: null, programme: null, contentCode: null, contentInstalled: false,
  content: null, record: null, pending: [], selected: null, tab: "learn",
  syncing: false, idleTimer: null, rating: null,
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
    S.token = out.token; S.learner = out.learner;
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
  const list = entitlements?.data || entitlements || [];
  const open = list.find((e) => e.state === "active") || list.find((e) => e.unlocked_at);

  S.programme = open ? open.programme_code : null;
  S.contentCode = open ? open.content_code : null;
  // No guessing. A programme whose content nobody has written yet says so —
  // showing a Payroll learner the Foundation course would be worse than
  // showing them nothing.
  S.contentInstalled = !!(open && open.content_installed);

  $("whoName").textContent = `${S.learner.first_name} ${S.learner.last_name}`.trim();
  $("whoRef").textContent = S.learner.learner_ref + (S.programme ? ` · ${S.programme}` : "");
  $("signin").classList.add("hide");
  $("work").classList.remove("hide");

  S.pending = await queueAll(owner());
  S.record = await cacheGet(`record:${owner()}`);
  S.content = S.contentCode ? await cacheGet(`content:${S.contentCode}`) : null;
  render();

  await Promise.allSettled([pullContent(), pullRecord()]);
  render(); drain(); armIdle();
}

async function pullContent() {
  if (!S.contentCode || !S.contentInstalled) return;
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
 * Everything a learner does is queued and pushed straight away — ticks, typed
 * answers, files, hand-ins. Nothing waits for logout, so the most a machine
 * can be holding on its own is a few seconds of work.
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
    // Progress first: it is small and it is what a facilitator looks at.
    // Files follow one at a time so a big photo never holds up a tick.
    const ticks = batch.filter((b) => b.kind === "substep");
    const handIns = batch.filter((b) => b.kind === "submission");

    if (ticks.length || handIns.length) {
      const r = await api("/me/progress", {
        method: "POST",
        json: {
          programme: S.programme, device: deviceLabel(),
          sub_steps: ticks.map((b) => ({
            sub_step_id: b.sub_step_id, task_id: b.task_id, complete: b.complete,
            completed_at: b.completed_at, client_updated_at: b.client_updated_at,
          })),
          submissions: handIns.map((b) => ({
            task_id: b.task_id, submitted_at: b.submitted_at,
            confidence_rating: b.confidence_rating, client_updated_at: b.client_updated_at,
          })),
        },
      });
      await queueDrop([...ticks, ...handIns].map((b) => b.id));
      S.record = r; await cachePut(`record:${owner()}`, r);
    }

    for (const item of batch.filter((b) => b.kind === "evidence")) {
      const form = new FormData();
      form.append("programme", S.programme);
      form.append("device", deviceLabel());
      form.append("client_evidence_id", item.client_evidence_id);
      form.append("task_id", item.task_id);
      form.append("captured_at", item.captured_at);
      if (item.description) form.append("description", item.description);
      form.append("file", item.blob, item.file_name);

      await api("/me/evidence", { method: "POST", body: form });
      await queueDrop([item.id]);
    }

    if (batch.some((b) => b.kind === "evidence")) await pullRecord();
    S.pending = await queueAll(owner());
  } catch (ex) {
    if (ex.status === 401) return forceSignOut("Your session ended. Please sign in again.");
    // 422 means the server will never accept this item, so retrying for ever
    // would wedge the queue behind it. Drop it and say so.
    if (ex.status === 422) {
      const stuck = (await queueAll(owner())).find((b) => b.kind === "evidence");
      if (stuck) {
        await queueDrop([stuck.id]);
        toast(`"${stuck.file_name}" was refused — it may be too big or the wrong kind of file.`);
      }
    }
    S.pending = await queueAll(owner());
  } finally {
    S.syncing = false; paintSync(); render();
  }
}
window.addEventListener("online", drain);
window.addEventListener("offline", paintSync);

function deviceLabel() {
  return /Android|iPhone|iPad/i.test(navigator.userAgent)
    ? "Workspace (phone browser)" : "Workspace (computer)";
}

/* =================================================== what the learner does */
const isComplete = (id) => !!(S.record?.sub_steps || []).find((s) => s.sub_step_id === id && s.complete);
const submissionFor = (taskId) => (S.record?.submissions || []).find((s) => s.task_id === taskId);
const evidenceFor = (taskId) => [
  ...(S.record?.evidence || []).filter((e) => e.task_id === taskId),
  ...S.pending.filter((p) => p.kind === "evidence" && p.task_id === taskId)
    .map((p) => ({ client_evidence_id: p.client_evidence_id, task_id: p.task_id,
      file_name: p.file_name, mime_type: p.mime_type, byte_size: p.byte_size,
      description: p.description, captured_at: p.captured_at, waiting: true })),
];
const isHandedIn = (taskId) => !!submissionFor(taskId)?.submitted_at;

async function toggle(step, task) {
  if (isHandedIn(task.taskId)) return toast("This task is already handed in.");
  const done = !isComplete(step.subStepId);
  const now = new Date().toISOString();

  const list = (S.record?.sub_steps || []).filter((s) => s.sub_step_id !== step.subStepId);
  list.push({ sub_step_id: step.subStepId, task_id: task.taskId, complete: done, completed_at: done ? now : null });
  S.record = { ...(S.record || {}), sub_steps: list };
  await cachePut(`record:${owner()}`, S.record);

  await queueAdd({
    owner: owner(), kind: "substep", sub_step_id: step.subStepId, task_id: task.taskId,
    complete: done, completed_at: done ? now : null, client_updated_at: now,
  });
  await afterChange();
}

async function attach(taskId, blob, fileName, description) {
  await queueAdd({
    owner: owner(), kind: "evidence", client_evidence_id: uuid(), task_id: taskId,
    blob, file_name: fileName, mime_type: blob.type || "application/octet-stream",
    byte_size: blob.size, description: description || null,
    captured_at: new Date().toISOString(),
  });
  await afterChange();
}

async function handIn(task) {
  const now = new Date().toISOString();
  const list = (S.record?.submissions || []).filter((s) => s.task_id !== task.taskId);
  list.push({ task_id: task.taskId, submitted_at: now, confidence_rating: S.rating,
    result: "not_yet_assessed", assessed_at: null, feedback: null });
  S.record = { ...(S.record || {}), submissions: list };
  await cachePut(`record:${owner()}`, S.record);

  await queueAdd({
    owner: owner(), kind: "submission", task_id: task.taskId,
    submitted_at: now, confidence_rating: S.rating, client_updated_at: now,
  });
  S.rating = null;
  toast("Handed in. Your assessor will look at it.");
  await afterChange();
}

async function afterChange() {
  S.pending = await queueAll(owner());
  render(); paintSync(); scheduleDrain(); armIdle();
}

/* =================================================================== render */
function tasks() {
  return (S.content?.workstreams || [])
    .flatMap((w) => (w.tasks || []).map((t) => ({ ...t, workstream: w.name })))
    .sort((a, b) => (a.dayNumber || 0) - (b.dayNumber || 0));
}

function taskState(t) {
  const steps = t.subSteps || [], done = steps.filter((s) => isComplete(s.subStepId)).length;
  const sub = submissionFor(t.taskId);
  if (sub?.result === "competent") return { cls: "done", label: "Competent", done, of: steps.length };
  if (sub?.result === "requires_improvement") return { cls: "redo", label: "Needs more work", done, of: steps.length };
  if (sub?.submitted_at) return { cls: "sent", label: "Handed in", done, of: steps.length };
  if (steps.length && done === steps.length) return { cls: "done", label: "Steps complete", done, of: steps.length };
  return { cls: "", label: `${done} of ${steps.length} steps`, done, of: steps.length };
}

function render() {
  if (!S.programme) return notice("No programme is open on your account",
    "Speak to the office — your registration may not be finished yet.");
  if (!S.contentInstalled) return notice("Your course is not loaded yet",
    `The content for ${esc(S.programme)} has not been published to this system. Your facilitator knows about it — nothing you have done is lost.`);

  const all = tasks();
  if (!all.length) return notice("Your course has not reached this computer yet",
    "Connect to the internet once and it will be saved here for offline use.");

  if (!S.selected || !all.find((t) => t.taskId === S.selected)) {
    const next = all.find((t) => !isHandedIn(t.taskId)
      && (t.subSteps || []).some((s) => !isComplete(s.subStepId)));
    S.selected = (next || all[0]).taskId;
  }

  $("navTitle").textContent = `Your work · ${all.length} tasks`;
  $("taskList").innerHTML = "";
  all.forEach((t) => {
    const st = taskState(t);
    const b = document.createElement("button");
    b.className = "task";
    b.setAttribute("aria-current", String(t.taskId === S.selected));
    b.title = t.title;
    b.innerHTML = `<span class="n">DAY ${t.dayNumber || "–"}</span>
      <span class="t"><span class="ttl">${esc(t.title)}</span>
      <span class="c ${st.cls}">${esc(st.label)}</span></span>`;
    b.onclick = () => {
      S.selected = t.taskId; S.tab = "learn"; render(); armIdle();
      // Otherwise a learner opening a short task from halfway down a long one
      // lands past its heading and thinks the page is blank.
      $("detail").scrollTop = 0;
    };
    $("taskList").appendChild(b);
  });

  renderTask(all.find((x) => x.taskId === S.selected));
}

function notice(heading, body) {
  $("taskList").innerHTML = "";
  $("navTitle").textContent = "Your work";
  $("detail").innerHTML = `<div class="notice"><h2>${heading}</h2><p>${body}</p></div>`;
}

function renderTask(t) {
  const steps = t.subSteps || [], st = taskState(t);
  const ev = evidenceFor(t.taskId);
  const sec = $("detail");

  sec.innerHTML = `
    <h1>${esc(t.title)}</h1>
    <p class="meta">Day ${t.dayNumber} · ${esc(t.workstream || "")} · about ${t.estimatedMinutes || "—"} minutes</p>
    <div class="progress"><i style="width:${st.of ? (st.done / st.of) * 100 : 0}%"></i></div>
    <div class="tabs" role="tablist">
      <button role="tab" data-tab="learn">Learn</button>
      <button role="tab" data-tab="steps">Your steps</button>
      <button role="tab" data-tab="evidence">Evidence${ev.length ? `<span class="badge">${ev.length}</span>` : ""}</button>
      <button role="tab" data-tab="handin">Hand in</button>
    </div>
    <div id="tabBody"></div>`;

  sec.querySelectorAll("[data-tab]").forEach((b) => {
    b.setAttribute("aria-selected", String(b.dataset.tab === S.tab));
    b.onclick = () => { S.tab = b.dataset.tab; renderTask(t); armIdle(); };
  });

  ({ learn: tabLearn, steps: tabSteps, evidence: tabEvidence, handin: tabHandIn }[S.tab] || tabLearn)(t);
}

/* ------------------------------------------------------------------- learn */
function tabLearn(t) {
  const block = (title, body) => body
    ? `<div class="panel"><h3>${title}</h3><p>${esc(body)}</p></div>` : "";

  $("tabBody").innerHTML = `
    ${block("What you are doing", t.whatYoureDoing)}
    ${block("Why it matters", t.whyItMatters)}
    ${block("Understand it", t.understandText)}
    ${block("Practise it", t.practiseText)}
    ${block("Now do the real thing", t.assignmentText)}
    ${t.deliverableLabel ? `<div class="deliver"><b>What you must hand in:</b> ${esc(t.deliverableLabel)}</div>` : ""}
    ${(t.reviewQuestions || []).length ? `<div class="panel"><h3>Check yourself</h3><ol>
        ${t.reviewQuestions.map((q) => `<li>${esc(q)}</li>`).join("")}</ol></div>` : ""}`;
}

/* ------------------------------------------------------------------- steps */
function tabSteps(t) {
  const steps = t.subSteps || [];
  const locked = isHandedIn(t.taskId);
  $("tabBody").innerHTML = `
    ${locked ? `<div class="verdict sent"><h3>Handed in</h3><p>You cannot change the steps now. If it comes back for more work, they open again.</p></div>` : ""}
    <div class="panel"><h3>Your steps</h3><ul class="steps" id="stepList"></ul></div>`;

  const ul = $("stepList");
  steps.forEach((s) => {
    const on = isComplete(s.subStepId);
    const li = document.createElement("li");
    const b = document.createElement("button");
    b.className = "step" + (on ? " on" : "");
    b.innerHTML = `<span class="box">${on ? "✓" : ""}</span>
      <span class="lbl"><b>${esc(s.title)}</b><span>${esc(s.instructions || "")}</span>
      ${s.evidence ? `<span><b style="display:inline;font-weight:600">Show:</b> ${esc(s.evidence)}</span>` : ""}</span>`;
    b.onclick = () => toggle(s, t);
    li.appendChild(b); ul.appendChild(li);
  });
}

/* ---------------------------------------------------------------- evidence */
function tabEvidence(t) {
  const ev = evidenceFor(t.taskId);
  $("tabBody").innerHTML = `
    <div class="panel">
      <h3>Write your answer</h3>
      <p style="color:var(--muted);font-size:13.5px">Not everything is a file. If the work is
        an explanation, type it here — it is saved as evidence exactly like a photo.</p>
      <textarea id="answer" placeholder="Type your answer here…"></textarea>
      <div class="filerow" style="margin-top:12px">
        <button class="btn small" id="saveAnswer">Save this answer</button>
      </div>
    </div>
    <div class="panel">
      <h3>Attach a file or photo</h3>
      <p style="color:var(--muted);font-size:13.5px">A photo of your written work, a document,
        a screenshot — up to 25 MB.</p>
      <div class="filerow">
        <input type="file" id="file"
               accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.odt,.ods">
        <button class="btn small" id="saveFile">Attach it</button>
      </div>
    </div>
    <div class="panel">
      <h3>What you have attached${ev.length ? ` (${ev.length})` : ""}</h3>
      ${ev.length ? `<div id="evList"></div>`
        : `<p style="color:var(--muted)">Nothing yet. You need at least one piece of evidence before you can hand this in.</p>`}
    </div>`;

  $("saveAnswer").onclick = async () => {
    const text = $("answer").value.trim();
    if (!text) return toast("Type something first.");
    await attach(t.taskId, new Blob([text], { type: "text/plain" }), "written-answer.txt", "Written answer");
    toast("Your answer is saved.");
  };
  $("saveFile").onclick = async () => {
    const f = $("file").files[0];
    if (!f) return toast("Choose a file first.");
    await attach(t.taskId, f, f.name, null);
    toast(`"${f.name}" attached.`);
  };

  const list = $("evList");
  if (!list) return;
  ev.forEach((e) => {
    const row = document.createElement("div");
    row.className = "ev";
    const icon = /^image\//.test(e.mime_type || "") ? "🖼" : /pdf/.test(e.mime_type || "") ? "📄" : "📎";
    row.innerHTML = `<span class="ic">${icon}</span>
      <span class="nm"><b>${esc(e.file_name)}</b>
        <span>${esc(e.description || "")}${e.description ? " · " : ""}${bytes(e.byte_size || 0)}</span></span>
      ${e.waiting ? `<span class="tag wait">Waiting to send</span>`
        : `<a href="${esc(e.download_url)}" target="_blank" rel="noopener">Open</a>`}`;
    list.appendChild(row);
  });
}

/* ----------------------------------------------------------------- hand in */
function tabHandIn(t) {
  const steps = t.subSteps || [], st = taskState(t);
  const ev = evidenceFor(t.taskId);
  const sub = submissionFor(t.taskId);
  const missing = [];
  if (st.of && st.done < st.of) missing.push(`${st.of - st.done} of your ${st.of} steps are not ticked`);
  if (!ev.length) missing.push("you have not attached any evidence");

  const verdict = !sub?.submitted_at ? ""
    : sub.result === "competent"
      ? `<div class="verdict ok"><h3>Competent</h3><p>${esc(sub.feedback || "Your assessor accepted this work.")}</p></div>`
    : sub.result === "requires_improvement"
      ? `<div class="verdict redo"><h3>Not yet competent</h3><p>${esc(sub.feedback || "Your assessor has asked for more work on this.")}</p></div>`
      : `<div class="verdict sent"><h3>Waiting for your assessor</h3><p>Handed in. Nothing more to do on this one for now.</p></div>`;

  $("tabBody").innerHTML = `
    ${verdict}
    <div class="panel">
      <h3>What your assessor will check</h3>
      <ul>${(t.assessmentCriteria || ["Your steps are complete", "Your evidence is attached"])
        .map((c) => `<li>${esc(c)}</li>`).join("")}</ul>
    </div>
    ${sub?.submitted_at ? "" : `
    <div class="panel">
      <h3>How confident are you in this work?</h3>
      <p style="color:var(--muted);font-size:13.5px">1 is "I struggled", 5 is "I could teach it".
        This is how you feel — it does not decide your result.</p>
      <div class="rate" id="rate">
        ${[1, 2, 3, 4, 5].map((n) => `<button data-n="${n}" aria-pressed="${S.rating === n}">${n}</button>`).join("")}
      </div>
      <button class="btn" id="submitBtn" ${missing.length ? "disabled" : ""}>Hand this work in</button>
      ${missing.length ? `<p class="blocked">You cannot hand in yet: ${esc(missing.join(", and "))}.</p>` : ""}
    </div>`}`;

  if (sub?.submitted_at) return;
  $("rate").querySelectorAll("button").forEach((b) => {
    b.onclick = () => { S.rating = Number(b.dataset.n); renderTask(t); };
  });
  $("submitBtn").onclick = () => handIn(t);
}

/* ------------------------------------------------------------- sync status */
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
 * allowed to be quiet.
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
  // Files are dropped from the copy on purpose — a learner cannot carry a
  // 25 MB photo home on a text file, and the photo is still on this PC when
  // they come back. The ticks and hand-ins are what a facilitator can re-enter.
  const blob = new Blob([JSON.stringify({
    learner_ref: S.learner.learner_ref, programme: S.programme,
    saved_at: new Date().toISOString(),
    changes: rows.map(({ blob: _drop, ...rest }) => rest),
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
  Object.assign(S, { token: null, learner: null, record: null, content: null,
    pending: [], selected: null, tab: "learn", rating: null });
  clearTimeout(S.idleTimer); closeSheet();
  $("work").classList.add("hide");
  $("signin").classList.remove("hide");
  $("ref").value = ""; $("pin").value = "";
  const err = $("signinErr");
  if (message) { err.textContent = message; err.classList.remove("hide"); } else { err.classList.add("hide"); }
}

/* ===================================================================== idle */
function armIdle() {
  clearTimeout(S.idleTimer);
  if (!S.token) return;
  S.idleTimer = setTimeout(async () => { await drain(); signOut(); }, CFG.idleMinutes * 60000);
}
["click", "keydown", "pointerdown"].forEach((e) =>
  document.addEventListener(e, () => { if (S.token) armIdle(); }));

/* =================================================== modals and small talk */
function sheet(html) { $("sheet").innerHTML = html; $("modal").classList.remove("hide"); }
function closeSheet() { $("modal").classList.add("hide"); }
let toastTimer = null;
function toast(message) {
  document.querySelector(".toast")?.remove();
  const el = document.createElement("div");
  el.className = "toast"; el.textContent = message;
  document.body.appendChild(el);
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.remove(), 3200);
}

/* ===================================================== boot / offline / PWA */
(async function boot() {
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
      if (ents) { await openWorkspace(ents); return; }
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
