<?php
/*
Plugin Name: Pushover
Plugin URI: https://github.com/ionos/yourls-notifier-pushover
Description: Sends a notification via Pushover when a short is created and/or clicked
Version: 0.1
Author: ionos
Author URI: https://github.com/ionos
*/

// No direct call
if (!defined('YOURLS_ABSPATH')) {
    die();
}

function pushover_notify(string $title, string $message)
{
    $app_token = yourls_get_option('pushover_app_token');
    $user_key = yourls_get_option('pushover_user_key');
    if (empty($app_token) || empty($user_key)) {
        return;
    }

    $curl_opts = [
        CURLOPT_URL => 'https://api.pushover.net/1/messages.json',
        CURLOPT_POST => true,
        CURLOPT_HEADER => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => [
            'token' => $app_token,
            'user' => $user_key,
            'title' => $title,
            'message' => $message,
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $curl_opts);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!empty($response)) {
        trigger_error("Pushover notifier failed to send message: $response!");
    }
}

function pushover_post_add_new_link($args)
{
    $data = $args[3];
    $message = <<<MSG
Target: {$data['url']['url']}
IP: {$data['url']['ip']}
ARG0: {$args[0]}
ARG1: {$args[1]}
ARG2: {$args[2]}
ARG3: {$args[3]}
MSG;

    pushover_notify($args[1] . '" created', $message);
}

function pushover_redirect_shorturl($args)
{
    $data = $args[3];
    $message = <<<MSG
Target: {$data['url']['url']}
IP: {$data['url']['ip']}
ARG0: {$args[0]}
ARG1: {$args[1]}
ARG2: {$args[2]}
ARG3: {$args[3]}
MSG;

    pushover_notify($args[1] . '" redirected', $message);
}

yourls_add_action('plugins_loaded', 'pushover_loaded');
function pushover_loaded()
{
    yourls_register_plugin_page('pushover_settings', 'Pushover', 'pushover_settings_page');

    $events = pushover_get_event_subscriptions();
    foreach ($events as $event => $enabled) {
        if ($enabled) {
            yourls_add_action($event, 'pushover_' . $event);
        }
    }
}

function pushover_get_event_subscriptions() {
    $events = [
        'post_add_new_link' => true,
        'redirect_shorturl' => false,
    ];
    $db_events = yourls_get_option('pushover_event_subscriptions');
    foreach ($db_events as $event => $status) {
        if (array_key_exists($event, $events)) {
            $events[$event] = $status;
        }
    }

    return $events;
}

function pushover_settings_page()
{
    $events = pushover_get_event_subscriptions();
    $event_descriptions = [
        'post_add_new_link' => 'When a new link is shortened',
        'redirect_shorturl' => 'When a short URL is accessed',
    ];

    if (isset($_POST['nonce'])) {
        yourls_verify_nonce('pushover_settings');

        $app_token = $_POST['pushover_app_token'];
        yourls_update_option('pushover_app_token', $app_token);

        $user_key = $_POST['pushover_user_key'];
        yourls_update_option('pushover_user_key', $user_key);

        $click_regex = $_POST['pushover_click_regex'];
        yourls_update_option('pushover_click_regex', $click_regex);

        $posted_events = [];
        if (!empty($_POST['events'])) {
            $posted_events = $_POST['events'];
        }
        foreach ($events as $event => $enabled) {
            if (array_key_exists($event, $posted_events)) {
                $events[$event] = true;
            } else {
                $events[$event] = false;
            }
        }
        yourls_update_option('pushover_event_subscriptions', $events);
    } else {
        $app_token = yourls_get_option('pushover_app_token', '');
        $user_key = yourls_get_option('pushover_user_key', '');
        $click_regex = yourls_get_option('pushover_click_regex', '');
    }

    $nonce = yourls_create_nonce('pushover_settings');

    echo <<<HTML
        <main>
            <h2>Pushover</h2>
            <form method="post">
            <input type="hidden" name="nonce" value="$nonce" />
            <p>
                <label>App Token</label>
                <input type="text" name="pushover_app_token" value="$app_token" size="100" />
            </p>
            <p>
                <label>User Key</label>
                <input type="text" name="pushover_user_key" value="$user_key" size="100" />
            </p>
            <fieldset>
                <legend>Events subscriptions</legend>
HTML;

    foreach ($events as $event => $enabled) {
        echo '<input type="checkbox" id="' . $event . '" name="events[' . $event . ']" ' . ($enabled ? 'checked' : '') . '><label for="' . $event . '">' . $event_descriptions[$event] . '</label><br>';
    }

    echo <<<HTML
            </fieldset>
            <p>
                <label>Short Click Regex</label>
                <input type="text" name="pushover_click_regex" value="$click_regex" size="100" />
            </p>
            <p><input type="submit" value="Save" class="button" /></p>
            </form>
        </main>
HTML;
}
