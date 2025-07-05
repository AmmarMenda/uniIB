<?php
session_start();

// Configuration
$config = [
    "admin_username" => "redditmod",
    "admin_password" => "admin", // In production, use password_hash()
    "posts_per_page" => 20,
    "data_dir" => "b/posts/", // Directory where posts are stored
    "backup_dir" => "b/backups/",
    "base_url" => "", // Your site's base URL
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
        // Create backup before deletion
        if (!is_dir($config["backup_dir"])) {
            mkdir($config["backup_dir"], 0755, true);
        }

        $post_data = json_decode(file_get_contents($post_file), true);

        // Backup the image if exists
        if (
            !empty($post_data["file"]) &&
            file_exists($config["data_dir"] . $post_data["file"])
        ) {
            rename(
                $config["data_dir"] . $post_data["file"],
                $config["backup_dir"] . "deleted_" . $post_data["file"]
            );
        }

        rename(
            $post_file,
            $config["backup_dir"] .
                "deleted_" .
                $post_id .
                "_" .
                time() .
                ".json"
        );
        $success_msg = "Post deleted successfully";
    }
}

// Get all posts
$posts = [];
if (isset($_SESSION["authenticated"]) && is_dir($config["data_dir"])) {
    $post_files = scandir($config["data_dir"], SCANDIR_SORT_DESCENDING);
    foreach ($post_files as $file) {
        if ($file === "." || $file === "..") {
            continue;
        }
        if (pathinfo($file, PATHINFO_EXTENSION) !== "json") {
            continue;
        }

        $post_content = file_get_contents($config["data_dir"] . $file);
        $post_data = json_decode($post_content, true);

        // Skip if invalid data
        if (!is_array($post_data)) {
            continue;
        }

        $posts[] = $post_data;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Openchan /b/ - Moderator Panel</title>
    <link rel="shortcut icon" href="favicon.png">
    <link rel="stylesheet" href="../styles/moderator.css">
    <style>
        .post-image {
            max-width: 200px;
            max-height: 200px;
            margin: 10px 0;
            border: 1px solid #ddd;
        }
        .post-image-container {
            margin: 10px 0;
        }
    </style>
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
                <h1>/b/ Moderator Panel</h1>
                <p>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></p>
                <p>Total Posts: <?= count($posts) ?></p>

                <div class="post-list">
                    <h2>Recent Posts</h2>
                    <?php if (empty($posts)): ?>
                        <p>No posts found.</p>
                    <?php else: ?>
                        <table class="posts-table">
                            <thead>
                                <tr>
                                    <th>Post ID</th>
                                    <th>Content</th>
                                    <th>Image</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($posts as $post): ?>
                                <tr>
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
                                    <td><?= date(
                                        "Y-m-d H:i:s",
                                        $post["timestamp"]
                                    ) ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Delete this post?');">
                                            <input type="hidden" name="delete_post" value="<?= htmlspecialchars(
                                                $post["id"]
                                            ) ?>">
                                            <button type="submit" class="delete-btn">Delete</button>
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
        <p>Openchan Moderator Panel &copy; <?= date("Y") ?></p>
    </footer>
</body>
</html>
