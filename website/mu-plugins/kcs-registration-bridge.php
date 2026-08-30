<?php
/**
 * Plugin Name: KCS Registration Bridge
 * Description: Posts every submission of the KCS Registration form to the KCS Education backend at /admin. Loaded as a must-use plugin so it cannot be deactivated by accident.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) { exit; }

const KCS_BRIDGE_FORM_ID = 15;
const KCS_BRIDGE_ENDPOINT = 'https://www.kcs.edu.za/admin/api/v1/intake/application';
const KCS_BRIDGE_ENV = '/home/kcseduza/kcs-backend/.env';

/**
 * The signing secret lives in the backend's .env and nowhere else.
 *
 * The Perfex bridge this replaces had its API token pasted into the snippet
 * body, which meant the token sat in the WordPress database in plain text and
 * rotating it meant editing two places. Reading it here keeps one copy.
 */
function kcs_bridge_secret(): string {
    static $secret = null;
    if ($secret !== null) { return $secret; }
    $secret = '';
    if (is_readable(KCS_BRIDGE_ENV)) {
        $env = (string) file_get_contents(KCS_BRIDGE_ENV);
        if (preg_match('/^KCS_FLUENTFORM_SECRET=(.*)$/m', $env, $m)) {
            $secret = trim($m[1], " \t\n\r\0\x0B\"'");
        }
    }
    return $secret;
}

function kcs_bridge_log(string $message, array $context = []): void {
    $dir = WP_CONTENT_DIR . '/uploads/kcs-backups';
    if (!is_dir($dir)) { wp_mkdir_p($dir); }
    $line = wp_json_encode(['at' => current_time('c'), 'message' => $message] + $context) . "\n";
    file_put_contents($dir . '/registration-bridge.log', $line, FILE_APPEND);
}

function kcs_bridge_value($data, $keys, $default = '') {
    foreach ((array) $keys as $key) {
        if (isset($data[$key]) && $data[$key] !== '' && !is_array($data[$key])) {
            return trim((string) $data[$key]);
        }
        if (isset($data[$key]) && is_array($data[$key])) {
            $flat = implode(', ', array_filter($data[$key]));
            if ($flat !== '') { return $flat; }
        }
    }
    return $default;
}

add_action('fluentform/submission_inserted', 'kcs_bridge_send', 20, 3);

function kcs_bridge_send($entryId, $formData, $form): void {
    if ((int) $form->id !== KCS_BRIDGE_FORM_ID) { return; }

    $secret = kcs_bridge_secret();
    if ($secret === '') {
        kcs_bridge_log('No signing secret found; submission not forwarded.', ['entry' => $entryId]);
        return;
    }

    $names = (isset($formData['names']) && is_array($formData['names'])) ? $formData['names'] : [];
    $whatsapp = kcs_bridge_value($formData, ['whatsapp', 'phone']);

    $payload = [
        'source' => 'fluentform',
        'form_id' => KCS_BRIDGE_FORM_ID,
        'submission_id' => (string) $entryId,
        'submitted_at' => current_time('c'),
        'applicant' => [
            'first_name' => trim((string) ($names['first_name'] ?? '')),
            'middle_name' => trim((string) ($names['middle_name'] ?? '')),
            'last_name' => trim((string) ($names['last_name'] ?? '')),
            'email' => kcs_bridge_value($formData, ['email']),
            'phone' => $whatsapp,
            'whatsapp' => $whatsapp,
        ],
        // The form submits the programme's full name; the backend's
        // programme_map turns it into a code, and a drift test keeps the two
        // lists in step.
        'programme_code' => kcs_bridge_value($formData, ['programme']),
        'funding_source' => kcs_bridge_value($formData, ['funding_source']),
        'campaign' => kcs_bridge_value($formData, ['campaign'], 'website'),
    ];

    $body = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $response = wp_remote_post(KCS_BRIDGE_ENDPOINT, [
        'timeout' => 12,
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-KCS-Signature' => 'sha256=' . hash_hmac('sha256', $body, $secret),
        ],
        'body' => $body,
    ]);

    if (is_wp_error($response)) {
        // The submission is already saved in Fluent Forms, so nothing is lost:
        // this records enough for a registrar to replay it.
        kcs_bridge_log('Delivery failed.', ['entry' => $entryId, 'error' => $response->get_error_message()]);
        return;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        kcs_bridge_log('Backend refused the delivery.', [
            'entry' => $entryId,
            'http' => $code,
            'body' => mb_substr((string) wp_remote_retrieve_body($response), 0, 400),
        ]);
    }
}
