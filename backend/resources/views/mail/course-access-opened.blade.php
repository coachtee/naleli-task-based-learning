<x-mail::message>
# You're in, {{ $learner->preferred_name ?: $learner->first_name }}

Your payment has reached us and your place is confirmed.
@if ($programme)
You are registered for **{{ $programme->name }}**.
@endif

**Your student number is {{ $learner->learner_ref }}.** You will type it every
time you sign in, so keep it somewhere you will find it.

## One thing to do first

Choose the PIN you will use to sign in. It takes a minute, and the link below
only works for you.

<x-mail::button :url="$link">
Choose my PIN
</x-mail::button>

Once you have set it, sign in at **{{ $workspace }}** on any computer at KCS —
or on your phone at home. Your work is saved to your student number, not to the
machine, so you can start on one and carry on at another.

This link lasts {{ \App\Services\Registration\LearnerLinks::ACCESS_DAYS }} days.
If it has expired by the time you open it, reply to this email and we will send
a new one.

@if ($appToken)
## On your phone

If you are using the Naleli app on Android, activate it once with this code:

<x-mail::panel>
**{{ $appToken }}**
</x-mail::panel>

You only need it the first time. The app remembers your phone after that.
@endif

<x-mail::panel>
**Keep your PIN to yourself.** The computers at KCS are shared, and anyone with
your student number and PIN can open your work and hand it in as their own. If
you think somebody has seen it, ask us for a new one.
</x-mail::panel>

Welcome to Katlehong Computer School.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
