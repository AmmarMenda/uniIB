<?php
// Configuration
$boards = [
    "b" => ["name" => "/b/ Random", "description" => "Off-topic discussion"],
    "co" => [
        "name" => "Coordinator Form",
        "description" =>
            "Form for volunteering for Coordination of University events",
    ],
    "ev" => [
        "name" => "Events",
        "description" =>
            "Discussions around events occurring in the university",
    ],
];
// Calculate total posts
$totalPosts = 0;
foreach ($boards as $dir => $board) {
    if (file_exists("$dir/postcount.txt")) {
        $totalPosts += (int) file_get_contents("$dir/postcount.txt");
    }
}
file_put_contents("overchan/postcount.txt", $totalPosts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>uniIB</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://unpkg.com/chota">
    <link rel="stylesheet" href="styles/styles.css">  <!-- Custom overrides -->
    <script defer src="js/userstyles.js"></script>
</head>
<body>
  <header>
    <div class="row">
      <div class="col-6">
        <!-- Navigation -->
      </div>
      <div class="col-6 text-right">
        <!-- Theme selector -->
      </div>
    </div>
  </header>

  <div class="container">
    <section>
      <h2>About uniIB</h2>
      <p>

          uniIB is a simple image-based bulletin board where anyone can post comments and share images. There are boards dedicated to a variety of topics, from Random,Events etc. Users do not need to register an account before participating in the community. Feel free to click on a board below that interests you and jump right in!</p>
<p>
          Be sure to familiarize yourself with the <a href=./static/rules.php>Rules</a> before posting, and read the FAQ if you wish to learn more about how to use the site.
</p>
    </section>

    <section class="text-center">
      <h3>uniIB</h3>
      <img src="static/openchan3.gif" alt="Spinning Globe" class="globe">
    </section>

    <section>
      <h2>Board List</h2>
      <div class="row board-card-row">
        <?php foreach ($boards as $dir => $board): ?>
          <div class="col board-card">
            <div class="card">
              <div class="img-container">
                <img
                  src="static/<?= htmlspecialchars($dir) ?>.jpeg"
                  alt="<?= htmlspecialchars($board["name"]) ?> preview"
                  class="board-preview-img"
                  onerror="this.parentElement.style.display='none';"
                >
              </div>
              <header style="margin: .7em 0 .45em 0;">
                <a href="<?= htmlspecialchars($dir) ?>/"
                   style="font-size:1.3em; font-weight:bold; text-decoration:none;">
                  <?= htmlspecialchars($board["name"]) ?>
                </a>
              </header>
              <div class="card-body">
                <p><?= htmlspecialchars($board["description"]) ?></p>
                <div class="board-meta">
                  <strong>Posts:</strong>
                  <?= file_exists("$dir/postcount.txt")
                      ? htmlspecialchars(
                          file_get_contents("$dir/postcount.txt"),
                      )
                      : "0" ?>
                  <br>
                  <strong>Updated:</strong>
                  <?= file_exists("$dir/lastupdated.txt")
                      ? htmlspecialchars(
                          file_get_contents("$dir/lastupdated.txt"),
                      )
                      : "Never" ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section>
      <h2>Stats</h2>
      <p><strong>Total Posts:</strong> <?= htmlspecialchars($totalPosts) ?></p>
    </section>
  </div>

  <footer class="text-center">
    <p>uniIB &copy; <?= date("Y") ?></p>
  </footer>
</body>
</html>
