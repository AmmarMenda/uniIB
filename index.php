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
    <!-- Windows 98 styles -->
    <link rel="stylesheet" href="https://unpkg.com/98.css" />
    <!-- Your custom overrides -->
    <link rel="stylesheet" href="styles/styles.css" />
    <script defer src="js/userstyles.js"></script>
</head>
<body class="windowed">
  <div class="title-bar">
    <div class="title-bar-text">uniIB Image Board</div>
    <div class="title-bar-controls">
      <button aria-label="Minimize"></button>
      <button aria-label="Maximize"></button>
      <button aria-label="Close"></button>
    </div>
  </div>

  <div class="window" style="margin:1em; padding:1em;">
    <!-- Navigation -->
    <div class="toolbar" style="display:flex; justify-content:space-between;">

    </div>

    <!-- About Panel -->
    <fieldset class="field-set">
      <legend>About uniIB</legend>
      <p>This website is for open discussions for university.</p>
    </fieldset>

    <!-- Spinning Globe GIF (Centered in the Middle) -->
    <fieldset class="field-set globe-panel">
      <legend>uniIB</legend>
      <div style="text-align:center;">
        <img src="static/openchan3.gif" alt="Spinning Globe" class="globe">
      </div>
    </fieldset>

    <!-- Board List Panel -->
    <fieldset class="field-set">
      <legend>Board List</legend>
      <table class="cell-border">
        <thead>
          <tr>
            <th>Title</th><th>Description</th><th>Posts</th><th>Updated</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($boards as $dir => $board): ?>
          <tr>
            <td><a href="<?= htmlspecialchars($dir) ?>/"><?= htmlspecialchars(
    $board["name"],
) ?></a></td>
            <td><?= htmlspecialchars($board["description"]) ?></td>
            <td>
              <?= file_exists("$dir/postcount.txt")
                  ? htmlspecialchars(file_get_contents("$dir/postcount.txt"))
                  : "0" ?>
            </td>
            <td>
              <?= file_exists("$dir/lastupdated.txt")
                  ? htmlspecialchars(file_get_contents("$dir/lastupdated.txt"))
                  : "Never" ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </fieldset>

    <!-- Stats Panel -->
    <fieldset class="field-set">
      <legend>Stats</legend>
      <p><strong>Total Posts:</strong> <?= htmlspecialchars($totalPosts) ?></p>
    </fieldset>

    <!-- Footer -->
    <footer style="margin-top:1em; text-align:center;">
      <p>uniIB &copy; <?= date("Y") ?></p>
    </footer>
  </div>
</body>
</html>
