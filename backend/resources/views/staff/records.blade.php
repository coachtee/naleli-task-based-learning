<x-staff-shell active="records" title="Records">
  <div class="segmented">
    <a class="seg @if($tab === 'registrations') active @endif" href="{{ route('staff.records.index', ['tab' => 'registrations']) }}">Registrations</a>
    <a class="seg @if($tab === 'learners') active @endif" href="{{ route('staff.records.index', ['tab' => 'learners']) }}">Learners</a>
    <a class="seg @if($tab === 'invoices') active @endif" href="{{ route('staff.records.index', ['tab' => 'invoices']) }}">Invoices</a>
  </div>

  @if($tab === 'registrations')
    <div class="card-list" style="padding-top:12px">
      @forelse($registrations as $row)
        @php $a = $row['application']; $learner = $a->learner; @endphp
        <div class="row-card">
          <div class="row-top">
            <div class="row-avatar" style="background:var(--navy)">{{ mb_strtoupper(mb_substr($learner->first_name, 0, 1).mb_substr($learner->last_name, 0, 1)) }}</div>
            <div class="row-body">
              <div class="row-name">{{ $learner->full_name }}</div>
              <div style="font-size:11px;color:var(--faint);font-weight:700">{{ $learner->learner_ref }}</div>
            </div>
            <span class="pill pill-{{ $a->status->value }}">{{ $a->status->label() }}</span>
          </div>
          <div class="row-meta" style="margin-top:8px">{{ $a->programme?->name ?? 'No programme yet' }}</div>
          <div style="height:6px;border-radius:100px;background:var(--gray-bg);overflow:hidden;margin-top:10px">
            <div style="display:block;height:100%;background:var(--coral);width:{{ $row['percent'] }}%"></div>
          </div>
          <div style="font-size:11px;color:var(--faint);font-weight:700;margin-top:5px">{{ $row['percent'] }}% of profile complete</div>
          <div class="row-actions" style="margin-top:11px;display:flex;gap:8px">
            @if($row['profile_link'])
              <a class="btn btn-outline" style="flex:1" href="{{ $row['profile_link'] }}" target="_blank" rel="noopener">Ask to finish profile</a>
            @endif
            <a class="btn btn-coral" style="flex:1" href="{{ route('filament.admin.resources.applications.edit', $a) }}">Edit</a>
          </div>
        </div>
      @empty
        <div class="row-card" style="text-align:center;color:var(--muted)">Nothing in progress right now.</div>
      @endforelse
    </div>
  @endif

  @if($tab === 'learners')
    <div class="card-list" style="padding-top:12px;gap:8px">
      @forelse($learners as $row)
        @php $l = $row['learner']; @endphp
        <a href="{{ route('staff.records.learner', $l) }}" class="row-card" style="display:flex;align-items:center;gap:12px;padding:12px 14px">
          <div class="row-avatar" style="background:var(--navy);width:40px;height:40px">{{ mb_strtoupper(mb_substr($l->first_name, 0, 1).mb_substr($l->last_name, 0, 1)) }}</div>
          <div class="row-body">
            <div class="row-name">{{ $l->full_name }}</div>
            <div class="row-meta" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $l->email ?? $l->phone ?? 'No contact on file' }}</div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <span class="pill" style="background:{{ $row['percent'] >= 100 ? 'var(--success-bg)' : 'var(--warning-bg)' }};color:{{ $row['percent'] >= 100 ? 'var(--success)' : 'var(--warning)' }}">{{ $row['percent'] }}% complete</span>
          </div>
        </a>
      @empty
        <div class="row-card" style="text-align:center;color:var(--muted)">No learners on record yet.</div>
      @endforelse
    </div>
  @endif

  @if($tab === 'invoices')
    <div class="card-list" style="padding-top:12px;gap:8px">
      @forelse($invoices as $invoice)
        @php
          $overdue = $invoice->status->value === 'due' && $invoice->due_on && $invoice->due_on->isPast();
          $total = $invoice->enrolment?->invoices()->count() ?? 1;
        @endphp
        <div class="row-card" style="display:flex;align-items:center;gap:12px;padding:12px 14px">
          <div style="width:38px;height:38px;border-radius:10px;background:var(--gray-bg);display:grid;place-items:center;color:var(--muted);flex-shrink:0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/></svg>
          </div>
          <div class="row-body">
            <div style="font-size:13.5px;font-weight:800;color:var(--ink)">Invoice {{ $invoice->sequence }} &middot; {{ $invoice->description }}</div>
            <div class="row-meta">{{ $invoice->learner->full_name }} &middot; balance {{ $invoice->sequence }} of {{ $total }}</div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div style="font-size:14.5px;font-weight:800;color:var(--ink)">R{{ number_format($invoice->amount_cents / 100, 2) }}</div>
            @if($invoice->status->value === 'paid')
              <span class="pill" style="background:var(--success-bg);color:var(--success)">Paid</span>
            @elseif($overdue)
              <span class="pill" style="background:var(--danger-bg);color:var(--danger)">Overdue</span>
            @else
              <span class="pill" style="background:var(--warning-bg);color:var(--warning)">Due {{ $invoice->due_on?->format('j M') ?? '—' }}</span>
            @endif
          </div>
        </div>
      @empty
        <div class="row-card" style="text-align:center;color:var(--muted)">No invoices yet.</div>
      @endforelse
    </div>
  @endif
</x-staff-shell>
