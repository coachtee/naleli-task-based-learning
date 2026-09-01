@php($name = $learner->preferred_name ?: $learner->first_name)

<x-learner-shell :learner="$learner" title="Choose your PIN" :heading="'Welcome, '.$name.'. Let\'s open your course.'">

  @if ($errors->any())
    <div class="card">
      <div class="errors">
        <strong>Please check this:</strong>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    </div>
  @endif

  <div class="card">
    <h2>Your student number</h2>
    <p class="id">{{ $learner->learner_ref }}</p>
    <p class="hint">Write this down. You will type it every time you sign in at the computer lab.</p>
  </div>

  <div class="card">
    <h2>{{ $alreadySet ? 'Choose a new PIN' : 'Now choose a PIN' }}</h2>
    <p class="hint">
      {{ $alreadySet
          ? 'You already have a PIN. Setting a new one here stops the old one working straight away.'
          : 'Six numbers, and only you should know them. You will use your student number and this PIN together to sign in.' }}
    </p>

    <form method="POST" action="{{ route('learner.access.update', ['learner' => $learner->id]).'?'.request()->getQueryString() }}">
      @csrf
      <label for="pin">Your PIN</label>
      <input class="pin" id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*"
             maxlength="6" autocomplete="new-password" placeholder="••••••" required autofocus>

      <label for="pin_confirmation">Type it again</label>
      <input class="pin" id="pin_confirmation" name="pin_confirmation" type="password"
             inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="new-password"
             placeholder="••••••" required>

      <button type="submit">Save my PIN</button>
    </form>
  </div>

  <div class="card">
    <h2>Keep it to yourself</h2>
    <p class="hint">
      The computers at KCS are shared. Anyone with your student number and PIN can open
      your work and hand it in as their own. If you think somebody has seen it, come and
      ask us for a new one — it takes a minute.
    </p>
  </div>

</x-learner-shell>
