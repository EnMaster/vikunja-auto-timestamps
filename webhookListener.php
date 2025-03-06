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

// Update a task on Vikunja using the API (secret must be created from Vikunja gui)
function updateTask($taskId, $data) {
    error_log(json_encode($data));

    global $vikunjaUrl, $vikunjaToken;

    $ch = curl_init("{$vikunjaUrl}tasks/{$taskId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $vikunjaToken",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log($response);
    return $httpCode == 200;
}

// Receive webhook data
$input = file_get_contents("php://input");
$data = json_decode($input, true);

$task = $data['data']['task'] ?? null;

//error_log(print_r($data, true));
if (!$task) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid webhook data"]);
    exit;
}

$taskId = $task['id'];
$bucketId = $task['bucket_id'];
$startDate = $task['start_date'];  
$endDate = $task['end_date'];  
$doneAt = $task['done_at'];
$done = $task['done'];
$dueDate = $task['due_date'];
$now =  (new DateTime())->format('Y-m-d\TH:i:s\Z');

$updateData = [];


if ($doneAt != "0001-01-01T00:00:00Z" && $done == 1) {
    $updateData['start_date'] = $startDate;
    $updateData['end_date'] = $doneAt;
    $updateData['due_date'] = $dueDate;
    $updateData['done'] = $done;
} else if ($doneAt == "0001-01-01T00:00:00Z" && $done == 0 && $startDate == "0001-01-01T00:00:00Z" && $endDate == "0001-01-01T00:00:00Z") {
    $updateData['start_date'] = $now;
    $updateData['due_date'] = $dueDate;
    $updateData['done'] = $done;
}


if (!empty($updateData)) {
    error_log($taskId);
    error_log(json_encode($updateData));
    if (updateTask($taskId, $updateData)) {
        error_log("update");
    } else {
        error_log("error");
    }
}