# Pay@ Go — cash at a till, verified before it counts

Pay@ Go is the first live payment gateway wired into this backend, and it is
first on purpose. A KCS learner without a bank card, without data and without
a smartphone can still walk into a Shoprite, Checkers, Pick n Pay or Boxer,
quote a number and pay cash. Card gateways serve the learners who already had
options; this one serves the learners who did not.

## What it is, technically

The YAPI **Merchant Request-to-Pay (RTP)** API. An RTP is a payable
reference: we create one, the learner pays against it, Pay@ tells us.

- Base: `https://go.payat.co.za/yapi/v1`
- Token: `https://go.payat.co.za/yapi/oauth/token`
- Specification: <https://go.payat.co.za/yapi/swagger-ui/index.html?urls.primaryName=merchant>

Two facts about the OAuth endpoint cost an afternoon each if you get them
wrong, and both were established against the live account rather than read out
of the specification:

1. **Credentials go in an HTTP Basic header.** The same credentials in the
   POST body — also legal OAuth2 — return `invalid_client`.
2. **Scopes must be requested explicitly.** A token minted with no `scope`
   parameter is issued happily and then 403s on every call that uses it.

Money is integer cents on both sides of the boundary, so nothing is converted
anywhere in this integration.

## The security property that matters

**Pay@ does not sign its callback.** The notification body observed in
production is:

```json
{"requestToPayId":"…","sourceReference":"…","amount":50000,
 "status":"PAID","customerNameSurname":"…"}
```

Anyone who found the URL could post that and activate an enrolment for free.

So the callback is treated as a nudge and nothing more. `PayAtGoController`
takes exactly one thing from the body — the identity of a reference — and then
decides everything else by reading that reference back from Pay@ over an
authenticated connection (`GET /merchant/rtp/read/{clientAccountNumber}`). A
forged callback therefore settles nothing; at worst it costs one API call,
which is why the route is throttled.

Settlement requires **both halves to agree**: Pay@ must report a settled
account state *and* `amountPaid` must cover the invoice. See
`App\Enums\PayAtAccountState` — only `PAYMENT_COMPLETED`,
`PAYMENT_READY_FOR_SETTLEMENT` and `SETTLEMENT_PROCESSED` count.
`PAYMENT_FEES_ISSUE` deliberately does not: money exists but Pay@ has flagged
something about it, so it is held short of settlement for a registrar to look
at rather than activating on a state we have never seen in production.

## Reference numbers

`clientAccountNumber` is **ours to allocate** — up to 14 digits, unique on the
merchant account forever. The scheme is a fixed prefix plus the invoice id
(`9` + 9 digits by default), which makes it reproducible from the invoice
alone and impossible to confuse with the learner mobile numbers used as
references before this backend existed.

One RTP per **invoice**, not per enrolment. `EnrolmentActivator` keys
activation off a specific invoice settling, so a reference that covered
several invoices at once would make attribution ambiguous the moment somebody
part-paid. A career module therefore issues four references: R500
registration (the one that opens access) and three of R950.

Part payment is allowed at the till — turning a learner away because they are
short is worse than carrying a part-paid invoice — and is recorded as a
`pending` payment against the same reference. When the balance arrives, the
same row settles.

## Four constraints the specification does not tell you

All four were found by walking the flow against the live merchant account, and
each one had already produced a broken reference before it was understood.

1. **`clientAccountNumber` is unique forever, including after cancellation.**
   Pay@ will not reuse a number, so a cancelled reference is permanently dead
   and its invoice needs a new one. That is what the one-digit attempt on the
   end of our account numbers is for.
2. **`clientReferenceNumber` is unique too**, per business —
   *"Reference number NAL-2026-00002-1 already exists for this business"*. A
   re-issue carries the attempt here as well (`NAL-2026-00002-1r1`) or Pay@
   refuses it for colliding with the dead reference it replaces.
3. **A failed create followed by a successful read does not mean "adopt it".**
   The read returns cancelled and expired references quite happily. Adopting
   one stores a number no till will ever accept and shows it to a registrar as
   payable, so adoption now takes an open reference and refuses a closed one.
4. **`daysValid` must not be derived from the due date.** The registration fee
   is due the day it is raised, which minted a reference expiring at midnight
   that same day. The configured window is a floor, not a default.

One quirk worth knowing: `create` returns `sourceReference` (the number quoted
at the till) but `read` does not, so the value is stored when the reference is
minted and never overwritten by a later read.

## Operating it

**Issue a reference.** Money → Invoices → *Create Pay@ reference*. Deliberate,
never automatic: this is the school asking for money. The row then shows the
payable reference, copyable, linked to its QR page for WhatsApp.

**Check a reference.** *Check Pay@* on any unpaid invoice reads it back and
settles it if Pay@ says it is paid.

**The sweep.** One cron entry makes a lost webhook a non-event:

```
*/15 * * * * php /home/kcseduza/kcs-backend/artisan payat:reconcile
```

It checks every outstanding reference and settles the ones that have been
paid, whether or not a callback ever arrived.

## Configuration

`config/payat.php`, from `.env`:

| Key | Meaning |
| --- | --- |
| `PAYAT_CLIENT_ID` / `PAYAT_CLIENT_SECRET` | OAuth2 client credentials |
| `PAYAT_MERCHANT_DISPLAY_NAME` | printed on the learner's slip |
| `PAYAT_ACCOUNT_PREFIX` | first digit(s) of our account numbers |
| `PAYAT_DAYS_VALID` | how long a reference stays payable (Pay@ allows 1–120) |
| `PAYAT_MINIMUM_AMOUNT_CENTS` | smallest part payment a till will take |

**With no credentials configured nothing breaks.** `createCheckout()` returns
null, the dashboard hides the button, and payment falls back to a registrar
recording it by hand. That is the same fallback that covers cash at reception,
and it does not go away.

## The webhook

Point the Pay@ merchant portal's notification URL at:

```
https://www.kcs.edu.za/admin/api/v1/payments/payat
```

The WordPress endpoint it used before — `/wp-json/kcs/v1/payat`, handled by
`wp-content/novamira-sandbox/payat-webhook.php` — logged deliveries and did
nothing else. Leave it in place until the replacement has been proven against
a real payment, then remove it.

## Still to do before this touches a learner

- **Nothing sends the learner their reference.** The website tells them
  "we will send your registration reference and payment options to your
  WhatsApp shortly" and no code does. This is the one thing standing between a
  working flow and a working business — a payable number sitting in the
  dashboard that the learner has never seen cannot be paid.
- Pay one real R500 registration fee at a till, to prove settlement end to end
  against a genuine payment rather than a faked one.
- Two questions outstanding with Pay@: whether a sandbox exists, and whether
  they reserve any `clientAccountNumber` range (our prefix assumes not).
