<?php
// Configuration
$config = [
    "board_title" => "/b/ - Random",
    "description" => "Off-topic discussion",
    "posts_per_page" => 10,
    "data_dir" => "posts/",
    "allowed_file_types" => ["image/jpeg", "image/png", "image/gif"],
    "max_file_size" => 2 * 1024 * 1024, // 2MB
    "max_threads" => 100,
    "max_replies" => 200, // Max replies per thread
];

// Create directories if they don't exist
if (!file_exists($config["data_dir"])) {
    mkdir($config["data_dir"], 0755, true);
}

// Handle new post submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $error = "";

    // Validate inputs
    $name = isset($_POST["name"])
        ? substr(trim($_POST["name"]), 0, 50)
        : "Anonymous";
    $subject = isset($_POST["subject"])
        ? substr(trim($_POST["subject"]), 0, 100)
        : "";
    $message = isset($_POST["message"]) ? trim($_POST["message"]) : "";
    $thread_id = isset($_POST["thread_id"]) ? (int) $_POST["thread_id"] : null;

    if (empty($message)) {
        $error = "Message cannot be empty";
    }

    // Handle file upload (only allowed in OP posts)
    $file_name = "";
    $file_path = "";
    if (
        empty($thread_id) &&
        isset($_FILES["file"]) &&
        $_FILES["file"]["error"] === UPLOAD_ERR_OK
    ) {
        $file_info = $_FILES["file"];

        if ($file_info["size"] > $config["max_file_size"]) {
            $error = "File too large (max 2MB)";
        } elseif (
            !in_array($file_info["type"], $config["allowed_file_types"])
        ) {
            $error = "Invalid file type";
        } else {
            $file_ext = pathinfo($file_info["name"], PATHINFO_EXTENSION);
            $file_name = uniqid() . "." . $file_ext;
            $file_path = $config["data_dir"] . $file_name;

            if (!move_uploaded_file($file_info["tmp_name"], $file_path)) {
                $error = "Failed to upload file";
            }
        }
    }

    // Save post if no errors
    if (empty($error)) {
        $post_id = time();
        $post_data = [
            "id" => $post_id,
            "name" => $name,
            "subject" => $subject,
            "message" => $message,
            "file" => $file_name,
            "timestamp" => $post_id,
            "ip" => $_SERVER["REMOTE_ADDR"],
            "is_thread" => empty($thread_id),
            "thread_id" => empty($thread_id) ? $post_id : $thread_id,
            "replies" => [],
        ];

        // If this is a reply, add it to the thread
        if (!empty($thread_id)) {
            $thread_file = $config["data_dir"] . $thread_id . ".json";
            if (file_exists($thread_file)) {
                $thread_data = json_decode(
                    file_get_contents($thread_file),
                    true
                );
                if (count($thread_data["replies"]) < $config["max_replies"]) {
                    $thread_data["replies"][] = $post_data;
                    file_put_contents($thread_file, json_encode($thread_data));
                } else {
                    $error =
                        "Thread is full (max " .
                        $config["max_replies"] .
                        " replies)";
                }
            } else {
                $error = "Thread not found";
            }
        }

        // If this is a new thread or reply was added successfully
        if (empty($thread_id) || empty($error)) {
            file_put_contents(
                $config["data_dir"] . $post_id . ".json",
                json_encode($post_data)
            );
        }

        if (empty($error)) {
            header("Location: " . $_SERVER["REQUEST_URI"]);
            exit();
        }
    }
}

// Get all threads (OP posts)
$threads = [];
if (is_dir($config["data_dir"])) {
    $post_files = scandir($config["data_dir"]);
    rsort($post_files); // Newest first

    foreach ($post_files as $file) {
        if ($file === "." || $file === "..") {
            continue;
        }
        if (pathinfo($file, PATHINFO_EXTENSION) !== "json") {
            continue;
        }

        $post_content = file_get_contents($config["data_dir"] . $file);
        $post_data = json_decode($post_content, true);

        if ($post_data["is_thread"]) {
            $threads[] = $post_data;
            if (count($threads) >= $config["max_threads"]) {
                break;
            }
        }
    }
}

// Update post count
$total_posts =
    count($threads) +
    array_sum(
        array_map(function ($t) {
            return count($t["replies"]);
        }, $threads)
    );
file_put_contents("postcount.txt", $total_posts);
file_put_contents("lastupdated.txt", date("Y-m-d H:i:s"));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config["board_title"]) ?> - Openchan</title>
    <link rel="stylesheet" href="../styles/board.css">
    <link rel="shortcut icon" href="../favicon.png">
    <script>
    function toggleReplyForm(threadId) {
        const form = document.getElementById('reply-form-' + threadId);
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    </script>
</head>
<body>
    <header id="nav">
        <span class='left'>
            <a href="../">Home</a> /
            <a href="./"><?= htmlspecialchars($config["board_title"]) ?></a>
        </span>
        <span class="right">
            <a href="../mod.php">Mod Panel</a>
        </span>
    </header>

    <div id="board-header">
        <h1><?= htmlspecialchars($config["board_title"]) ?></h1>
        <p><?= htmlspecialchars($config["description"]) ?></p>
        <p>Total Posts: <?= $total_posts ?> (<?= count($threads) ?> threads)</p>
    </div>

    <div id="post-form">
        <form action="" method="post" enctype="multipart/form-data">
            <table>
                <tr>
                    <td>Name:</td>
                    <td><input type="text" name="name" maxlength="50" placeholder="Anonymous"></td>
                </tr>
                <tr>
                    <td>Subject:</td>
                    <td><input type="text" name="subject" maxlength="100"></td>
                </tr>
                <tr>
                    <td>Message:</td>
                    <td><textarea name="message" required></textarea></td>
                </tr>
                <tr>
                    <td>File:</td>
                    <td><input type="file" name="file"></td>
                </tr>
                <tr>
                    <td></td>
                    <td><button type="submit">Create Thread</button></td>
                </tr>
            </table>
            <?php if (!empty($error) && empty($_POST["thread_id"])): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
        </form>
    </div>

    <div id="threads">
        <?php foreach ($threads as $thread): ?>
        <div class="thread">
            <div class="op-post">
                <div class="post-header">
                    <span class="post-subject"><?= htmlspecialchars(
                        $thread["subject"]
                    ) ?></span>
                    <span class="post-name"><?= htmlspecialchars(
                        $thread["name"]
                    ) ?></span>
                    <span class="post-id">No. <?= $thread["id"] ?></span>
                    <span class="post-date"><?= date(
                        "Y/m/d H:i:s",
                        $thread["timestamp"]
                    ) ?></span>
                </div>
                <div class="post-content">
                    <?php if (!empty($thread["file"])): ?>
                    <div class="post-file">
                        <a href="<?= htmlspecialchars(
                            $config["data_dir"] . $thread["file"]
                        ) ?>" target="_blank">
                            <img src="<?= htmlspecialchars(
                                $config["data_dir"] . $thread["file"]
                            ) ?>"
                                 alt="Attachment" class="thumbnail">
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="post-message">
                        <?= nl2br(htmlspecialchars($thread["message"])) ?>
                    </div>
                </div>
                <div class="post-actions">
                    <button onclick="toggleReplyForm(<?= $thread[
                        "id"
                    ] ?>)">Reply</button>
                    <span class="reply-count">Replies: <?= count(
                        $thread["replies"]
                    ) ?></span>
                </div>
            </div>

            <!-- Reply form (hidden by default) -->
            <div id="reply-form-<?= $thread[
                "id"
            ] ?>" class="reply-form" style="display: none;">
                <form action="" method="post">
                    <input type="hidden" name="thread_id" value="<?= $thread[
                        "id"
                    ] ?>">
                    <table>
                        <tr>
                            <td>Name:</td>
                            <td><input type="text" name="name" maxlength="50" placeholder="Anonymous"></td>
                        </tr>
                        <tr>
                            <td>Message:</td>
                            <td><textarea name="message" required></textarea></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td><button type="submit">Post Reply</button></td>
                        </tr>
                    </table>
                    <?php if (
                        !empty($error) &&
                        isset($_POST["thread_id"]) &&
                        $_POST["thread_id"] == $thread["id"]
                    ): ?>
                        <p class="error"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Replies -->
            <div class="replies">
                <?php foreach (
                    array_slice($thread["replies"], -5)
                    as $reply
                ): ?>
                <div class="reply">
                    <div class="post-header">
                        <span class="post-name"><?= htmlspecialchars(
                            $reply["name"]
                        ) ?></span>
                        <span class="post-id">No. <?= $reply["id"] ?></span>
                        <span class="post-date"><?= date(
                            "Y/m/d H:i:s",
                            $reply["timestamp"]
                        ) ?></span>
                    </div>
                    <div class="post-content">
                        <div class="post-message">
                            <?= nl2br(htmlspecialchars($reply["message"])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (count($thread["replies"]) > 5): ?>
                    <div class="more-replies"><?= count($thread["replies"]) -
                        5 ?> more replies...</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <footer id="footer">
        <p>Openchan /b/ - Page generated at <?= date("Y-m-d H:i:s") ?></p>
    </footer>
</body>
</html>
