<?php
session_start();

// Configuration
$config = [
    "admin_username" => "batman",
    "admin_password" => "ammar007",
    "data_dir" => "b/posts/",
    "reports_dir" => "b/reports/",
    "backup_dir" => "b/backups/",
];

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Handle logout
if (isset($_GET["logout"])) {
    session_unset();
    session_destroy();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
    exit();
}

// Handle login
if (
    isset($_POST["login"]) &&
    !empty($_POST["username"]) &&
    !empty($_POST["password"])
) {
    if (
        $_POST["username"] === $config["admin_username"] &&
        $_POST["password"] === $config["admin_password"]
    ) {
        $_SESSION["authenticated"] = true;
        $_SESSION["username"] = $config["admin_username"];
        $_SESSION["last_activity"] = time();
        $_SESSION["ip"] = $_SERVER["REMOTE_ADDR"];
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    } else {
        $error_msg = "Invalid username or password";
    }
}

// Handle post deletion
if (isset($_SESSION["authenticated"]) && isset($_POST["delete_post"])) {
    $post_id = basename($_POST["delete_post"]);
    $post_file = $config["data_dir"] . $post_id . ".json";

    if (file_exists($post_file)) {
        // Create backup directory if needed
        if (!is_dir($config["backup_dir"])) {
            mkdir($config["backup_dir"], 0755, true);
        }

        // Check if this is a thread (OP post)
        $post_data = json_decode(file_get_contents($post_file), true);
        $is_thread = $post_data["is_thread"] ?? false;
        $replies_deleted = 0;

        // If it's a thread, find and delete all its replies
        if ($is_thread) {
            $all_posts = glob($config["data_dir"] . "*.json");
            foreach ($all_posts as $reply_file) {
                $reply_data = json_decode(file_get_contents($reply_file), true);

                // Check if this is a reply to the thread we're deleting
                if (
                    ($reply_data["thread_id"] ?? null) == $post_id &&
                    !($reply_data["is_thread"] ?? false)
                ) {
                    // Backup reply image if exists
                    if (
                        !empty($reply_data["file"]) &&
                        file_exists($config["data_dir"] . $reply_data["file"])
                    ) {
                        rename(
                            $config["data_dir"] . $reply_data["file"],
                            $config["backup_dir"] .
                                "deleted_reply_" .
                                basename($reply_data["file"])
                        );
                    }

                    // Delete any reports for this reply
                    $reply_report_file =
                        $config["reports_dir"] .
                        "report_" .
                        $reply_data["id"] .
                        ".json";
                    if (file_exists($reply_report_file)) {
                        rename(
                            $reply_report_file,
                            $config["backup_dir"] .
                                "deleted_report_" .
                                $reply_data["id"] .
                                ".json"
                        );
                    }

                    // Delete the reply JSON file
                    rename(
                        $reply_file,
                        $config["backup_dir"] .
                            "deleted_" .
                            basename($reply_file)
                    );
                    $replies_deleted++;
                }
            }
        }

        // Backup the main post image if exists
        if (
            !empty($post_data["file"]) &&
            file_exists($config["data_dir"] . $post_data["file"])
        ) {
            rename(
                $config["data_dir"] . $post_data["file"],
                $config["backup_dir"] . "deleted_" . $post_data["file"]
            );
        }

        // Delete any reports for the main post
        $report_file = $config["reports_dir"] . "report_" . $post_id . ".json";
        if (file_exists($report_file)) {
            rename(
                $report_file,
                $config["backup_dir"] .
                    "deleted_report_" .
                    $post_id .
                    "_" .
                    time() .
                    ".json"
            );
        }

        // Delete the main post file
        rename(
            $post_file,
            $config["backup_dir"] .
                "deleted_" .
                $post_id .
                "_" .
                time() .
                ".json"
        );

        $success_msg = $is_thread
            ? "Thread and $replies_deleted replies deleted successfully"
            : "Post deleted successfully";

        // Update post count
        updatePostCount(-1 - $replies_deleted);
    } else {
        // Handle case where post file doesn't exist
        $error_msg = "Post not found";
    }
}

// Helper function to update post counts
function updatePostCount($change)
{
    $count_file = "b/postcount.txt";
    $overchan_file = "../overchan/postcount.txt";

    if (file_exists($count_file)) {
        $current = max(0, (int) file_get_contents($count_file) + $change);
        file_put_contents($count_file, $current);
    }

    if (file_exists($overchan_file)) {
        $current = max(0, (int) file_get_contents($overchan_file) + $change);
        file_put_contents($overchan_file, $current);
    }
}
// Handle backup clearance
if (isset($_SESSION["authenticated"]) && isset($_POST["clear_backups"])) {
    $backup_files = glob($config["backup_dir"] . "*");
    $deleted_count = 0;

    foreach ($backup_files as $file) {
        if (is_file($file)) {
            unlink($file);
            $deleted_count++;
        }
    }

    $success_msg = "Deleted $deleted_count backup files";
}
// Get reported posts
$reported_posts = [];
if (isset($_SESSION["authenticated"])) {
    // First get all reports
    if (is_dir($config["reports_dir"])) {
        $report_files = scandir($config["reports_dir"]);
        foreach ($report_files as $file) {
            if ($file === "." || $file === "..") {
                continue;
            }

            $report_data = json_decode(
                file_get_contents($config["reports_dir"] . $file),
                true
            );
            $post_file =
                $config["data_dir"] . $report_data["post_id"] . ".json";

            if (file_exists($post_file)) {
                $post_data = json_decode(file_get_contents($post_file), true);
                $post_data["report_data"] = $report_data;
                $reported_posts[] = $post_data;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Openchan /b/ - Reported Posts</title>
    <link rel="shortcut icon" href="../favicon.png">
    <link rel="stylesheet" href="../styles/moderator.css">
</head>
<body>
    <header id="nav">
        <span class='left'>
            <a href="../b/">Back to /b/</a>
        </span>
        <span class="right">
            <?php if (isset($_SESSION["authenticated"])): ?>
                <a href="?logout" class="logout-btn">Logout</a>
            <?php endif; ?>
        </span>
    </header>

    <main id="content">
        <?php if (isset($error_msg)): ?>
            <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <?php if (isset($success_msg)): ?>
            <div class="alert success"><?= htmlspecialchars(
                $success_msg
            ) ?></div>
        <?php endif; ?>

        <?php if (!isset($_SESSION["authenticated"])): ?>
            <div class="login-container">
                <h1>Moderator Login</h1>
                <form method="post" class="login-form">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit" name="login">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="mod-panel">
                <h1>Reported Posts</h1>
                <p>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></p>
                <p>Total Reported Posts: <?= count($reported_posts) ?></p>

                <div class="post-list">
                    <?php if (empty($reported_posts)): ?>
                        <p>No reported posts found.</p>
                    <?php else: ?>
                        <table class="posts-table">
                            <thead>
                                <tr>
                                    <th>Post ID</th>
                                    <th>Content</th>
                                    <th>Image</th>
                                    <th>Reported At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reported_posts as $post): ?>
                                <tr class="reported-post">
                                    <td><?= htmlspecialchars(
                                        substr($post["id"], 0, 8)
                                    ) ?>...</td>
                                    <td class="post-content">
                                        <strong><?= htmlspecialchars(
                                            $post["name"]
                                        ) ?></strong><br>
                                        <?php if (!empty($post["subject"])): ?>
                                            <em><?= htmlspecialchars(
                                                $post["subject"]
                                            ) ?></em><br>
                                        <?php endif; ?>
                                        <?= nl2br(
                                            htmlspecialchars(
                                                substr($post["message"], 0, 200)
                                            )
                                        ) ?>
                                        <?php if (
                                            strlen($post["message"]) > 200
                                        ): ?>...<?php endif; ?>
                                    </td>
                                    <td class="post-image-cell">
                                        <?php if (
                                            !empty($post["file"]) &&
                                            file_exists(
                                                $config["data_dir"] .
                                                    $post["file"]
                                            )
                                        ): ?>
                                            <div class="post-image-container">
                                                <img src="<?= htmlspecialchars(
                                                    $config["data_dir"] .
                                                        $post["file"]
                                                ) ?>"
                                                     class="post-image"
                                                     alt="Post image">
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date(
                                            "Y-m-d H:i:s",
                                            $post["report_data"]["reported_at"]
                                        ) ?><br>
                                        IP: <?= htmlspecialchars(
                                            $post["report_data"]["reported_by"]
                                        ) ?>
                                    </td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Delete this post?');">
                                            <input type="hidden" name="delete_post" value="<?= htmlspecialchars(
                                                $post["id"]
                                            ) ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                        <form method="post" action="dismiss_report.php" style="margin-top: 5px;">
                                            <input type="hidden" name="post_id" value="<?= htmlspecialchars(
                                                $post["id"]
                                            ) ?>">
                                            <button type="submit" class="dismiss-btn">Dismiss Report</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer id="footer">
        <div class="mod-actions">
            <form method="post" onsubmit="return confirm('Permanently delete ALL backup files? This cannot be undone!');">
                <button type="submit" name="clear_backups" class="clear-backups-btn">Clear All Backups</button>
            </form>
        </div>
        <p>uniIB Moderator Panel &copy; <?= date("Y") ?></p>
    </footer>
</body>
</html>
