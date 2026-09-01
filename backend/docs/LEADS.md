# Facebook leads → students

An ad that cost R191 produced 85 names. One of them registering pays for the
campaign seventeen times over. The thing that loses them is never the ad and
never the price — it is nobody remembering who is still owed a call.

## The funnel

```
Meta Lead Ad (instant form)
  → download CSV from the Leads Center
  → import here                          status: LEAD
  → somebody calls them                  status: CONTACTED
  → they fill in the website form        status: REGISTRATION_STARTED
  → accept → Pay@ → pay                  status: PAID
  → "your course is open" email          → workspace
```

Everything from the website form onward already existed and is tested end to
end. This document covers the first two steps.

## Importing

**From the dashboard.** *Import Facebook leads* on the call queue. Download the
CSV from Meta's Leads Center on your phone, upload it, done. Importing the same
file twice is safe.

**From the command line**, if you prefer:

```bash
php artisan leads:import storage/app/leads-august.csv --campaign="Aug 2026"
```

### They come in as leads, not registrations

Somebody who tapped an instant form asked what the course costs. They did not
register. Filing them as registrations would mean the dashboard says you have
85 registrations when you have 85 enquiries, and you could never again answer
"how many actually registered this month".

So: `status = LEAD`, and the pipeline counts **Leads to call** separately from
**New registrations**. Creating an application writes no invoice, so an import
never bills anybody.

### Each lead becomes a real learner

With a real `learner_ref`, which does consume references on people who may
never enrol. That is the deliberate trade: when one of them fills in the
website form six weeks later, `LearnerRegistry` matches them on email or phone
and it is the **same person**, with the Facebook lead still attached. You get
the whole funnel on one record. Recognising a returning prospect is worth more
than a tidy numbering sequence.

### The file

Meta's columns move around — a custom question becomes its own column, Excel
adds a byte-order mark, phone numbers arrive as `p:+27821234567`. The importer
maps headers loosely and needs only a name plus an email or a phone. A file
with neither is refused with the columns it did find, rather than importing
nothing and saying it worked.

Leads with no programme are filed under the **Foundation**, because the
catalogue says that is where every learner starts and somebody who has not
spoken to anybody has not chosen a specialisation either. `--programme`
overrides.

## The call queue

The first widget on the dashboard, ordered by how long each person has been
waiting. Per lead: **WhatsApp** (opens with the message written), **Log a
call**, and **Register them** once they say they will.

### Why the outcome sets the next date

| Outcome | Back in |
|---|---|
| No answer / left a message | 2 days |
| Spoke to them / sent the info | 3 days |
| Says they will register | 2 days |
| Interested, not this intake | 6 weeks |
| Not interested / wrong number | never — leaves the queue |

A closed lead leaving the queue matters as much as an open one staying in it.
A list that is 40% dead entries stops being read.

### Never bulk WhatsApp

The queue opens one chat at a time, from your own number, with the message
pre-written. **Do not send bulk WhatsApp from the school's number** — Meta bans
numbers for it, usually permanently, and the school runs on that number. True
bulk messaging is the WhatsApp Business API with approved templates and a
per-conversation cost; not worth it at this scale, and a personally-sent
message converts better anyway.

### Email: send campaigns through Brevo, not from here

Transactional mail — the registration confirmation, and the "your course is
open" message carrying the PIN link — sends from `kcs.edu.za`. A bulk send from
the same domain that collects spam complaints gets **all** mail from that
domain filtered, and then a student who has paid never receives their login.
Send campaigns from a marketing subdomain in Brevo, which also owns the
unsubscribe list.

## POPIA

These are Meta Instant Form submissions: the person typed their details asking
about KCS courses, which is consent for direct marketing about those courses.
The import records where each came from (`source = meta_lead`) and which
campaign, and every touch is logged. Honour unsubscribes, and do not import
lists that were bought or scraped — a different provenance is a different legal
question.
