<?php
// /ev/report.php - Report submission handler for /ev/ board

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["post_id"])) {
    $post_id = (int) $_POST["post_id"];
    $report = [
        "post_id" => $post_id,
        "board" => "ev", // Crucial: Set the board identifier
        "reported_at" => time(),
        "reported_by" => $_SERVER["REMOTE_ADDR"],
    ];

    $reports_dir = __DIR__ . "/../reports/";
    if (!is_dir($reports_dir)) {
        mkdir($reports_dir, 0755, true);
    }

    $report_file = $reports_dir . "report_" . $post_id . ".json";
    file_put_contents($report_file, json_encode($report, JSON_PRETTY_PRINT));

    // Redirect back to the thread or board
    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit();
}

// If not POST, show error or redirect
http_response_code(405);
echo "Invalid request method";
?>
