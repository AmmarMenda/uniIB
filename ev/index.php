<?php
// Configuration
$config = [
    "board_title" => "/ev/ Events",
    "description" => "Discussions around events in university",
    "posts_per_page" => 10,
    "data_dir" => "posts/",
    "reports_dir" => "reports/",
    "allowed_file_types" => ["image/jpeg", "image/png", "image/gif"],
    "max_file_size" => 2 * 1024 * 1024, // 2MB
    "max_threads" => 100,
    "max_replies" => 200,
];
// Ensure directories exist
foreach (["data_dir", "reports_dir"] as $d) {
    if (!file_exists($config[$d])) {
        mkdir($config[$d], 0755, true);
    }
}

// Get current reply form to show (if any)
$show_reply_form = isset($_GET["reply_to"]) ? (int) $_GET["reply_to"] : null;

// Handle form submission
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = substr(trim($_POST["name"] ?? "Anonymous"), 0, 50);
    $subject = substr(trim($_POST["subject"] ?? ""), 0, 100);
    $message = trim($_POST["message"] ?? "");
    $thread_id = (int) ($_POST["thread_id"] ?? 0);

    if (empty($message)) {
        $error = "Message cannot be empty";
    }

    $file_name = "";
    if (
        !$thread_id &&
        isset($_FILES["file"]) &&
        $_FILES["file"]["error"] === UPLOAD_ERR_OK
    ) {
        $file = $_FILES["file"];
        if ($file["size"] > $config["max_file_size"]) {
            $error = "File too large (max 2MB)";
        } elseif (!in_array($file["type"], $config["allowed_file_types"])) {
            $error = "Invalid file type";
        } else {
            $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
            $file_name = uniqid() . "." . $ext;
            if (
                !move_uploaded_file(
                    $file["tmp_name"],
                    $config["data_dir"] . $file_name,
                )
            ) {
                $error = "Failed to upload file";
            }
        }
    }

    if (!$error) {
        $post_id = time();
        $post = [
            "id" => $post_id,
            "name" => $name,
            "subject" => $subject,
            "message" => $message,
            "file" => $file_name,
            "timestamp" => $post_id,
            "ip" => $_SERVER["REMOTE_ADDR"],
            "is_thread" => !$thread_id,
            "thread_id" => $thread_id ?: $post_id,
        ];

        if ($thread_id) {
            $thread_file = $config["data_dir"] . $thread_id . ".json";
            if (!file_exists($thread_file)) {
                $error = "Thread not found";
            } else {
                $replies =
                    count(
                        array_filter(
                            scandir($config["data_dir"]),
                            fn($f) => str_ends_with($f, ".json") &&
                                json_decode(
                                    file_get_contents($config["data_dir"] . $f),
                                    true,
                                )["thread_id"] ??
                                0 === $thread_id,
                        ),
                    ) - 1;
                if ($replies >= $config["max_replies"]) {
                    $error = "Thread full (max {$config["max_replies"]} replies)";
                }
            }
        }

        if (!$error) {
            file_put_contents(
                $config["data_dir"] . $post_id . ".json",
                json_encode($post),
            );
            header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
            exit();
        }
    }
}

// Load threads
$threads = [];
if (is_dir($config["data_dir"])) {
    $files = scandir($config["data_dir"], SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== "json") {
            continue;
        }
        $data = json_decode(
            file_get_contents($config["data_dir"] . $file),
            true,
        );
        if (
            json_last_error() !== JSON_ERROR_NONE ||
            empty($data["is_thread"])
        ) {
            continue;
        }
        $threads[] = $data;
        if (count($threads) >= $config["max_threads"]) {
            break;
        }
    }
}

// Load replies
foreach ($threads as &$thread) {
    $thread["replies"] = [];
    foreach (scandir($config["data_dir"]) as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== "json") {
            continue;
        }
        $r = json_decode(file_get_contents($config["data_dir"] . $file), true);
        if (
            isset($r["thread_id"]) &&
            $r["thread_id"] === $thread["id"] &&
            empty($r["is_thread"])
        ) {
            $thread["replies"][] = $r;
        }
    }
    usort(
        $thread["replies"],
        fn($a, $b) => $a["timestamp"] <=> $b["timestamp"],
    );
}
unset($thread);

// Update counts
$total_posts =
    count($threads) +
    array_sum(array_map(fn($t) => count($t["replies"]), $threads));
file_put_contents("postcount.txt", $total_posts);
file_put_contents("lastupdated.txt", date("Y-m-d H:i:s"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($config["board_title"]) ?> – uniIB</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://unpkg.com/98.css" />
  <link rel="stylesheet" href="../styles/board.css" />
</head>
<body class="windowed">
  <div class="title-bar">
    <div class="title-bar-text"><?= htmlspecialchars(
        $config["board_title"],
    ) ?></div>
    <div class="title-bar-controls">
      <button aria-label="Minimize"></button>
      <button aria-label="Maximize"></button>
      <button aria-label="Close"></button>
    </div>
  </div>

  <div class="window" style="margin:1em; padding:1em;">
    <div class="toolbar" style="display:flex; justify-content:space-between;">
      <div>
        <a href="../" class="toolbar-button">Home</a> &gt;
        <a href="./" class="toolbar-button"><?= htmlspecialchars(
            $config["board_title"],
        ) ?></a>
      </div>
      <div>
        <a href="../mod.php" class="toolbar-button">Mod Panel</a>
      </div>
    </div>

    <fieldset class="field-set">
      <legend>Board Info</legend>
      <h1 style="margin:0;"><?= htmlspecialchars($config["board_title"]) ?></h1>
      <p style="margin:0;"><?= htmlspecialchars($config["description"]) ?></p>
      <p style="margin:0;">Total Posts: <?= $total_posts ?> (<?= count(
     $threads,
 ) ?> threads)</p>
    </fieldset>

    <fieldset class="field-set">
      <legend>Create New Thread</legend>
      <form action="" method="post" enctype="multipart/form-data">
        <table>
          <tr><td>Name:</td><td><input type="text" name="name" maxlength="50" placeholder="Anonymous"></td></tr>
          <tr><td>Subject:</td><td><input type="text" name="subject" maxlength="100"></td></tr>
          <tr><td>Message:</td><td><textarea name="message" required></textarea></td></tr>
          <tr><td>File:</td><td><input type="file" name="file"></td></tr>
          <tr><td></td><td><button class="default" type="submit">Create Thread</button></td></tr>
        </table>
        <?php if (
            !empty($error) &&
            empty($_POST["thread_id"])
        ): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      </form>
    </fieldset>

    <?php foreach ($threads as $thread): ?>
      <fieldset class="field-set">
        <legend>Thread No. <?= $thread["id"] ?></legend>
        <div class="window-body">
          <div class="post-header">
            <span class="post-subject"><?= htmlspecialchars(
                $thread["subject"] ?: "",
            ) ?></span>
            <span class="post-name"><?= htmlspecialchars(
                $thread["name"] ?: "Anonymous",
            ) ?></span>
            <span class="post-date"><?= date(
                "Y/m/d H:i:s",
                $thread["timestamp"],
            ) ?></span>
            <span class="post-id">No. <?= $thread["id"] ?></span>
          </div>
          <?php if ($thread["file"]): ?>
            <div class="post-file">
              <a href="<?= htmlspecialchars(
                  $config["data_dir"] . $thread["file"],
              ) ?>" target="_blank">
                <img src="<?= htmlspecialchars(
                    $config["data_dir"] . $thread["file"],
                ) ?>" class="thumbnail" alt="Attachment">
              </a>
            </div>
          <?php endif; ?>
          <div class="post-message"><?= nl2br(
              htmlspecialchars($thread["message"]),
          ) ?></div>
          <div class="post-actions">
            <?php if ($show_reply_form === $thread["id"]): ?>
              <a href="<?= strtok(
                  $_SERVER["REQUEST_URI"],
                  "?",
              ) ?>" class="default">Cancel Reply</a>
            <?php else: ?>
              <a href="?reply_to=<?= $thread["id"] ?>" class="default">Reply</a>
            <?php endif; ?>
            <form method="post" action="report.php" style="display:inline;">
              <input type="hidden" name="post_id" value="<?= $thread["id"] ?>">
              <button class="report-btn" type="submit">Report</button>
            </form>
            <span class="reply-count">Replies: <?= count(
                $thread["replies"] ?? [],
            ) ?></span>
          </div>
          <?php if ($show_reply_form === $thread["id"]): ?>
            <div class="reply-form">
              <form action="" method="post">
                <input type="hidden" name="thread_id" value="<?= $thread[
                    "id"
                ] ?>">
                <table>
                  <tr><td>Name:</td><td><input type="text" name="name" maxlength="50" placeholder="Anonymous"></td></tr>
                  <tr><td>Message:</td><td><textarea name="message" required></textarea></td></tr>
                  <tr><td></td><td><button class="default" type="submit">Post Reply</button><a href="<?= strtok(
                      $_SERVER["REQUEST_URI"],
                      "?",
                  ) ?>" class="default" style="margin-left:8px;">Cancel</a></td></tr>
                </table>
                <?php if (
                    !empty($error) &&
                    ($_POST["thread_id"] ?? "") == $thread["id"]
                ): ?><p class="error"><?= htmlspecialchars(
    $error,
) ?></p><?php endif; ?>
              </form>
            </div>
          <?php endif; ?>
          <?php foreach (array_slice($thread["replies"], -5) as $reply): ?>
            <div class="reply">
              <div class="post-header">
                <span class="post-name"><?= htmlspecialchars(
                    $reply["name"] ?: "Anonymous",
                ) ?></span>
                <span class="post-date"><?= date(
                    "Y/m/d H:i:s",
                    $reply["timestamp"],
                ) ?></span>
                <span class="post-id">No. <?= htmlspecialchars(
                    $reply["id"],
                ) ?></span>
              </div>
              <div class="post-message"><?= nl2br(
                  htmlspecialchars($reply["message"]),
              ) ?></div>
              <div class="post-actions">
                <form method="post" action="report.php" style="display:inline;">
                  <input type="hidden" name="post_id" value="<?= $reply[
                      "id"
                  ] ?>">
                  <button class="report-btn" type="submit">Report</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (count($thread["replies"]) > 5): ?>
            <div class="more-replies"><?= count($thread["replies"]) -
                5 ?> more replies…</div>
          <?php endif; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>

    <footer style="margin-top:1em; text-align:center;">
      <p>uniIB <?= htmlspecialchars(
          $config["board_title"],
      ) ?> – Generated <?= date("Y-m-d H:i:s") ?></p>
    </footer>
  </div>
</body>
</html>
