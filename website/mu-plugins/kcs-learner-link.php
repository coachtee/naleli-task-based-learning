<?php
/**
 * Plugin Name: KCS Learner Link Rewrite
 * Description: Serves kcs.edu.za/my/... from the backend at /admin/my/..., so the private link a learner is sent does not read like a staff area. Must-use.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) { exit; }

/**
 * The application is served from public_html/admin, so every learner-facing
 * URL it generates carries /admin in it. A link that looks like an admin panel
 * is a link people do not click, and these are sent over WhatsApp to somebody
 * who has just paid.
 *
 * WordPress owns the domain root, so it forwards /my/... to the application
 * and keeps the query string — the expiry and signature travel with it, or the
 * link stops verifying.
 */
add_action('template_redirect', function () {
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    if (strpos($path, '/my/') !== 0) {
        return;
    }

    $query = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    $target = 'https://www.kcs.edu.za/admin' . $path . ($query !== '' ? '?' . $query : '');

    wp_redirect($target, 302);
    exit;
}, 0);
