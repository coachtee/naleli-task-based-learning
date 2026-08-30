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
