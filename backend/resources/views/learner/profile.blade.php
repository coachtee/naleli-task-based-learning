<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Finish your registration — Katlehong Computer School</title>
<style>
  :root{
    --navy:#0A192F; --navy-2:#12203D; --coral:#FF7A59;
    --line:#DCE2EE; --slate:#46506B; --mist:#F4F6FA;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--mist);color:var(--navy-2);
       font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .wrap{max-width:640px;margin:0 auto;padding:0 18px 64px}
  header{background:var(--navy);color:#fff;padding:28px 0 26px;margin-bottom:-28px}
  header .wrap{padding-bottom:0}
  .eyebrow{font-size:11px;letter-spacing:.16em;text-transform:uppercase;
           color:rgba(255,255,255,.66);margin:0 0 8px;font-weight:700}
  h1{font-size:24px;line-height:1.25;margin:0 0 6px}
  .ref{font-size:13px;color:rgba(255,255,255,.72);margin:0}
  .card{background:#fff;border:1px solid var(--line);border-radius:12px;
        padding:22px;margin-top:44px}
  .bar{height:8px;background:var(--mist);border-radius:99px;overflow:hidden;margin:14px 0 8px}
  .bar>span{display:block;height:100%;background:var(--coral);border-radius:99px}
  .bar-label{font-size:13px;color:var(--slate);margin:0 0 4px}
  .done{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;
        border-radius:10px;padding:14px 16px;margin:0 0 18px;font-size:14.5px}
  .saved{background:#EFF6FF;border:1px solid #BFDBFE;color:#1E40AF;
         border-radius:10px;padding:14px 16px;margin:0 0 18px;font-size:14.5px}
  .errors{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;
          border-radius:10px;padding:14px 16px;margin:0 0 18px;font-size:14.5px}
  .errors ul{margin:8px 0 0;padding-left:18px}
  fieldset{border:0;padding:0;margin:0 0 26px}
  legend{font-size:12px;letter-spacing:.12em;text-transform:uppercase;
         color:var(--slate);font-weight:700;padding:0;margin:0 0 4px}
  .hint{font-size:13.5px;color:var(--slate);margin:0 0 14px}
  label{display:block;font-size:14px;font-weight:600;margin:0 0 6px}
  input,select{width:100%;padding:13px 14px;border:1px solid #C9D2DE;border-radius:8px;
               font-size:16px;background:#fff;color:var(--navy-2);margin:0 0 16px}
  input:focus,select:focus{outline:none;border-color:var(--navy);
                           box-shadow:0 0 0 3px rgba(10,25,47,.08)}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
  button{width:100%;background:var(--coral);color:var(--navy);border:0;border-radius:8px;
         padding:16px;font-size:16px;font-weight:700;cursor:pointer}
  button:hover{filter:brightness(.95)}
  .foot{text-align:center;font-size:13px;color:var(--slate);margin-top:22px}
  .foot a{color:var(--slate)}
  @media(max-width:520px){ .row{grid-template-columns:1fr} }
</style>
</head>
<body>

<header>
  <div class="wrap">
    <p class="eyebrow">Katlehong Computer School</p>
    <h1>Finish your registration, {{ $learner->preferred_name ?: $learner->first_name }}</h1>
    <p class="ref">Student reference {{ $learner->learner_ref }}</p>
  </div>
</header>

<div class="wrap">
  <div class="card">

    @if (session('saved'))
      <p class="saved">{{ session('saved') }}</p>
    @endif

    @if ($errors->any())
      <div class="errors">
        <strong>Please check these:</strong>
        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    @if ($complete)
      <p class="done"><strong>Everything is in.</strong> Thank you — there is nothing else we need
      from you right now. You can still change anything below.</p>
    @else
      <p class="bar-label">{{ $percent }}% complete · {{ count($missing) }} still to go</p>
      <div class="bar"><span style="width:{{ max($percent, 4) }}%"></span></div>
      <p class="hint">Fill in what you can now and come back to the rest — everything saves as
      you go, and nothing you have already entered is lost.</p>
    @endif

    <form method="POST" action="{{ $action }}">
      @csrf

      <fieldset>
        <legend>Your identification</legend>
        @if ($learner->id_number_masked)
          <p class="hint">We have <strong>{{ $learner->id_number_masked }}</strong> on file.
          To correct it, WhatsApp us — we will not change an ID from this page.</p>
        @else
          <p class="hint">We need this to issue your certificate in your correct legal name.</p>
          <div class="row">
            <div>
              <label for="id_type">Document type</label>
              <select id="id_type" name="id_type">
                @foreach ($idTypes as $t)
                  <option value="{{ $t->value }}" @selected(old('id_type', 'sa_id') === $t->value)>{{ $t->label() }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label for="id_number">Number</label>
              <input type="text" id="id_number" name="id_number" inputmode="numeric"
                     value="{{ old('id_number') }}" autocomplete="off">
            </div>
          </div>
        @endif
      </fieldset>

      <fieldset>
        <legend>About you</legend>
        <label for="date_of_birth">Date of birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth"
               value="{{ old('date_of_birth', $learner->date_of_birth?->format('Y-m-d')) }}">

        <div class="row">
          <div>
            <label for="phone">Mobile number</label>
            <input type="tel" id="phone" name="phone" inputmode="tel"
                   value="{{ old('phone', $learner->phone) }}" placeholder="073 000 0000">
          </div>
          <div>
            <label for="email">Email address</label>
            <input type="email" id="email" name="email"
                   value="{{ old('email', $learner->email) }}" placeholder="you@example.com">
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Where you live</legend>
        <label for="address_line">Street address</label>
        <input type="text" id="address_line" name="address_line"
               value="{{ old('address_line', $learner->address_line) }}">

        <div class="row">
          <div>
            <label for="suburb">Suburb</label>
            <input type="text" id="suburb" name="suburb"
                   value="{{ old('suburb', $learner->suburb) }}">
          </div>
          <div>
            <label for="city">Town or city</label>
            <input type="text" id="city" name="city"
                   value="{{ old('city', $learner->city) }}">
          </div>
        </div>

        <div class="row">
          <div>
            <label for="province">Province</label>
            <select id="province" name="province">
              <option value="">Choose…</option>
              @foreach ($provinces as $p)
                <option value="{{ $p }}" @selected(old('province', $learner->province) === $p)>{{ $p }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label for="postal_code">Postal code</label>
            <input type="text" id="postal_code" name="postal_code" inputmode="numeric"
                   value="{{ old('postal_code', $learner->postal_code) }}">
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Schooling and work</legend>
        <label for="highest_qualification">Highest qualification</label>
        <select id="highest_qualification" name="highest_qualification">
          <option value="">Choose…</option>
          @foreach ($qualifications as $q)
            <option value="{{ $q }}" @selected(old('highest_qualification', $learner->highest_qualification) === $q)>{{ $q }}</option>
          @endforeach
        </select>

        <label for="school_or_institution">School or institution</label>
        <input type="text" id="school_or_institution" name="school_or_institution"
               value="{{ old('school_or_institution', $learner->school_or_institution) }}">

        <label for="employment_status">Are you working at the moment?</label>
        <select id="employment_status" name="employment_status">
          <option value="">Choose…</option>
          @foreach ($employment as $e)
            <option value="{{ $e }}" @selected(old('employment_status', $learner->employment_status) === $e)>{{ $e }}</option>
          @endforeach
        </select>
      </fieldset>

      <button type="submit">Save my details</button>
    </form>
  </div>

  <p class="foot">
    Something wrong? WhatsApp us on <a href="https://wa.me/27766845222">076 684 5222</a>
    or email <a href="mailto:info@kcs.edu.za">info@kcs.edu.za</a>.
  </p>
</div>

</body>
</html>
