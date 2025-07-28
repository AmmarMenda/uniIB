<?php
// Configuration
$config = [
    "board_title" => "/b/ - Random",
    "description" => "Off-topic discussion",
    "posts_per_page" => 10,
    "data_dir" => "posts/",
    "reports_dir" => "reports/",
    "allowed_file_types" => ["image/jpeg", "image/png", "image/gif"],
    "max_file_size" => 2 * 1024 * 1024,
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
                                (json_decode(
                                    file_get_contents($config["data_dir"] . $f),
                                    true,
                                )["thread_id"] ??
                                    0) ===
                                    $thread_id,
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

// Format post message with greentext
function formatMessage(string $msg): string
{
    $lines = explode("\n", htmlspecialchars($msg));
    foreach ($lines as &$line) {
        if (str_starts_with($line, "&gt;")) {
            $line = '<span class="greentext">' . $line . "</span>";
        }
    }
    return implode("<br>", $lines);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($config["board_title"]) ?> – uniIB</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://unpkg.com/chota">
  <link rel="stylesheet" href="../styles/board.css">
  <script defer src="../js/userstyles.js"></script>
</head>
<body>
  <header>
    <div class="row">
      <div class="col-6">
        <a href="../">Home</a> &gt;
        <a href="./"><?= htmlspecialchars($config["board_title"]) ?></a>
      </div>
      <div class="col-6 text-right">
        <a href="../mod.php">Mod Panel</a>
      </div>
    </div>
  </header>

  <div class="container">
    <div class="board-main-container">
      <!-- Board Info Card -->
      <div class="board-info-card">
        <h2>Board Info</h2>
        <h1><?= htmlspecialchars($config["board_title"]) ?></h1>
        <p><?= htmlspecialchars($config["description"]) ?></p>
        <p>Total Posts: <?= $total_posts ?> (<?= count($threads) ?> threads)</p>
      </div>

      <!-- Create New Thread Card -->
      <div class="create-thread-card">
        <h2>Create New Thread</h2>
        <form action="" method="post" enctype="multipart/form-data">
          <div class="row">
            <div class="col-3"><label>Name:</label></div>
            <div class="col-9"><input type="text" name="name" maxlength="50" placeholder="Anonymous"></div>
          </div>
          <div class="row">
            <div class="col-3"><label>Subject:</label></div>
            <div class="col-9"><input type="text" name="subject" maxlength="100"></div>
          </div>
          <div class="row">
            <div class="col-3"><label>Message:</label></div>
            <div class="col-9"><textarea name="message" required></textarea></div>
          </div>
          <div class="row">
            <div class="col-3"><label>File:</label></div>
            <div class="col-9"><input type="file" name="file"></div>
          </div>
          <div class="row">
            <div class="col-3"></div>
            <div class="col-9"><button class="button primary" type="submit">Create Thread</button></div>
          </div>
          <?php if (
              !empty($error) &&
              empty($_POST["thread_id"])
          ): ?><p class="text-error"><?= htmlspecialchars(
    $error,
) ?></p><?php endif; ?>
        </form>
      </div>
    </div>

    <?php foreach ($threads as $thread): ?>
      <div class="card">
        <header>Thread No. <?= $thread["id"] ?></header>
        <div class="card-body">
          <div class="row">
            <div class="col"><strong>Subject:</strong> <?= htmlspecialchars(
                $thread["subject"] ?: "",
            ) ?></div>
            <div class="col"><strong>Name:</strong> <?= htmlspecialchars(
                $thread["name"] ?: "Anonymous",
            ) ?></div>
            <div class="col"><strong>Date:</strong> <?= date(
                "Y/m/d H:i:s",
                $thread["timestamp"],
            ) ?></div>
            <div class="col"><strong>ID:</strong> No. <?= $thread["id"] ?></div>
          </div>
          <div class="post-content">
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
            <p><?= formatMessage($thread["message"]) ?></p>
          </div>
          <div class="post-actions row">
            <div class="col">
              <?php if ($show_reply_form === $thread["id"]): ?>
                <a href="<?= strtok(
                    $_SERVER["REQUEST_URI"],
                    "?",
                ) ?>" class="button outline">Cancel Reply</a>
              <?php else: ?>
                <a href="?reply_to=<?= $thread[
                    "id"
                ] ?>" class="button primary">Reply</a>
              <?php endif; ?>
              <form method="post" action="report.php" style="display:inline;">
                <input type="hidden" name="post_id" value="<?= $thread[
                    "id"
                ] ?>">
                <button class="button error" type="submit">Report</button>
              </form>
              <span>Replies: <?= count($thread["replies"] ?? []) ?></span>
            </div>
          </div>
          <?php if ($show_reply_form === $thread["id"]): ?>
            <form action="" method="post">
              <input type="hidden" name="thread_id" value="<?= $thread[
                  "id"
              ] ?>">
              <div class="row">
                <div class="col-3"><label>Name:</label></div>
                <div class="col-9"><input type="text" name="name" maxlength="50" placeholder="Anonymous"></div>
              </div>
              <div class="row">
                <div class="col-3"><label>Message:</label></div>
                <div class="col-9"><textarea name="message" required></textarea></div>
              </div>
              <div class="row">
                <div class="col-3"></div>
                <div class="col-9">
                  <button class="button primary" type="submit">Post Reply</button>
                  <a href="<?= strtok(
                      $_SERVER["REQUEST_URI"],
                      "?",
                  ) ?>" class="button outline" style="margin-left:8px;">Cancel</a>
                </div>
              </div>
              <?php if (
                  !empty($error) &&
                  ($_POST["thread_id"] ?? "") == $thread["id"]
              ): ?><p class="text-error"><?= htmlspecialchars(
    $error,
) ?></p><?php endif; ?>
            </form>
          <?php endif; ?>
          <?php foreach (array_slice($thread["replies"], -5) as $reply): ?>
            <div class="card">
              <header>
                <strong><?= htmlspecialchars(
                    $reply["name"] ?: "Anonymous",
                ) ?></strong>
                <span><?= date("Y/m/d H:i:s", $reply["timestamp"]) ?></span>
                <span>No. <?= htmlspecialchars($reply["id"]) ?></span>
              </header>
              <div class="card-body">
                <p><?= formatMessage($reply["message"]) ?></p>
                <form method="post" action="report.php" style="display:inline;">
                  <input type="hidden" name="post_id" value="<?= $reply[
                      "id"
                  ] ?>">
                  <button class="button error" type="submit">Report</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (count($thread["replies"]) > 5): ?>
            <p class="text-secondary"><?= count($thread["replies"]) -
                5 ?> more replies…</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <footer class="text-center">
    <p>uniIB <?= htmlspecialchars(
        $config["board_title"],
    ) ?> – Generated <?= date("Y-m-d H:i:s") ?></p>
  </footer>
</body>
</html>
