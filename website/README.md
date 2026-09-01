# The kcs.edu.za side

The WordPress files this project owns. They live on the server under
`public_html/wp-content/`, and are kept here so a host rebuild does not lose
them and so changes are reviewable.

| File | Where it goes on the server |
| --- | --- |
| `mu-plugins/kcs-registration-bridge.php` | `wp-content/mu-plugins/` |
| `mu-plugins/kcs-registration-embed.php` | `wp-content/mu-plugins/` |

Must-use plugins, deliberately: they load automatically and cannot be
deactivated from the admin screen by someone tidying up the plugin list.

## How a registration reaches the backend

```
programme page  ──►  Fluent Forms 15  ──►  bridge (HMAC-signed)  ──►  /admin/api/v1/intake/application
```

`kcs-registration-embed.php` renders the form; `kcs-registration-bridge.php`
forwards the submission. The signing secret is read from the backend's own
`.env`, so rotating `KCS_FLUENTFORM_SECRET` rotates both sides at once.

## The thing to know before editing a page

**kcs.edu.za does not render page content.** `wp-content/novamira-sandbox/kcs-transform.php`
takes over `template_include` and renders PHP templates from
`novamira-sandbox/kcs-templates/` instead — mapped by page ID for the home,
about and contact pages, and by a `_kcs_template` postmeta value (`pathway`,
`qualification`, …) for the rest.

Every one of the 13 programme pages therefore has an **empty** `post_content`.
A shortcode pasted into the editor is never output, and no error is raised —
it simply does not appear. That is why the registration form was missing from
the site for so long: the home page carried `[fluentform id="13"]` for a form
that had since been unpublished, and nothing on any programme page at all.

So: to put something on a page, edit its template and call the function
(`kcs_registration_section()`), rather than adding a shortcode to the page.
The `[kcs_register]` shortcode exists for the few pages that do render post
content.

## Styling travels with the form

`kcs_registration_section()` prints its own CSS, once per request. Those rules
previously lived in an inline `<style>` at the bottom of `homepage.php`, which
was fine while the form only appeared there — and would have rendered it
unstyled on all 13 programme pages, dark labels directly on the navy CTA band.

## Where the form appears

| Place | Programme | Campaign recorded |
| --- | --- | --- |
| Home hero card | none — visitor chooses | `hero` |
| Home CTA band | none | `homepage` |
| Each of the 13 programme pages | that page's programme | `pathway:<slug>` |
| Qualification overview | none — it covers seven modules | `qualification:<slug>` |
| `/application/` | none | `website` |

Two instances of one form on the home page is supported: Fluent Forms scopes
its JavaScript by the `ff_form_instance_15_1` / `_2` classes it generates for
exactly this case. It does emit a duplicate `id="fluentform_15"` on both,
which is invalid HTML but is the plugin's own markup, not ours.

## One name for one action

The site had two labels for the same thing pointing at two different places:
"Apply Now" → `/application/` and "Register Interest" → an in-page anchor.
Everything is now **Register Now**, `kcs-btn--primary`, and lands on a working
form: the in-page one where the page has it, `/application/` where it does not.

## Not every button is in a template

The most prominent button on the site — the one in the header, on every page —
is **not** in any file. Astra stores it in the `astra-settings` option:

| Key | Holds |
| --- | --- |
| `header-button1-text` | the label |
| `header-button1-link-option` | `{"url": …}` |

It read "My Campus" and pointed at the old LMS long after the templates had
moved on, and no amount of grepping the theme files would have found it. If a
label on the live site does not match anything in `novamira-sandbox/`, look in
`astra-settings` before assuming a caching problem.

`header-account-logout-link` still points at campus.kcs.edu.za on purpose:
it is where a logged-in learner gets sent, not a marketing link.

## Pre-selecting the programme

`kcs_registration_section($programme)` sets the choice server-side through
Fluent Forms' `fluentform/rendering_field_data_select` filter, so the right
programme is already chosen in the HTML that arrives. It is deliberately not
done in JavaScript: the one field the whole registration depends on should not
be blank for anyone whose script did not run.

The valid programme names are read from the live form rather than duplicated
here, so a programme renamed in the Fluent Forms editor cannot silently stop
pre-selecting on its own page. A page whose title is not one of them (the
qualification overview, for instance) renders the form with nothing chosen.

`campaign` is set to the page that converted — `pathway:ict-systems-administration`,
`homepage` — and lands on the application record in the backend.
