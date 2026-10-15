<?php

// load the .env file whitout external lib
function loadEnv($file = '.env') {
    if (!file_exists($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue; 
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_ENV) && !getenv($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

loadEnv();

// ENV vars
$vikunjaUrl = getenv('VIKUNJA_URL') ?: 'http://localhost/api/v1/';
$vikunjaToken = getenv('VIKUNJA_API_TOKEN') ?: 'your_api_token_here';
$vikunjaWebhookSecret = getenv('VIKUNJA_WEBHOOK_SECRET') ?: '';

const ZERO_DATE = '0001-01-01T00:00:00Z';
const BUCKET_BACKLOG_TITLE = 'backlog';
const BUCKET_DONE_TITLE = 'done';

function isZeroDate($value) {
    return empty($value) || $value === ZERO_DATE;
}

function sameDate($a, $b) {
    return (isZeroDate($a) && isZeroDate($b)) || $a === $b;
}

function getTaskBucketTitles($task) {
    $titles = [];
    if (!isset($task['buckets']) || !is_array($task['buckets'])) {
        return $titles;
    }

    foreach ($task['buckets'] as $bucket) {
        if (is_array($bucket) && isset($bucket['title'])) {
            $titles[] = strtolower(trim($bucket['title']));
        }
    }

    return $titles;
}

// Update a task on Vikunja using the API (secret must be created from Vikunja gui)
function updateTask($taskId, $data) {
    error_log(json_encode($data));

    global $vikunjaUrl, $vikunjaToken;

    $ch = curl_init("{$vikunjaUrl}tasks/{$taskId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $vikunjaToken",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        error_log("curl error: $curlError");
    }

    error_log($response);
    return $httpCode >= 200 && $httpCode < 300;
}

// Receive webhook data
$input = file_get_contents("php://input");

if ($vikunjaWebhookSecret === '') {
    error_log('VIKUNJA_WEBHOOK_SECRET is not configured');
    http_response_code(500);
    echo json_encode(["error" => "Server misconfigured"]);
    exit;
}

$signature = $_SERVER['HTTP_X_VIKUNJA_SIGNATURE'] ?? '';
$expectedSignature = hash_hmac('sha256', $input, $vikunjaWebhookSecret);

if ($signature === '' || !hash_equals($expectedSignature, $signature)) {
    error_log('Invalid webhook signature');
    http_response_code(401);
    echo json_encode(["error" => "Invalid signature"]);
    exit;
}

$data = json_decode($input, true);

$task = $data['data']['task'] ?? null;

if (!$task) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid webhook data"]);
    exit;
}

$taskId = $task['id'];
$startDate = $task['start_date'] ?? null;
$endDate = $task['end_date'] ?? null;
$doneAt = $task['done_at'] ?? null;
$done = $task['done'] ?? false;
$dueDate = $task['due_date'] ?? null;
$now = gmdate('Y-m-d\TH:i:s\Z');

$isDone = (bool) $done;
$hasStart = !isZeroDate($startDate);
$hasEnd = !isZeroDate($endDate);

$bucketTitles = getTaskBucketTitles($task);
$hasBucketInfo = !empty($bucketTitles);
$inBacklog = in_array(BUCKET_BACKLOG_TITLE, $bucketTitles, true);
$inDone = in_array(BUCKET_DONE_TITLE, $bucketTitles, true);

$newStart = $startDate;
$newEnd = $endDate;
$newDone = $isDone;

if ($hasBucketInfo) {
    if ($inDone) {
        $newDone = true;
        if (!$hasEnd) {
            $newEnd = isZeroDate($doneAt) ? $now : $doneAt;
        }
        if (!$hasStart) {
            $newStart = $now;
        }
    } elseif ($inBacklog) {
        if ($isDone || $hasEnd) {
            $newDone = false;
            $newEnd = ZERO_DATE;
        }
        if ($hasStart) {
            $newStart = ZERO_DATE;
        }
    } else {
        if ($isDone || $hasEnd) {
            $newDone = false;
            $newEnd = ZERO_DATE;
        }
        if (!$hasStart) {
            $newStart = $now;
        }
    }
} else {
    if (!isZeroDate($doneAt) && $isDone) {
        $newStart = $startDate;
        $newEnd = $doneAt;
    } elseif (isZeroDate($doneAt) && !$isDone && !$hasStart && !$hasEnd) {
        $newStart = $now;
    }
}

$updateData = [];

if (!sameDate($newStart, $startDate)) {
    $updateData['start_date'] = $newStart;
}
if (!sameDate($newEnd, $endDate)) {
    $updateData['end_date'] = $newEnd;
}
if ($newDone !== $isDone) {
    $updateData['done'] = $newDone;
}

if (!empty($updateData)) {
    $updateData['due_date'] = $dueDate;
    error_log($taskId);
    error_log(json_encode($updateData));
    if (updateTask($taskId, $updateData)) {
        error_log("update");
    } else {
        error_log("error");
    }
}
