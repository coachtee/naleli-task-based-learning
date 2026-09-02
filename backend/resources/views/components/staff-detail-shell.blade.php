{{--
    The shared chrome for a drill-in staff screen: a lead's profile or a
    learner's profile. A back button, not the tab bar — you arrived here
    from a list, and the natural next move is back to it, not sideways to
    another tab.
--}}
@props(['back', 'name', 'status' => null, 'statusLabel' => null])
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $name }} — KCS</title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#ffffff">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('staff._styles')
</head>
<body>
<div class="topbar-plain">
  <a class="backbtn" href="{{ $back }}" aria-label="Back">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 5-7 7 7 7"/></svg>
  </a>
  <div class="ttl">
    <div class="name">{{ $name }}</div>
    @if($statusLabel)
      <span class="pill pill-{{ $status }}">{{ $statusLabel }}</span>
    @endif
  </div>
  {{ $topActions ?? '' }}
</div>

{{ $slot }}

{{ $scripts ?? '' }}
</body>
</html>
