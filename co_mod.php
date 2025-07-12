<?php
session_start();

// Configuration
$config = [
    "admin_username" => "batman",
    "admin_password" => "ammar007",
    "co_data_dir" => "./co/data/",
    "co_submissions_file" => "./co/submissions.json",
    "co_postcount_file" => "./co/postcount.txt",
    "backup_dir" => "backups/",
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

// Handle submission deletion
if (isset($_SESSION["authenticated"]) && isset($_POST["delete_submission"])) {
    $submission_id = $_POST["delete_submission"];
    $submissions = json_decode(
        file_get_contents($config["co_submissions_file"]),
        true
    );

    // Create backup before deletion
    if (!is_dir($config["backup_dir"])) {
        mkdir($config["backup_dir"], 0755, true);
    }

    // Find and backup the submission
    foreach ($submissions as $index => $submission) {
        if ($submission["timestamp"] == $submission_id) {
            // Backup the file if exists
            if (
                !empty($submission["file"]) &&
                file_exists($config["co_data_dir"] . $submission["file"])
            ) {
                rename(
                    $config["co_data_dir"] . $submission["file"],
                    $config["backup_dir"] . "deleted_" . $submission["file"]
                );
            }

            // Backup the submission record
            file_put_contents(
                $config["backup_dir"] .
                    "deleted_submission_" .
                    $submission_id .
                    ".json",
                json_encode($submission)
            );

            // Remove from active submissions
            unset($submissions[$index]);

            // Update post count
            if (file_exists($config["co_postcount_file"])) {
                $current_count = (int) file_get_contents(
                    $config["co_postcount_file"]
                );
                if ($current_count > 0) {
                    file_put_contents(
                        $config["co_postcount_file"],
                        $current_count - 1
                    );
                }
            }

            // Update overchan post count if exists
            $overchan_file = "../overchan/postcount.txt";
            if (file_exists($overchan_file)) {
                $overchan_count = (int) file_get_contents($overchan_file);
                if ($overchan_count > 0) {
                    file_put_contents($overchan_file, $overchan_count - 1);
                }
            }

            break;
        }
    }

    // Save updated submissions
    file_put_contents(
        $config["co_submissions_file"],
        json_encode(array_values($submissions))
    );
    $success_msg = "Submission deleted successfully";
}
// Get all submissions
$submissions = [];
if (
    isset($_SESSION["authenticated"]) &&
    file_exists($config["co_submissions_file"])
) {
    $submissions = json_decode(
        file_get_contents($config["co_submissions_file"]),
        true
    );
    $submissions = is_array($submissions) ? $submissions : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Openchan - Moderator Panel</title>
    <link rel="shortcut icon" href="favicon.png">
    <link rel="stylesheet" href="./styles/moderator.css">
    <style>
        .submission {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .submission-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        .submission-meta {
            font-size: 0.9em;
            color: #666;
        }

        .submission-actions {
            margin-top: 10px;
        }

        .delete-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }

        .view-id {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 5px;
        }

        /* Dark mode styles */
        body.dark .submission {
            background-color: #333;
            border-color: #444;
        }

        body.dark .submission-header {
            border-color: #444;
        }

        body.dark .submission-meta {
            color: #aaa;
        }
    </style>
</head>
<body>
    <header id="nav">
        <span class='left'>
            <a href="../">Home</a>
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
                <h1>Coordinator Submissions</h1>
                <p>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></p>

                <div class="submissions-list">
                    <?php if (empty($submissions)): ?>
                        <p>No submissions found.</p>
                    <?php else: ?>
                        <?php foreach ($submissions as $submission): ?>
                        <div class="submission">
                            <div class="submission-header">
                                <h3><?= htmlspecialchars(
                                    $submission["name"]
                                ) ?></h3>
                                <span class="submission-meta">
                                    <?= date(
                                        "Y-m-d H:i:s",
                                        $submission["timestamp"]
                                    ) ?>
                                </span>
                            </div>

                            <div class="submission-details">
                                <p>
                                    <strong>Department:</strong> <?= htmlspecialchars(
                                        $submission["department"]
                                    ) ?><br>
                                    <strong>Branch:</strong> <?= htmlspecialchars(
                                        $submission["branch"]
                                    ) ?><br>
                                    <strong>Division:</strong> <?= htmlspecialchars(
                                        $submission["division"]
                                    ) ?><br>
                                    <strong>IP:</strong> <?= htmlspecialchars(
                                        $submission["ip"]
                                    ) ?>
                                </p>
                            </div>

                            <div class="submission-actions">
                                <?php if (!empty($submission["file"])): ?>
                                    <a href="<?= htmlspecialchars(
                                        $config["co_data_dir"] .
                                            $submission["file"]
                                    ) ?>"
                                       target="_blank" class="view-id">View ID Card</a>
                                <?php endif; ?>

                                <form method="post" onsubmit="return confirm('Delete this submission?');" style="display: inline;">
                                    <input type="hidden" name="delete_submission" value="<?= $submission[
                                        "timestamp"
                                    ] ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer id="footer">
        <p>Openchan Moderator Panel &copy; <?= date("Y") ?></p>
    </footer>
</body>
</html>
