<x-mail::message>
# Thank you, {{ $learner->preferred_name ?: $learner->first_name }}

We have your registration and it is on our system. Nothing else is needed from
you right now.

**Your student reference is {{ $learner->learner_ref }}.** Keep it — every
message from us will carry it, and it is how we find you if you phone.

@if ($programme)
You registered for **{{ $programme->name }}**.
@else
Our team will confirm which programme you are registering for.
@endif

## What happens next

1. We check your registration and issue your payment reference — usually the
   same working day.
2. You pay **R500** once-off registration at any Pay@ till: Shoprite, Checkers,
   Pick n Pay, Boxer or USave. Then R950 a month while you study.
3. Your place is confirmed as soon as that payment reaches us, and we send you
   your access details straight after.

We send your payment reference to this email address and to your WhatsApp, so
you can pay whichever way suits you.

<x-mail::panel>
**Not expecting this?** Someone may have entered your address by mistake.
Reply to this email and we will remove it.
</x-mail::panel>

Any questions, WhatsApp us on 076 684 5222 or reply here.

Thanks,<br>
Katlehong Computer School
</x-mail::message>
