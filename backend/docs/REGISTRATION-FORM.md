# The registration form

**Fluent Forms id 15, "KCS Registration 2027"** — the single public entry point.
Built 30 August 2026, replacing five forms.

## Fields

| Field | Name | Required | Notes |
|---|---|---|---|
| Your name | `names.first_name`, `names.last_name` | yes | Split, not one box — `LearnerRegistry` stores them separately and a certificate needs them apart |
| WhatsApp Number | `whatsapp` | yes | How the registration reference is sent |
| Email | `email` | yes | |
| Which programme are you registering for? | `programme` | yes | The 13 blocks, plus "Not sure yet" |
| How will your studies be paid for? | `funding_source` | yes | Matches the `FundingSource` enum exactly |
| Campaign | `campaign` | hidden | Defaults to `website`; set per landing page or Facebook lead |
| POPIA consent | `consent` | yes | |

That is the whole of Stage A. Identity, address, education, employment and
documents are collected **after payment**, through the learner's own secure link —
`ProfileCompleteness` measures what is still owed.

## Why these fields and no more

Every field before payment costs conversion. The backend needs a name, one way to
reach the person, and which programme — nothing else is required to create a
learner, raise the invoices and take money. Identity is required before a token is
issued, not before payment, so it is not asked here.

## Programme values

The dropdown submits the programme's **exact name**, which
`config/webhooks.php` maps to a code. `CatalogueDriftTest` fails if the two ever
disagree, so renaming a programme in the manifest without updating the form is
caught by the suite rather than by an unmapped application.

## Retired forms

Unpublished, never deleted — their submissions and entry views survive, and a
JSON backup of all 58 sits in `wp-content/uploads/kcs-backups/` (directory denied
to the public).

| Form | Submissions | Why |
|---|---|---|
| 2 — Subscription Form | 0 | unused |
| 8 — Student Application Form 2026 | 35 | sold a catalogue that no longer exists |
| 12 — Booking Excel 2026 | 15 | Basic Excel is not in the catalogue |
| 13 — Register Your Interest | 3 | superseded by form 15 |
| 14 — Hero Quick Enquiry | 0 | unused |

**Kept:** form 1, Contact Us. It is a contact form, not a registration form, and it
is embedded on the Contact page.

## Perfex

The `fluentform/submission_inserted` bridge that posted every application to
`kcs.edu.za/portal/api/leads` is disabled. Drafting the WPCode snippet was not
enough — WPCode caches active snippets in the `wpcode_snippets` option and evals
from there, so the hook survived until that entry was removed. Verified gone on a
fresh request: the function is undefined and the hook unregistered.

The API token is still stored in the snippet body. Revoke it in Perfex.


## How a registration reaches the backend

A must-use plugin on the website, `wp-content/mu-plugins/kcs-registration-bridge.php`,
hooks `fluentform/submission_inserted` for form 15 and posts to

```
POST https://www.kcs.edu.za/admin/api/v1/intake/application
X-KCS-Signature: sha256=<hmac-sha256 of the raw body>
```

It is a must-use plugin rather than a WPCode snippet on purpose. The Perfex
bridge it replaces was a WPCode snippet, and drafting that snippet did not stop
it running — WPCode caches active snippets in an option and evals from there.
A must-use plugin cannot be deactivated by accident.

The signing secret is read from `/home/kcseduza/kcs-backend/.env` at request
time, so there is exactly one copy. The Perfex bridge had its API token pasted
into the snippet body, which left a live credential sitting in the WordPress
database in plain text.

A delivery that fails is appended to
`wp-content/uploads/kcs-backups/registration-bridge.log` with the entry id.
Nothing is lost when that happens: the submission is already stored in Fluent
Forms, and `inbound_webhooks` records every delivery the backend accepted
before it interprets it.

### Where the form appears

`kcs.edu.za/application/` — the one registration page. The home page's
"Register Interest" button links to it.

The form was briefly placed on the home page too, but the front-page template
does not expand shortcodes: the anchor and heading rendered while the form did
not, though the identical content renders correctly server-side. One
destination is better than two anyway.

### Verified end to end on 30 August 2026

A submission through the bridge produced application 1, learner
`NAL-2026-00001`, programme `ICT` resolved from the name "ICT Systems
Administration", `funding_source=funding_application`,
`funding_status=pending`, `campaign` recorded, and the WhatsApp number
normalised to `+27735550101`. The verification records were then deleted and
the reference counter reset to 1, so the first real registration takes
NAL-2026-00001.
