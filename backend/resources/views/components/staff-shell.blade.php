{{--
    The shared chrome for every tab-root staff screen (Dashboard, Leads,
    Records, More): navy header carrying the real Bohlale mark, and the
    bottom tab bar. Detail screens (a single lead or learner) use
    <x-staff-detail-shell> instead — a back button, not a nav bar, because
    they are somewhere you drill into, not a place you land.
--}}
@props(['active', 'title'])
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $title }} — KCS</title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0A1F3D">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('staff._styles')
</head>
<body>
<div class="topbar">
  <div class="mark"><img src="{{ asset('brand/bohlale-logo.png') }}" alt="Bohlale"></div>
  <h1>{{ $title }}@isset($subtitle)<span class="sub">{{ $subtitle }}</span>@endisset</h1>
  {{ $topActions ?? '' }}
</div>

{{ $slot }}

<nav class="bottomnav">
  <a class="navitem @if($active === 'dashboard') on @endif" href="{{ route('staff.dashboard') }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9"/></svg>
    <span class="lbl">Dashboard</span>
  </a>
  <a class="navitem @if($active === 'leads') on @endif" href="{{ route('staff.calls.shell') }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6.6 10.8c1.4 2.8 3.8 5.2 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.3 1.2.4 2.5.6 3.8.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C11 21 3 13 3 4.7c0-.6.4-1 1-1H7.5c.6 0 1 .4 1 1 0 1.3.2 2.6.6 3.8.1.4 0 .8-.3 1.1L6.6 10.8Z"/></svg>
    <span class="lbl">Leads</span>
  </a>
  <a class="navitem @if($active === 'records') on @endif" href="{{ route('staff.records.index') }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h6L15 6.5h2.5A2.5 2.5 0 0 1 20 9v8.5A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-11Z"/></svg>
    <span class="lbl">Records</span>
  </a>
  <a class="navitem @if($active === 'more') on @endif" href="{{ route('staff.more') }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
    <span class="lbl">More</span>
  </a>
</nav>

{{ $scripts ?? '' }}
</body>
</html>
