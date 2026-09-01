<?php
/**
 * Plugin Name: KCS Registration Embed
 * Description: Renders the live registration form (form 15) anywhere on the site, with the programme pre-selected from the page it is embedded on. Must-use so it cannot be deactivated by accident.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) { exit; }

const KCS_REGISTER_FORM_ID = 15;

/**
 * The programme options exactly as the live form offers them.
 *
 * Read from the form itself rather than kept as a second list here, so a
 * programme renamed in the Fluent Forms editor can never silently stop
 * pre-selecting on its own page.
 */
function kcs_registration_options(): array {
    static $options = null;
    if ($options !== null) { return $options; }

    global $wpdb;
    $options = [];
    $json = $wpdb->get_var($wpdb->prepare(
        "SELECT form_fields FROM {$wpdb->prefix}fluentform_forms WHERE id = %d",
        KCS_REGISTER_FORM_ID
    ));

    $walk = function ($els) use (&$walk, &$options) {
        foreach ((array) $els as $el) {
            if (($el['attributes']['name'] ?? null) === 'programme') {
                foreach ((array) ($el['settings']['advanced_options'] ?? []) as $o) {
                    if (!empty($o['value'])) { $options[] = (string) $o['value']; }
                }
            }
            foreach (['fields', 'columns'] as $k) {
                if (!empty($el[$k])) { $walk($el[$k]); }
            }
        }
    };
    $walk(json_decode((string) $json, true)['fields'] ?? []);

    return $options;
}

/** The catalogue name for a page, or null when the page is not one programme. */
function kcs_registration_programme_for_page($page_id): ?string {
    $title = trim(html_entity_decode(get_the_title($page_id), ENT_QUOTES, 'UTF-8'));

    return in_array($title, kcs_registration_options(), true) ? $title : null;
}

/**
 * Pre-fill the form server-side.
 *
 * Fluent Forms builds each field through this filter, so setting the default
 * here means the right programme is already chosen in the HTML that arrives.
 * Doing it in JavaScript would leave the choice blank for anyone whose script
 * did not run, on the one field the whole registration depends on.
 */
add_filter('fluentform/rendering_field_data_select', function ($data, $form) {
    if ((int) $form->id !== KCS_REGISTER_FORM_ID) { return $data; }
    $ctx = $GLOBALS['kcs_registration_context'] ?? null;
    if (($data['attributes']['name'] ?? null) === 'programme' && !empty($ctx['programme'])) {
        $data['attributes']['value'] = $ctx['programme'];
    }
    return $data;
}, 10, 2);

add_filter('fluentform/rendering_field_data_input_hidden', function ($data, $form) {
    if ((int) $form->id !== KCS_REGISTER_FORM_ID) { return $data; }
    $ctx = $GLOBALS['kcs_registration_context'] ?? null;
    if (($data['attributes']['name'] ?? null) === 'campaign' && !empty($ctx['campaign'])) {
        $data['attributes']['value'] = $ctx['campaign'];
    }
    return $data;
}, 10, 2);

/**
 * The registration section, ready to drop into any template.
 *
 * Echoes rather than returns, because that is how the KCS templates are
 * written and a section that has to be assigned to a variable first invites
 * the shortcode-that-never-renders problem this replaces.
 */
function kcs_registration_section(?string $programme = null, ?string $campaign = null, ?string $heading = null): void {
    kcs_registration_styles();

    $GLOBALS['kcs_registration_context'] = [
        'programme' => $programme,
        'campaign'  => $campaign ?: 'website',
    ];

    $form = do_shortcode('[fluentform id="' . KCS_REGISTER_FORM_ID . '"]');

    unset($GLOBALS['kcs_registration_context']);
    ?>
    <section class="kcs-cta-band" id="register-interest">
      <div class="kcs-container">
        <h2 class="kcs-h2"><?php echo esc_html($heading ?: 'Register your interest'); ?></h2>
        <?php if ($programme !== null): ?>
          <p>You are registering for <strong><?php echo esc_html($programme); ?></strong>. R500 once-off registration, then R950 per month. Change the programme below if you meant a different one.</p>
        <?php else: ?>
          <p>Tell us which programme you want and we will be in touch on WhatsApp. R500 once-off registration, then R950 per month.</p>
        <?php endif; ?>
        <div class="kcs-regform"><?php echo $form; ?></div>
      </div>
    </section>
    <?php
}

/**
 * Just the form, for a place that already provides its own framing.
 *
 * The hero card has its own heading, price line and styling, so it wants the
 * fields alone rather than the whole section.
 */
function kcs_registration_form(?string $campaign = null, ?string $programme = null): void {
    $GLOBALS['kcs_registration_context'] = [
        'programme' => $programme,
        'campaign'  => $campaign ?: 'website',
    ];

    echo do_shortcode('[fluentform id="' . KCS_REGISTER_FORM_ID . '"]');

    unset($GLOBALS['kcs_registration_context']);
}

/** For any page that does render post content. */
add_shortcode('kcs_register', function ($atts) {
    $atts = shortcode_atts(['programme' => '', 'campaign' => ''], $atts);
    ob_start();
    kcs_registration_section($atts['programme'] ?: null, $atts['campaign'] ?: null);
    return ob_get_clean();
});

/**
 * The form's own styling, printed once wherever it is embedded.
 *
 * These rules used to live in an inline <style> at the bottom of homepage.php,
 * which meant the form rendered unstyled on every programme page: dark labels
 * directly on the navy CTA band, effectively unreadable. Styling travels with
 * the component instead.
 */
function kcs_registration_styles(): void {
    static $printed = false;
    if ($printed) { return; }
    $printed = true;
    ?>
    <style id="kcs-regform-css">
    .kcs-regform{max-width:560px;margin:24px auto 0;text-align:left;background:#fff;padding:28px;border-radius:4px}
    .kcs-regform label{color:#12203D;font-weight:600;font-size:14px}
    .kcs-regform input[type=text],.kcs-regform input[type=email],.kcs-regform input[type=tel],.kcs-regform select{width:100%;padding:12px 14px;border:1px solid #C9D2DE;border-radius:4px;font-size:15px;background:#fff;color:#12203D}
    .kcs-regform select{appearance:auto}
    .kcs-regform .ff-btn-submit{background:#FF7A59!important;color:#0A192F!important;border:0!important;border-radius:4px!important;padding:14px 28px!important;font-weight:700!important;font-size:15px!important;box-shadow:none!important;width:100%}
    .kcs-regform .ff-el-input--label label{margin-bottom:6px;display:block}
    .kcs-regform .ff-el-group{margin-bottom:18px}
    .kcs-regform .ff-el-form-check label{font-weight:400;font-size:14px}
    .kcs-regform .ff-message-success{color:#12203D}
    .kcs-regform .text-danger,.kcs-regform .error{color:#B3261E}
    </style>
    <?php
}
