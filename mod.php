<?php
session_start();

// Configuration
$config = [
    "admin_username" => "batman",
    "admin_password" => "ammar007",
    "reports_dir" => "reports/",
    "backup_dir" => "backups/",
];

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Get all boards
$boards = [];
$dirs = glob("*/", GLOB_ONLYDIR);
foreach ($dirs as $dir) {
    $dir = rtrim($dir, "/");
    if ($dir != "reports" && $dir != "backups" && $dir != "overchan") {
        $boards[] = $dir;
    }
}

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
    $post_info = explode("|", $_POST["delete_post"]);
    $board = $post_info[0];
    $post_id = $post_info[1];
    $post_file = "$board/posts/$post_id.json";

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
            $all_posts = glob("$board/posts/*.json");
            foreach ($all_posts as $reply_file) {
                $reply_data = json_decode(file_get_contents($reply_file), true);

                if (
                    ($reply_data["thread_id"] ?? null) == $post_id &&
                    !($reply_data["is_thread"] ?? false)
                ) {
                    // Backup reply image if exists
                    if (
                        !empty($reply_data["file"]) &&
                        file_exists("$board/posts/" . $reply_data["file"])
                    ) {
                        rename(
                            "$board/posts/" . $reply_data["file"],
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
            file_exists("$board/posts/" . $post_data["file"])
        ) {
            rename(
                "$board/posts/" . $post_data["file"],
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
            ? "Thread and $replies_deleted replies deleted successfully from /$board/"
            : "Post deleted successfully from /$board/";

        // Update post count
        updatePostCount($board, -1 - $replies_deleted);
    } else {
        $error_msg = "Post not found in /$board/";
    }
}

function updatePostCount($board, $change)
{
    $count_file = "$board/postcount.txt";
    $overchan_file = "overchan/postcount.txt";

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

// Get reported posts from all boards
$reported_posts = [];
if (isset($_SESSION["authenticated"])) {
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
            $board = $report_data["board"] ?? "b"; // Default to 'b' for backward compatibility
            $post_file = "$board/posts/" . $report_data["post_id"] . ".json";

            if (file_exists($post_file)) {
                $post_data = json_decode(file_get_contents($post_file), true);
                $post_data["report_data"] = $report_data;
                $post_data["board"] = $board;
                $reported_posts[] = $post_data;
            }
        }
    }

    // Sort by report time (newest first)
    usort($reported_posts, function ($a, $b) {
        return $b["report_data"]["reported_at"] <=>
            $a["report_data"]["reported_at"];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Openchan Global Moderator Panel</title>
    <link rel="shortcut icon" href="favicon.png">
    <link rel="stylesheet" href="styles/moderator.css">
    <style>
        .board-tag {
            background-color: #eef;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <header id="nav">
        <span class='left'>
            <a href="/">Back to Home</a>
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
                <h1>Global Moderator Login</h1>
                <form method="post" class="login-form">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit" name="login">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="mod-panel">
                <h1>Global Reported Posts</h1>
                <p>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></p>
                <p>Total Reported Posts: <?= count($reported_posts) ?></p>
                <p>Active Boards: <?= implode(
                    ", ",
                    array_map(fn($b) => "/$b/", $boards)
                ) ?></p>

                <div class="post-list">
                    <?php if (empty($reported_posts)): ?>
                        <p>No reported posts found across all boards.</p>
                    <?php else: ?>
                        <table class="posts-table">
                            <thead>
                                <tr>
                                    <th>Board</th>
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
                                    <td><span class="board-tag">/<?= htmlspecialchars(
                                        $post["board"]
                                    ) ?>/</span></td>
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
                                                $post["board"] .
                                                    "/posts/" .
                                                    $post["file"]
                                            )
                                        ): ?>
                                            <div class="post-image-container">
                                                <img src="<?= htmlspecialchars(
                                                    $post["board"] .
                                                        "/posts/" .
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
                                        <form method="post" onsubmit="return confirm('Delete this post from /<?= htmlspecialchars(
                                            $post["board"]
                                        ) ?>/?');">
                                            <input type="hidden" name="delete_post" value="<?= htmlspecialchars(
                                                $post["board"] .
                                                    "|" .
                                                    $post["id"]
                                            ) ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
                                        </form>
                                        <form method="post" action="dismiss_report.php" style="margin-top: 5px;">
                                            <input type="hidden" name="post_id" value="<?= htmlspecialchars(
                                                $post["id"]
                                            ) ?>">
                                            <input type="hidden" name="board" value="<?= htmlspecialchars(
                                                $post["board"]
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
        <p>Openchan Global Moderator Panel &copy; <?= date("Y") ?></p>
    </footer>
</body>
</html>
