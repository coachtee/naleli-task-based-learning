<x-staff-detail-shell
  :back="route('staff.records.index', ['tab' => 'learners'])"
  :name="$learner->full_name"
  :status="$learner->status->value"
  :statusLabel="$learner->status->label()"
>
  <x-slot:topActions>
    <a class="kebab" href="{{ route('filament.admin.resources.learners.edit', $learner) }}" aria-label="Edit in full">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
    </a>
  </x-slot:topActions>

  <div style="display:flex;gap:8px;padding:16px 16px 4px">
    <a href="tel:{{ $learner->phone }}" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;{{ $learner->phone ? '' : 'opacity:.35;pointer-events:none' }}">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--coral-bg);color:var(--coral-dark);display:grid;place-items:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="width:21px;height:21px"><path d="M4.5 5.5c0-1 .8-1.8 1.8-1.8h1.8c.5 0 .9.3 1 .8l.9 3a1 1 0 0 1-.3 1L8 10.2c1 2.1 2.7 3.8 4.8 4.8l1.7-1.7a1 1 0 0 1 1-.3l3 .9c.5.1.8.5.8 1V16.7c0 1-.8 1.8-1.8 1.8C10.9 18.5 5.5 13.1 4.5 5.5Z"/></svg>
      </div>
      <div style="font-size:10.5px;font-weight:700;color:var(--ink)">Call</div>
    </a>
    @php $whatsapp = $paymentLink ?: $profileLink; @endphp
    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;{{ $whatsapp ? '' : 'opacity:.35;pointer-events:none' }}">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--success-bg);color:var(--success);display:grid;place-items:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:21px;height:21px"><path d="M4 20l1.3-3.9A7.9 7.9 0 1 1 8.5 19L4 20Z"/><path d="M9 10c0 3 2.5 5 5 5"/></svg>
      </div>
      <div style="font-size:10.5px;font-weight:700;color:var(--ink)">WhatsApp</div>
    </a>
    <a href="mailto:{{ $learner->email }}" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;{{ $learner->email ? '' : 'opacity:.35;pointer-events:none' }}">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--info-bg);color:var(--info);display:grid;place-items:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:21px;height:21px"><rect x="4" y="6" width="16" height="12" rx="2"/><path d="m5 7 7 6 7-6"/></svg>
      </div>
      <div style="font-size:10.5px;font-weight:700;color:var(--ink)">Email</div>
    </a>
    <a href="{{ $paymentLink }}" target="_blank" rel="noopener" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;{{ $paymentLink ? '' : 'opacity:.35;pointer-events:none' }}">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--violet-bg);color:var(--violet);display:grid;place-items:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:21px;height:21px"><rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 10h18"/></svg>
      </div>
      <div style="font-size:10.5px;font-weight:700;color:var(--ink)">Payment link</div>
    </a>
  </div>

  @if($enrolment)
    <div class="section-hd" style="padding-top:16px"><h2>Enrolment</h2></div>
    <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;margin:0 16px;padding:14px 16px;display:flex;align-items:center;gap:12px">
      <div style="width:42px;height:42px;border-radius:11px;background:var(--violet-bg);color:var(--violet);display:grid;place-items:center;flex-shrink:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M3 9.5 12 5l9 4.5-9 4.5-9-4.5Z"/><path d="M7 11.5V16c0 1.1 2.2 2 5 2s5-.9 5-2v-4.5"/></svg>
      </div>
      <div class="row-body">
        <div style="font-size:13.5px;font-weight:800;color:var(--ink)">{{ $enrolment->programme?->name }}</div>
        <div class="row-meta">{{ $enrolment->intake?->label }} intake &middot; {{ $settledCount }} of {{ $totalInvoices }} invoices settled</div>
      </div>
    </div>
  @endif

  <div class="section-hd"><h2>Personal details</h2></div>
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;margin:0 16px;overflow:hidden">
    <div style="padding:11px 16px"><div style="font-size:11px;color:var(--muted);font-weight:600">First name</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->first_name }}</div></div>
    <div style="padding:11px 16px;border-top:1px solid var(--line)"><div style="font-size:11px;color:var(--muted);font-weight:600">Last name</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->last_name }}</div></div>
    <div style="padding:11px 16px;border-top:1px solid var(--line)"><div style="font-size:11px;color:var(--muted);font-weight:600">First registered</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->first_registered_year }}</div></div>
  </div>

  <div class="section-hd"><h2>Contact information</h2></div>
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;margin:0 16px;overflow:hidden">
    <div style="padding:11px 16px"><div style="font-size:11px;color:var(--muted);font-weight:600">Email</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->email ?? '—' }}</div></div>
    <div style="padding:11px 16px;border-top:1px solid var(--line)"><div style="font-size:11px;color:var(--muted);font-weight:600">Phone</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->phone ?? '—' }}</div></div>
    <div style="padding:11px 16px;border-top:1px solid var(--line)"><div style="font-size:11px;color:var(--muted);font-weight:600">WhatsApp</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->whatsapp ?? '—' }}</div></div>
  </div>

  <div class="section-hd"><h2>ID details</h2></div>
  <div style="background:var(--card);border:1px solid var(--line);border-radius:16px;margin:0 16px 16px;overflow:hidden">
    <div style="padding:11px 16px"><div style="font-size:11px;color:var(--muted);font-weight:600">ID type</div><div style="font-size:14px;color:var(--ink);font-weight:700">{{ $learner->id_type?->label() ?? 'Not on file' }}</div></div>
    <div style="padding:11px 16px;border-top:1px solid var(--line)"><div style="font-size:11px;color:var(--muted);font-weight:600">ID number</div><div style="font-size:14px;color:var(--ink);font-weight:700;font-family:ui-monospace,SFMono-Regular,Menlo,monospace">{{ $learner->id_number_masked ?? '—' }}</div></div>
  </div>

  @if(! empty($missing))
    <div style="margin:0 16px 16px;background:var(--rose-bg);border-radius:14px;padding:13px 14px;font-size:12.5px;color:#9E2249;font-weight:600">
      Still outstanding: {{ implode(', ', $missing) }}.
    </div>
  @endif
</x-staff-detail-shell>
