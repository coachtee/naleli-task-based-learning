{{--
  The shared skin for the two pages a learner reaches from a link we sent
  them. Extracted so the PIN pages cannot drift from the profile page — two
  hand-styled pages from the same institution that do not match is the thing
  that makes a school look improvised.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $title }} — Katlehong Computer School</title>
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
  .card + .card{margin-top:16px}
  h2{font-size:17px;margin:0 0 10px}
  p{margin:0 0 14px}
  p:last-child{margin-bottom:0}
  .done{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;
        border-radius:10px;padding:14px 16px;margin:0 0 18px;font-size:14.5px}
  .errors{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;
          border-radius:10px;padding:14px 16px;margin:0 0 18px;font-size:14.5px}
  .errors ul{margin:8px 0 0;padding-left:18px}
  .hint{font-size:13.5px;color:var(--slate)}
  label{display:block;font-size:14px;font-weight:600;margin:0 0 6px}
  input{width:100%;padding:13px 14px;border:1px solid #C9D2DE;border-radius:8px;
        font-size:16px;background:#fff;color:var(--navy-2);margin:0 0 16px}
  input:focus{outline:none;border-color:var(--navy);box-shadow:0 0 0 3px rgba(10,25,47,.08)}
  input.pin{letter-spacing:.5em;text-align:center;font-size:22px;padding:15px 14px}
  button{width:100%;background:var(--coral);color:var(--navy);border:0;border-radius:8px;
         padding:16px;font-size:16px;font-weight:700;cursor:pointer}
  button:hover{filter:brightness(.95)}
  .cta{display:block;text-align:center;background:var(--navy);color:#fff;
       border-radius:8px;padding:16px;font-size:16px;font-weight:700;text-decoration:none}
  .cta:hover{background:var(--navy-2)}
  .id{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:19px;
      font-weight:700;letter-spacing:.04em;background:var(--mist);border:1px solid var(--line);
      border-radius:8px;padding:12px 14px;text-align:center;margin:0 0 6px}
  ol{margin:0;padding-left:20px}
  ol li{margin-bottom:8px}
  .foot{text-align:center;font-size:13px;color:var(--slate);margin-top:22px}
  .foot a{color:var(--slate)}
</style>
</head>
<body>

<header>
  <div class="wrap">
    <p class="eyebrow">Katlehong Computer School</p>
    <h1>{{ $heading }}</h1>
    <p class="ref">Student number {{ $learner->learner_ref }}</p>
  </div>
</header>

<div class="wrap">
  {{ $slot }}
  <p class="foot">Stuck? Reply to the email we sent you, or ask at reception.</p>
</div>

</body>
</html>
