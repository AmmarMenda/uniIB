<?php
session_start();

// Configuration
$config = [
    "admin_username" => "batman",
    "admin_password" => "ammar007",
    "reports_dir" => __DIR__ . "/reports/",
    "backup_dir" => __DIR__ . "/backups/",
];

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Collect boards
$boards = [];
foreach (glob(__DIR__ . "/*", GLOB_ONLYDIR) as $dir) {
    $b = basename($dir);
    if (!in_array($b, ["reports", "backups", "overchan"])) {
        $boards[] = $b;
    }
}

// Logout
if (isset($_GET["logout"])) {
    session_unset();
    session_destroy();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], "?"));
    exit();
}

// Login
if (isset($_POST["login"])) {
    if (
        $_POST["username"] === $config["admin_username"] &&
        $_POST["password"] === $config["admin_password"]
    ) {
        $_SESSION["authenticated"] = true;
        $_SESSION["username"] = $config["admin_username"];
        header("Location: " . basename(__FILE__));
        exit();
    } else {
        $error_msg = "Invalid credentials";
    }
}

// Delete post logic (unchanged)...

// Dismiss report logic moved to dismiss_report.php

// Clear backups logic (unchanged)...

// Load reported posts
$reported_posts = [];
if (!empty($_SESSION["authenticated"])) {
    foreach (glob($config["reports_dir"] . "report_*.json") as $rf) {
        $r = json_decode(file_get_contents($rf), true);
        if (!$r || empty($r["board"])) {
            continue;
        }
        $pf = __DIR__ . "/{$r["board"]}/posts/{$r["post_id"]}.json";
        if (!file_exists($pf)) {
            continue;
        }
        $p = json_decode(file_get_contents($pf), true);
        $p["report_data"] = $r;
        $p["board"] = $r["board"];
        $reported_posts[] = $p;
    }
    usort(
        $reported_posts,
        fn($a, $b) => $b["report_data"]["reported_at"] <=>
            $a["report_data"]["reported_at"],
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Global Moderator Panel – uniIB</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://unpkg.com/chota">
  <link rel="stylesheet" href="styles/moderator.css">
</head>
<body class="<?php echo $_SESSION["theme"] ?? "light"; ?>">
  <header>
    <div class="container row">
      <div class="col-6"><a href="/">Home</a></div>
      <div class="col-6 text-right">
        <?php if (!empty($_SESSION["authenticated"])): ?>
          <a href="?logout" class="button outline small">Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="container">
    <?php if (!empty($error_msg)): ?>
      <article class="card bg-error text-light">
        <div class="card-body"><?= htmlspecialchars($error_msg) ?></div>
      </article>
    <?php endif; ?>
    <?php if (!empty($success_msg)): ?>
      <article class="card bg-success text-light">
        <div class="card-body"><?= htmlspecialchars($success_msg) ?></div>
      </article>
    <?php endif; ?>

    <?php if (empty($_SESSION["authenticated"])): ?>
      <section class="card">
        <header>Moderator Login</header>
        <div class="card-body">
            <form method="post">
              <div class="row">
                <div class="col-4"><label>Username</label></div>
                <div class="col-8"><input name="username" required></div>
              </div>
              <div class="row">
                <div class="col-4"><label>Password</label></div>
                <div class="col-8"><input type="password" name="password" required></div>
              </div>
              <button class="button primary" name="login" type="submit">Login</button>
            </form>
        </div>
      </section>
    <?php else: ?>
      <section>
        <h2>Reported Posts (<?= count($reported_posts) ?>)</h2>
        <?php if (empty($reported_posts)): ?>
          <p>No reports found.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Board</th><th>Post ID</th><th>Content</th>
                <th>Image</th><th>Reported At</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reported_posts as $post): ?>
              <tr>
                <td><span class="tag">/<?= htmlspecialchars(
                    $post["board"],
                ) ?>/</span></td>
                <td><?= htmlspecialchars(substr($post["id"], 0, 8)) ?>…</td>
                <td>
                  <strong><?= htmlspecialchars($post["name"]) ?></strong><br>
                  <?php if ($post["subject"]): ?>
                    <em><?= htmlspecialchars($post["subject"]) ?></em><br>
                  <?php endif; ?>
                  <?= nl2br(
                      htmlspecialchars(substr($post["message"], 0, 200)),
                  ) ?>
                  <?php if (strlen($post["message"]) > 200): ?>…<?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($post["file"])): ?>
                    <img src="<?= "{$post["board"]}/posts/{$post["file"]}" ?>" class="thumbnail" alt="">
                  <?php endif; ?>
                </td>
                <td><?= date(
                    "Y-m-d H:i",
                    $post["report_data"]["reported_at"],
                ) ?><br>
                    <?= htmlspecialchars(
                        $post["report_data"]["reported_by"],
                    ) ?></td>
                <td>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="delete_post" value="<?= "{$post["board"]}|{$post["id"]}" ?>">
                    <button class="button error small" onclick="return confirm('Delete?')">Delete</button>
                  </form>
                  <form method="post" action="dismiss_report.php" style="display:inline">
                    <input type="hidden" name="post_id" value="<?= $post[
                        "id"
                    ] ?>">
                    <input type="hidden" name="board"   value="<?= $post[
                        "board"
                    ] ?>">
                    <button class="button outline small">Dismiss</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>

      <section class="text-center">
        <form method="post" onsubmit="return confirm('Clear all backups?');">
          <button class="button outline">Clear Backups</button>
        </form>
      </section>
    <?php endif; ?>
  </main>

  <footer class="container text-center">
    <p>uniIB Moderator Panel © <?= date("Y") ?></p>
  </footer>
</body>
</html>
