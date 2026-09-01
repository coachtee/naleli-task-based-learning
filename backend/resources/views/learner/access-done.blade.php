@php($name = $learner->preferred_name ?: $learner->first_name)

<x-learner-shell :learner="$learner" title="You're ready" :heading="'You\'re ready, '.$name.'.'">

  <div class="card">
    <div class="done"><strong>Your PIN is saved.</strong> You can sign in now.</div>

    <h2>Signing in at KCS</h2>
    <ol>
      <li>Open <strong>{{ $workspace }}</strong> on any computer in the lab.</li>
      <li>Type your student number: <strong>{{ $learner->learner_ref }}</strong></li>
      <li>Type the PIN you just chose.</li>
    </ol>
  </div>

  <div class="card">
    <a class="cta" href="{{ $workspace }}">Open my workspace</a>
    <p class="hint" style="margin-top:14px">
      Your work is saved to your student number, not to the computer. Sign in at any
      machine, or on your phone at home, and carry on where you left off.
    </p>
  </div>

</x-learner-shell>
