<?php
session_start();
if (!isset($_SESSION["authenticated"])) {
    http_response_code(403);
    exit("Unauthorized");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $post_id = $_POST["post_id"] ?? "";
    $board = $_POST["board"] ?? "";

    if ($post_id && $board) {
        $reports_dir = "reports/";
        $report_file = $reports_dir . "report_" . $post_id . ".json";
        if (file_exists($report_file)) {
            unlink($report_file);
            // Optional: log or flag if removal fails
        }
    }
}
// Redirect back to the mod panel
header("Location: mod.php");
exit();
?>
