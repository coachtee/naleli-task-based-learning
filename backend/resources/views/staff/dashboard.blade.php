<x-staff-shell active="dashboard" title="KCS Education" subtitle="Sales & Registrations">
  <x-slot:topActions>
    <div class="avatar">{{ collect(explode(' ', (string) auth()->user()?->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') ?: 'KA' }}</div>
  </x-slot:topActions>

  <div style="padding:16px 16px 4px">
    <div style="font-size:19px;font-weight:800;letter-spacing:-.3px">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', (string) auth()->user()?->name)[0] ?? 'there' }}</div>
    <div style="font-size:12.5px;color:var(--muted);margin-top:2px;font-weight:600">{{ now()->format('l, j F') }}</div>
    <div style="font-size:11px;color:var(--faint);margin-top:6px;font-weight:700">{{ $touchesToday }} {{ Str::plural('touch', $touchesToday) }} logged today</div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:14px 16px 4px">
    <a href="{{ route('staff.calls.shell') }}" style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:13px 14px;display:block">
      <div style="font-size:21px;font-weight:800;letter-spacing:-.4px">{{ $leadsToCall }}</div>
      <div style="font-size:12px;color:var(--muted);margin-top:1px;font-weight:600">Leads to call</div>
      @if($overdue > 0)
        <div style="font-size:11px;font-weight:700;margin-top:7px;color:var(--danger)">{{ $overdue }} overdue</div>
      @else
        <div style="font-size:11px;font-weight:700;margin-top:7px;color:var(--faint)">None overdue</div>
      @endif
    </a>
    <a href="{{ route('staff.records.index', ['tab' => 'registrations']) }}" style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:13px 14px;display:block">
      <div style="font-size:21px;font-weight:800;letter-spacing:-.4px">{{ $newRegistrations }}</div>
      <div style="font-size:12px;color:var(--muted);margin-top:1px;font-weight:600">New registrations</div>
      <div style="font-size:11px;font-weight:700;margin-top:7px;color:var(--faint)">In progress</div>
    </a>
    <a href="{{ route('staff.records.index', ['tab' => 'invoices']) }}" style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:13px 14px;display:block">
      <div style="font-size:21px;font-weight:800;letter-spacing:-.4px">{{ $awaitingPayment }} &middot; R{{ number_format($outstandingCents / 100, 2) }}</div>
      <div style="font-size:12px;color:var(--muted);margin-top:1px;font-weight:600">Awaiting payment</div>
      <div style="font-size:11px;font-weight:700;margin-top:7px;color:var(--warning)">Outstanding</div>
    </a>
    <a href="{{ route('staff.records.index', ['tab' => 'invoices']) }}" style="background:var(--card);border:1px solid var(--line);border-radius:16px;padding:13px 14px;display:block">
      <div style="font-size:21px;font-weight:800;letter-spacing:-.4px">R{{ number_format($settledCents / 100, 2) }}</div>
      <div style="font-size:12px;color:var(--muted);margin-top:1px;font-weight:600">Settled this month</div>
      <div style="font-size:11px;font-weight:700;margin-top:7px;color:var(--success)">{{ $settledCount }} {{ Str::plural('payment', $settledCount) }}</div>
    </a>
  </div>

  <div class="section-hd">
    <h2>Call these people</h2>
    <a class="link" href="{{ route('staff.calls.shell') }}">See all {{ $leadsToCall }}</a>
  </div>

  <div class="card-list">
    @forelse($preview as $application)
      @php
        $learner = $application->learner;
        $lastTouch = $application->leadTouches->first();
        $name = trim("{$learner->first_name} {$learner->last_name}") ?: $learner->learner_ref;
        $initials = mb_strtoupper(mb_substr($learner->first_name, 0, 1).mb_substr($learner->last_name, 0, 1));
      @endphp
      <a class="row-card" style="display:block" href="{{ route('staff.calls.show', $application) }}">
        <div class="row-top">
          <div class="row-avatar" style="background:var(--navy)">{{ $initials }}</div>
          <div class="row-body">
            <div class="row-name">{{ $name }} <span class="pill pill-{{ $application->status->value }}">{{ $application->status->label() }}</span></div>
            <div class="row-meta">{{ $application->programme?->name ?? 'No programme yet' }} &middot; {{ $application->source->label() }}</div>
            <div style="font-size:11.5px;margin-top:5px;font-weight:700;color:{{ $application->next_action_at->isPast() ? 'var(--danger)' : 'var(--faint)' }}">
              {{ $application->next_action_at->isPast() ? $application->next_action_at->diffForHumans().' overdue' : 'due '.$application->next_action_at->diffForHumans() }}
              &middot; {{ $lastTouch ? $lastTouch->outcome->label() : 'never called' }}
            </div>
          </div>
        </div>
      </a>
    @empty
      <div class="row-card" style="text-align:center;color:var(--muted)">Nobody is waiting — every lead has a date to be called on.</div>
    @endforelse
  </div>
</x-staff-shell>
