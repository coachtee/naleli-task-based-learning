@php $user = auth()->user(); @endphp
<x-staff-shell active="more" title="More">
  <div style="margin:16px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px;display:flex;align-items:center;gap:13px">
    <div style="width:52px;height:52px;border-radius:50%;background:var(--navy);color:#fff;display:grid;place-items:center;font-size:16px;font-weight:800;flex-shrink:0">
      {{ collect(explode(' ', (string) $user?->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') ?: 'KA' }}
    </div>
    <div class="row-body">
      <div style="font-size:15.5px;font-weight:800;color:var(--ink)">{{ $user?->name }}</div>
      <div class="row-meta">{{ $user?->role?->label() }} &middot; KCS Education</div>
    </div>
  </div>

  <div style="margin:18px 16px 8px;font-size:12px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.3px">Leads &amp; campaigns</div>
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;margin:0 16px;overflow:hidden">
    <a href="{{ route('staff.calls.shell') }}?import=1" style="display:flex;align-items:center;gap:13px;padding:14px 16px">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--gray-bg);color:var(--ink);display:grid;place-items:center;flex-shrink:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px"><path d="M4 16.5V6.8a1.8 1.8 0 0 1 1.8-1.8h5l1.8 2h6.6A1.8 1.8 0 0 1 21 8.8v7.7a1.8 1.8 0 0 1-1.8 1.8H5.8A1.8 1.8 0 0 1 4 16.5Z"/></svg>
      </div>
      <div>
        <div style="font-size:14px;font-weight:700;color:var(--ink)">Import Facebook leads</div>
        <div style="font-size:11.5px;color:var(--faint);font-weight:600">Upload a Leads Centre export</div>
      </div>
    </a>
    <a href="{{ \App\Support\AdminUrl::route('filament.admin.resources.programmes.index') }}" style="display:flex;align-items:center;gap:13px;padding:14px 16px;border-top:1px solid var(--line)">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--gray-bg);color:var(--ink);display:grid;place-items:center;flex-shrink:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v5l3 2"/></svg>
      </div>
      <div style="font-size:14px;font-weight:700;color:var(--ink)">Programmes &amp; offerings</div>
    </a>
  </div>

  <div style="margin:18px 16px 8px;font-size:12px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.3px">Desktop dashboard</div>
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;margin:0 16px 16px;overflow:hidden">
    <a href="{{ \App\Support\AdminUrl::route('filament.admin.pages.dashboard') }}" style="display:flex;align-items:center;gap:13px;padding:14px 16px">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--gray-bg);color:var(--ink);display:grid;place-items:center;flex-shrink:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
      </div>
      <div>
        <div style="font-size:14px;font-weight:700;color:var(--ink)">Open the full admin panel</div>
        <div style="font-size:11.5px;color:var(--faint);font-weight:600">Everything mobile doesn't show yet</div>
      </div>
    </a>
    <form method="POST" action="{{ \App\Support\AdminUrl::route('filament.admin.auth.logout') }}" style="border-top:1px solid var(--line)">
      @csrf
      <button type="submit" style="width:100%;display:flex;align-items:center;gap:13px;padding:14px 16px;text-align:left">
        <div style="width:36px;height:36px;border-radius:10px;background:var(--danger-bg);color:var(--danger);display:grid;place-items:center;flex-shrink:0">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px"><path d="M9 6V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H11a2 2 0 0 1-2-2v-1"/><path d="M13 12H3m0 0 3-3m-3 3 3 3"/></svg>
        </div>
        <div style="font-size:14px;font-weight:700;color:var(--danger)">Log out</div>
      </button>
    </form>
  </div>
</x-staff-shell>
