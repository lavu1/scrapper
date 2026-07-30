<?php
/**
 * Plugin Name: Custom Notification Platform
 * Description: Self-hosted browser push and email subscriptions for Zambia Job Alerts.
 * Version: 1.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

const ZJA_NOTIFICATION_SITE = 'zambia-job-alerts';
const ZJA_NOTIFICATION_EVENT = 'zja_send_published_job_notification';

function zja_notification_url(): string
{
    return rtrim(defined('CUSTOM_NOTIFICATIONS_URL') ? CUSTOM_NOTIFICATIONS_URL : 'https://api.alphilnetworks.com/notifications', '/');
}

function zja_notification_key(): string
{
    return (string) (defined('CUSTOM_NOTIFICATIONS_KEY') ? CUSTOM_NOTIFICATIONS_KEY : getenv('NOTIFICATION_PLATFORM_KEY'));
}

add_action('wp_enqueue_scripts', function (): void {
    wp_enqueue_script('zja-custom-notifications', zja_notification_url().'/assets/client.js', [], '1.0.0', ['strategy' => 'defer', 'in_footer' => true]);
});

add_filter('script_loader_tag', function (string $tag, string $handle): string {
    if ($handle !== 'zja-custom-notifications') {
        return $tag;
    }

    return str_replace(' src=', ' data-site="'.esc_attr(ZJA_NOTIFICATION_SITE).'" data-api="'.esc_attr(zja_notification_url()).'" src=', $tag);
}, 10, 2);

add_action('wp_footer', function (): void {
    ?>
    <details class="zja-notification-dock">
        <summary aria-label="Open notification options">&#128276;</summary>
        <div class="zja-notification-panel">
            <h2>Job alerts</h2>
            <p>Choose the jobs you want to hear about.</p>
            <label>Keyword<input type="text" data-notification-filter="keyword" data-email-filter="keyword" placeholder="Nurse, driver, accountant"></label>
            <label>Location<input type="text" data-notification-filter="location" data-email-filter="location" placeholder="Lusaka, Ndola, remote"></label>
            <button type="button" data-enable-notifications data-enabled-label="Browser notifications enabled">Enable browser notifications</button>
            <div data-custom-push-status class="zja-notification-status" aria-live="polite">Your browser will ask for permission once.</div>
            <hr>
            <form data-email-notification-form>
                <label>Email address<input name="email" type="email" required placeholder="you@example.com"></label>
                <label>Frequency<select name="frequency"><option value="immediate">Immediately</option><option value="daily">Daily digest</option><option value="weekly">Weekly digest</option></select></label>
                <button type="submit">Subscribe by email</button>
                <div data-email-notification-status class="zja-notification-status" aria-live="polite"></div>
            </form>
        </div>
    </details>
    <style>
        .zja-notification-dock{position:fixed;right:20px;bottom:20px;z-index:99999}.zja-notification-dock>summary{list-style:none;width:56px;height:56px;display:grid;place-items:center;border-radius:50%;background:#0f766e;color:#fff;font-size:23px;box-shadow:0 14px 32px rgba(15,23,42,.3);cursor:pointer}.zja-notification-dock>summary::-webkit-details-marker{display:none}.zja-notification-panel{position:absolute;right:0;bottom:68px;width:min(360px,calc(100vw - 32px));padding:20px;border:1px solid #dbe3ef;border-radius:12px;background:#fff;color:#0f172a;box-shadow:0 20px 48px rgba(15,23,42,.25);font:14px/1.45 system-ui,sans-serif}.zja-notification-panel h2{margin:0 0 5px;font-size:20px}.zja-notification-panel p{margin:0 0 12px;color:#64748b}.zja-notification-panel label{display:grid;gap:4px;margin:9px 0;font-weight:600}.zja-notification-panel input,.zja-notification-panel select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#0f172a}.zja-notification-panel button{width:100%;margin-top:9px;padding:11px;border:0;border-radius:7px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer}.zja-notification-panel hr{margin:16px 0;border:0;border-top:1px solid #e2e8f0}.zja-notification-status{min-height:18px;margin-top:7px;color:#64748b;font-size:12px}
    </style>
    <?php
}, 50);

add_action('transition_post_status', function (string $newStatus, string $oldStatus, WP_Post $post): void {
    if ($post->post_type !== 'job_listing' || $newStatus !== 'publish' || $oldStatus === 'publish' || wp_is_post_revision($post)) {
        return;
    }

    if (! wp_next_scheduled(ZJA_NOTIFICATION_EVENT, [$post->ID, 1])) {
        wp_schedule_single_event(time() + 5, ZJA_NOTIFICATION_EVENT, [$post->ID, 1]);
    }
}, 10, 3);

add_action(ZJA_NOTIFICATION_EVENT, function (int $postId, int $attempt = 1): void {
    $post = get_post($postId);
    if (! $post || $post->post_type !== 'job_listing' || $post->post_status !== 'publish') {
        return;
    }

    $categories = wp_get_post_terms($postId, 'job_listing_category', ['fields' => 'names']);
    $types = wp_get_post_terms($postId, 'job_listing_type', ['fields' => 'names']);
    $payload = [
        'site' => ZJA_NOTIFICATION_SITE,
        'eventId' => 'zja-job-'.$postId.'-published',
        'channels' => ['push', 'email'],
        'content' => [
            'id' => (string) $postId,
            'type' => 'job',
            'title' => get_the_title($postId),
            'description' => wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 38),
            'url' => get_permalink($postId),
            'imageUrl' => get_the_post_thumbnail_url($postId, 'large') ?: null,
            'company' => get_post_meta($postId, '_company_name', true) ?: null,
            'location' => get_post_meta($postId, '_job_location', true) ?: null,
            'category' => is_wp_error($categories) ? [] : $categories,
            'jobType' => is_wp_error($types) ? [] : $types,
        ],
    ];

    $response = wp_remote_post(zja_notification_url().'/v1/internal/content-published', [
        'timeout' => 12,
        'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-Notification-Key' => zja_notification_key()],
        'body' => wp_json_encode($payload),
    ]);
    $status = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);

    if (! is_wp_error($response) && $status >= 200 && $status < 300) {
        update_post_meta($postId, '_custom_notification_status', 'queued');
        delete_post_meta($postId, '_custom_notification_error');
        return;
    }

    $error = is_wp_error($response) ? $response->get_error_message() : 'HTTP '.$status;
    update_post_meta($postId, '_custom_notification_status', 'failed');
    update_post_meta($postId, '_custom_notification_error', sanitize_text_field($error));
    if ($attempt < 4) {
        $delays = [2 => 60, 3 => 300, 4 => 1800];
        wp_schedule_single_event(time() + $delays[$attempt + 1], ZJA_NOTIFICATION_EVENT, [$postId, $attempt + 1]);
    }
}, 10, 2);

add_action('admin_menu', function (): void {
    add_management_page('Notification Platform', 'Notification Platform', 'manage_options', 'custom-notifications', function (): void {
        echo '<div class="wrap"><h1>Custom Notification Platform</h1><p>Subscriber management, campaigns, delivery history, and analytics are available in the central dashboard.</p><p><a class="button button-primary" href="'.esc_url(zja_notification_url().'/admin?site='.ZJA_NOTIFICATION_SITE).'" target="_blank" rel="noopener">Open notification dashboard</a></p></div>';
    });
});
