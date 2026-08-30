<?php

/*
 * Lightweight Google Calendar integration - no external library
 * needed, just direct calls to Google's REST endpoints.
 */

function google_get_auth_url(string $state = ''): string
{
    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/userinfo.email',
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Exchanges a one-time authorization code for tokens.
 * Returns ['access_token' => ..., 'refresh_token' => ..., 'error' => ...]
 */
function google_exchange_code(string $code): array
{
    $ch = curl_init('https://oauth2.googleapis.com/token');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        return ['error' => $data['error_description'] ?? $data['error']];
    }

    return $data;
}

/**
 * Uses a stored refresh token to get a fresh access token.
 * Returns the access token string, or null on failure.
 */
function google_get_access_token(PDO $pdo): ?string
{
    $stmt = $pdo->query("SELECT google_refresh_token FROM company_settings ORDER BY id ASC LIMIT 1");
    $refresh_token = $stmt->fetchColumn();

    if (!$refresh_token) {
        return null;
    }

    $ch = curl_init('https://oauth2.googleapis.com/token');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'refresh_token' => $refresh_token,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'grant_type'    => 'refresh_token',
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    return $data['access_token'] ?? null;
}

/**
 * Creates an event on the connected Google Calendar.
 * $start_datetime / $end_datetime: 'Y-m-d H:i:s' strings, local time.
 * Returns ['success' => bool, 'event_link' => string, 'error' => string]
 */
function google_create_event(PDO $pdo, string $summary, string $description, string $start_datetime, string $end_datetime, string $location = ''): array
{
    $access_token = google_get_access_token($pdo);

    if (!$access_token) {
        return ['success' => false, 'error' => 'Google Calendar is not connected.'];
    }

    $event = [
        'summary'     => $summary,
        'description' => $description,
        'location'    => $location,
        'start'       => [
            'dateTime' => date('c', strtotime($start_datetime)),
            'timeZone' => 'Africa/Johannesburg',
        ],
        'end'         => [
            'dateTime' => date('c', strtotime($end_datetime)),
            'timeZone' => 'Africa/Johannesburg',
        ],
        'reminders'   => [
            'useDefault' => true,
        ],
    ];

    $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($event),
        CURLOPT_TIMEOUT    => 20,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['error'])) {
        return ['success' => false, 'error' => $data['error']['message'] ?? 'Unknown error'];
    }

    return [
        'success'    => true,
        'event_link' => $data['htmlLink'] ?? '',
        'event_id'   => $data['id'] ?? '',
    ];
}

/**
 * Lists events between two datetimes (inclusive).
 * Returns an array of ['summary' => ..., 'start' => ..., 'location' => ..., 'link' => ...]
 */
function google_list_events(PDO $pdo, string $time_min, string $time_max): array
{
    $access_token = google_get_access_token($pdo);

    if (!$access_token) {
        return [];
    }

    $params = [
        'timeMin'      => date('c', strtotime($time_min)),
        'timeMax'      => date('c', strtotime($time_max)),
        'singleEvents' => 'true',
        'orderBy'      => 'startTime',
        'maxResults'   => 50,
    ];

    $ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events?' . http_build_query($params));

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $access_token],
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!isset($data['items'])) {
        return [];
    }

    $events = [];

    foreach ($data['items'] as $item) {
        $events[] = [
            'summary'  => $item['summary'] ?? '(No title)',
            'start'    => $item['start']['dateTime'] ?? ($item['start']['date'] ?? ''),
            'location' => $item['location'] ?? '',
            'link'     => $item['htmlLink'] ?? '',
        ];
    }

    return $events;
}
