<?php
session_start();

// Configuration
$config = [
    "data_dir" => "posts/",
    "reports_dir" => "../reports/",
];

// Create directories if needed
if (!file_exists($config["reports_dir"])) {
    mkdir($config["reports_dir"], 0755, true);
}

// Handle report submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["post_id"])) {
    $post_id = basename($_POST["post_id"]);
    $post_file = $config["data_dir"] . $post_id . ".json";

    if (file_exists($post_file)) {
        // Record the report
        $report_file = $config["reports_dir"] . "report_" . $post_id . ".json";
        $report_data = [
            "post_id" => $post_id,
            "reported_at" => time(),
            "reported_by" => $_SERVER["REMOTE_ADDR"],
        ];
        file_put_contents($report_file, json_encode($report_data));

        // Send back to the board
        header("Location: " . $_SERVER["HTTP_REFERER"] . "#post-" . $post_id);
        exit();
    }
}

// If something went wrong
header("Location: index.php");
exit();
?>
