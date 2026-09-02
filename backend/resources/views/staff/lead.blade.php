@php
  $learner = $application->learner;
  $lastTouches = $application->leadTouches;
@endphp
<x-staff-detail-shell
  :back="route('staff.calls.shell')"
  :name="trim($learner->first_name.' '.$learner->last_name) ?: $learner->learner_ref"
  :status="$application->status->value"
  :statusLabel="$application->status->label()"
>
  <div style="display:flex;gap:8px;padding:16px 16px 4px">
    <a href="tel:{{ $learner->phone }}" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;{{ $learner->phone ? '' : 'opacity:.35;pointer-events:none' }}">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--coral-bg);color:var(--coral-dark);display:grid;place-items:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="width:21px;height:21px"><path d="M4.5 5.5c0-1 .8-1.8 1.8-1.8h1.8c.5 0 .9.3 1 .8l.9 3a1 1 0 0 1-.3 1L8 10.2c1 2.1 2.7 3.8 4.8 4.8l1.7-1.7a1 1 0 0 1 1-.3l3 .9c.5.1.8.5.8 1V16.7c0 1-.8 1.8-1.8 1.8C10.9 18.5 5.5 13.1 4.5 5.5Z"/></svg>
      </div>
      <div style="font-size:10.5px;font-weight:700;color:var(--ink)">Call</div>
    </a>
    <button id="waBtn" data-enabled="{{ $whatsappLink ? '1' : '0' }}" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;{{ $whatsappLink ? '' : 'opacity:.35;pointer-events:none' }}">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--success-bg);color:var(--success);display:grid;place-items:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:21px;height:21px"><path d="M4 20l1.3-3.9A7.9 7.9 0 1 1 8.5 19L4 20Z"/><path d="M9 10c0 3 2.5 5 5 5"/></svg>
      </div>
      <div style="font-size:10.5px;font-weight:700;color:var(--ink)">WhatsApp</div>
    </button>
    <a href="mailto:{{ $learner->email }}" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;{{ $learner->email ? '' : 'opacity:.35;pointer-events:none' }}">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--info-bg);color:var(--info);display:grid;place-items:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:21px;height:21px"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="m5 7 7 6 7-6"/></svg>
      </div>
      <div style="font-size:10.5px;font-weight:700;color:var(--ink)">Email</div>
    </a>
    <button id="logBtn" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--gray-bg);color:var(--muted);display:grid;place-items:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:21px;height:21px"><path d="M4 4v6h6"/><path d="M4.6 15a8 8 0 1 0 2-8.4L4 10"/></svg>
      </div>
      <div style="font-size:10.5px;font-weight:700;color:var(--ink)">Log call</div>
    </button>
  </div>

  <div class="section-hd"><h2>Lead info</h2></div>
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;margin:0 16px;overflow:hidden">
    <div style="padding:11px 16px"><div style="font-size:11px;color:var(--muted);font-weight:600">Lead source</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $application->source->label() }}{{ $application->campaign ? ' — '.$application->campaign : '' }}</div></div>
    <div style="padding:11px 16px;border-top:1px solid var(--line)"><div style="font-size:11px;color:var(--muted);font-weight:600">Programme interested</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $application->programme?->name ?? 'Not chosen yet' }}</div></div>
    <div style="padding:11px 16px;border-top:1px solid var(--line)"><div style="font-size:11px;color:var(--muted);font-weight:600">First contact</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ ($application->first_contacted_at ?? $application->applied_at)?->format('j M Y, H:i') ?? '—' }}</div></div>
  </div>

  <div class="section-hd"><h2>Contact details</h2></div>
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;margin:0 16px;overflow:hidden">
    <div style="padding:11px 16px"><div style="font-size:11px;color:var(--muted);font-weight:600">Phone</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->phone ?? '—' }}</div></div>
    <div style="padding:11px 16px;border-top:1px solid var(--line)"><div style="font-size:11px;color:var(--muted);font-weight:600">WhatsApp</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->whatsapp ?? $learner->phone ?? '—' }}</div></div>
    <div style="padding:11px 16px;border-top:1px solid var(--line)"><div style="font-size:11px;color:var(--muted);font-weight:600">Email</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->email ?? '—' }}</div></div>
  </div>

  <div class="section-hd"><h2>Call history &middot; {{ $lastTouches->count() }} {{ Str::plural('attempt', $lastTouches->count()) }}</h2></div>
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;margin:0 16px 16px;overflow:hidden">
    @forelse($lastTouches as $touch)
      <div style="display:flex;gap:10px;padding:11px 16px;{{ $loop->first ? '' : 'border-top:1px solid var(--line)' }}">
        <div style="width:28px;height:28px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;background:var(--{{ $touch->outcome->color() === 'gray' ? 'gray-bg' : $touch->outcome->color().'-bg' }});color:var(--{{ $touch->outcome->color() === 'gray' ? 'muted' : $touch->outcome->color() }})">
          <span style="font-size:11px;font-weight:800">{{ mb_strtoupper(mb_substr($touch->channel->value, 0, 1)) }}</span>
        </div>
        <div class="row-body">
          <div style="display:flex;align-items:center;gap:8px;justify-content:space-between">
            <span style="font-size:13px;font-weight:800;color:var(--ink)">{{ $touch->outcome->label() }}</span>
            <span style="font-size:11px;color:var(--faint);font-weight:600;white-space:nowrap">{{ $touch->occurred_at->format('j M') }}</span>
          </div>
          @if($touch->note)
            <div style="font-size:12.5px;color:var(--muted);margin-top:2px">{{ $touch->note }}</div>
          @endif
        </div>
      </div>
    @empty
      <div style="padding:16px;text-align:center;color:var(--muted);font-size:13px">Nobody has spoken to this person yet.</div>
    @endforelse
  </div>

  <div style="height:80px"></div>
  <div class="stickybar">
    <button type="button" class="btn btn-outline" id="stickyLogBtn">Log a call</button>
    <a class="btn btn-coral" href="{{ route('filament.admin.resources.applications.edit', $application) }}">Register them</a>
  </div>

  <x-slot:scripts>
    <script>window.CFG = { csrf: document.querySelector('meta[name=csrf-token]').content, applicationId: {{ $application->id }} };</script>
    @verbatim
    <style>
      #veil{position:fixed;inset:0;background:rgba(10,31,61,.42);z-index:20;display:flex;align-items:flex-end}
      #veil.hide{display:none}
      #sheet{background:#fff;border-radius:20px 20px 0 0;padding:10px 16px calc(18px + env(safe-area-inset-bottom));width:100%;max-height:85vh;overflow:auto}
      #sheet h2{font-size:16px;font-weight:800;margin:6px 0 2px}
      #sheet .sub{font-size:12.5px;color:var(--muted);font-weight:600;margin:0 0 14px}
      #sheet label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin:14px 0 8px}
      .chips2{display:flex;flex-wrap:wrap;gap:8px}
      .chip2{padding:9px 13px;border-radius:11px;border:1.4px solid var(--line);font-size:12.5px;font-weight:700;color:var(--muted);background:#fff}
      .chip2.on{background:var(--navy);border-color:var(--navy);color:#fff}
      #sheet textarea{width:100%;border:1.4px solid var(--line);border-radius:12px;padding:11px 12px;font:inherit;resize:vertical;min-height:70px;margin-top:2px}
      #sheet .row{display:flex;gap:10px;margin-top:18px}
      #sheet .row button{flex:1;height:46px;border-radius:12px;font-size:14px;font-weight:800}
      #sheet .cancel{background:var(--gray-bg);color:var(--ink)}
      #sheet .save{background:var(--coral);color:#fff}
      #sheet .save:disabled{opacity:.5}
      .toast{position:fixed;left:16px;right:16px;bottom:20px;background:var(--ink);color:#fff;padding:12px 16px;border-radius:12px;font-size:13px;font-weight:600;z-index:30}
    </style>
    <div id="veil" class="hide"><div id="sheet"></div></div>
    <script>
    (() => {
      "use strict";
      const $ = (id) => document.getElementById(id);
      const CHANNELS = [["phone","Call"],["whatsapp","WhatsApp"],["email","Email"]];
      const OUTCOMES = [
        ["no_answer","No answer"],["left_message","Left a message"],["spoke","Spoke to them"],
        ["sent_info","Sent the course info"],["will_register","Says they will register"],
        ["not_now","Interested, not this intake"],["not_interested","Not interested"],["wrong_number","Wrong number"],
      ];

      async function api(path, opts = {}) {
        const headers = { "Accept": "application/json", "X-CSRF-TOKEN": window.CFG.csrf, ...(opts.headers || {}) };
        if (opts.json !== undefined) { headers["Content-Type"] = "application/json"; opts.body = JSON.stringify(opts.json); }
        const res = await fetch(path, { ...opts, headers });
        const body = await res.json().catch(() => null);
        if (!res.ok) throw Object.assign(new Error("http"), { status: res.status, body });
        return body;
      }

      function toast(msg) {
        document.querySelector(".toast")?.remove();
        const el = document.createElement("div");
        el.className = "toast"; el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 2600);
      }

      function openSheet() {
        let channel = "phone", outcome = null;
        const paint = () => {
          $("sheet").innerHTML = `
            <h2>Log this call</h2>
            <p class="sub">The next person to call them reads this.</p>
            <label>How did you reach out?</label>
            <div class="chips2" id="chChannel">${CHANNELS.map(([v,l]) => `<button type="button" class="chip2 ${v===channel?"on":""}" data-v="${v}">${l}</button>`).join("")}</div>
            <label>What happened?</label>
            <div class="chips2" id="chOutcome">${OUTCOMES.map(([v,l]) => `<button type="button" class="chip2 ${v===outcome?"on":""}" data-v="${v}">${l}</button>`).join("")}</div>
            <label>What did they say? (optional)</label>
            <textarea id="note" placeholder="A note for whoever calls them next."></textarea>
            <div class="row">
              <button type="button" class="cancel" id="cancelBtn">Cancel</button>
              <button type="button" class="save" id="saveBtn" ${outcome ? "" : "disabled"}>Save and move to next</button>
            </div>`;
          $("chChannel").querySelectorAll(".chip2").forEach((b) => b.onclick = () => { channel = b.dataset.v; paint(); });
          $("chOutcome").querySelectorAll(".chip2").forEach((b) => b.onclick = () => { outcome = b.dataset.v; paint(); });
          $("cancelBtn").onclick = () => $("veil").classList.add("hide");
          $("saveBtn").onclick = async () => {
            const btn = $("saveBtn");
            btn.disabled = true; btn.textContent = "Saving…";
            try {
              await api(`/calls/api/leads/${window.CFG.applicationId}/log`, {
                method: "POST",
                json: { channel, outcome, note: $("note").value || null },
              });
              $("veil").classList.add("hide");
              toast("Logged — reloading…");
              setTimeout(() => location.reload(), 500);
            } catch (e) {
              toast("Could not save that.");
              btn.disabled = false; btn.textContent = "Save and move to next";
            }
          };
        };
        paint();
        $("veil").classList.remove("hide");
      }

      $("logBtn").onclick = openSheet;
      $("stickyLogBtn")?.addEventListener("click", openSheet);
      $("veil").addEventListener("click", (e) => { if (e.target.id === "veil") $("veil").classList.add("hide"); });

      const waBtn = $("waBtn");
      if (waBtn && waBtn.dataset.enabled === "1") {
        waBtn.onclick = async () => {
          try {
            const { url } = await api(`/calls/api/leads/${window.CFG.applicationId}/whatsapp`, { method: "POST" });
            window.open(url, "_blank");
            toast("Logged as sent — WhatsApp opened.");
          } catch (e) {
            toast(e.body?.message || "Could not open WhatsApp.");
          }
        };
      }
    })();
    </script>
    @endverbatim
  </x-slot:scripts>
</x-staff-detail-shell>
