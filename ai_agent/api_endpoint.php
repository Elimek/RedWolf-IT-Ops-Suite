<?php
declare(strict_types=1);

/**
 * RedWolf IT Ops - AI Classifier API Endpoint
 *
 * Receives ticket classification requests from the frontend,
 * forwards them to the Python FastAPI service, and saves results to MySQL.
 *
 * Endpoint: POST /ai_agent/api_endpoint.php
 */

// --- Configuration ---
define('PYTHON_SERVICE_URL', 'http://localhost:8001/classify');
define('MAX_TEXT_LENGTH', 2000);
define('DB_HOST', 'localhost');
define('DB_NAME', 'redwolf_ops');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- CORS Headers ---
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed. Use POST.'], 405);
}

// --- Read and parse input ---
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if ($input === null || !is_array($input)) {
    json_response(['error' => 'Invalid JSON body.'], 400);
}

// --- Validate input ---
$text = $input['text'] ?? '';
$ticket_id = $input['ticket_id'] ?? '';

if (empty(trim($text))) {
    json_response(['error' => 'Ticket text is required and cannot be empty.'], 400);
}

if (mb_strlen($text) > MAX_TEXT_LENGTH) {
    json_response(['error' => 'Ticket text exceeds maximum length of ' . MAX_TEXT_LENGTH . ' characters.'], 400);
}

if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $ticket_id)) {
    json_response(['error' => 'Ticket ID must be alphanumeric (letters, numbers, underscores, hyphens).'], 400);
}

// --- Call Python FastAPI service ---
$ch = curl_init(PYTHON_SERVICE_URL);
$payload = json_encode([
    'text' => $text,
    'ticket_id' => $ticket_id,
]);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error || $response === false) {
    // Python service is unreachable - return a graceful fallback
    error_log("Classifier API: Python service unreachable - " . $curl_error);
    $fallback = [
        'ticket_id' => $ticket_id,
        'category' => 'other',
        'confidence' => 0.0,
        'reasoning' => 'Classification service is currently unavailable. Please try again later.',
        'priority' => 'medium',
        'classifier' => 'unavailable',
        'response_time_ms' => 0,
        'warning' => 'AI classifier service is down. Using fallback.',
    ];
    json_response($fallback, 503);
}

$result = json_decode($response, true);

if ($result === null || $http_code !== 200) {
    error_log("Classifier API: Python service returned error. HTTP {$http_code}");
    json_response([
        'error' => 'Classification service returned an error.',
        'details' => $result ?? null,
    ], 502);
}

// --- Save to MySQL ---
save_classification($ticket_id, $text, $result);

// --- Return result to frontend ---
json_response($result, 200);

// --- Helper Functions ---

/**
 * Send a JSON response and exit.
 */
function json_response(array $data, int $status_code = 200): void
{
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Save classification result to the MySQL ticket_classifications table.
 */
function save_classification(string $ticket_id, string $text, array $result): void
{
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO ticket_classifications
                (ticket_id, ticket_text, category, confidence, reasoning, priority, classifier, response_time_ms, created_at)
            VALUES
                (:ticket_id, :ticket_text, :category, :confidence, :reasoning, :priority, :classifier, :response_time_ms, NOW())
            ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                confidence = VALUES(confidence),
                reasoning = VALUES(reasoning),
                priority = VALUES(priority),
                classifier = VALUES(classifier),
                response_time_ms = VALUES(response_time_ms),
                created_at = NOW()
        SQL);

        $stmt->execute([
            ':ticket_id' => $ticket_id,
            ':ticket_text' => mb_substr($text, 0, 2000),
            ':category' => $result['category'] ?? 'other',
            ':confidence' => $result['confidence'] ?? 0.0,
            ':reasoning' => mb_substr($result['reasoning'] ?? '', 0, 500),
            ':priority' => $result['priority'] ?? 'medium',
            ':classifier' => $result['classifier'] ?? 'unknown',
            ':response_time_ms' => $result['response_time_ms'] ?? 0,
        ]);

    } catch (PDOException $e) {
        error_log("Classifier API: Database error - " . $e->getMessage());
        // Do not fail the request - classification result is still valid
    }
}
