<?php
// Configuration
$boards = [
    "b" => [
        "name" => "/b/ Random",
        "description" => "Off-topic discussion",
    ],
    "co" => [
        "name" => "Coordinator Form",
        "description" =>
            "Form for volunteering for Coordination of University events",
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
        <meta name="keywords" content="Imageboard, Photos, Viewer">
        <meta name="description" content="Openchan Image Board">
        <meta name="author" content="Openchan">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="favicon.ico">
        <title>Openchan</title>
        <script defer src="js/userstyles.js"></script>
        <link rel="stylesheet" href="styles/styles.css">
    </head>
    <body>
        <header id="nav">
            <span class='left'>
                <?php include "nav.php"; ?>
            </span>
            <span class="right">
                <select id="userstyleselecter" onchange="userstyle();">
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                    <option value="yotsuba">Yotsuba</option>
                    <option value="yotsuba-b">Yotsuba B</option>
                </select>
            </span>
        </header>

        <main id="content">
            <div id="head">
                <div class="logo-container">
                           <a href="index.php">
                               <img src="static/openchan3.gif" alt="Openchan Logo" class="logo">
                           </a>
                </a>
                </div>

                <div class="board-info">
                    <section class="about">
                        <h2>ABOUT uniIB</h2>
                        <p>This is website is for open discussions for university</p>
                    </section>

                    <section class="board-list">
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Posts</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($boards as $dir => $board): ?>
                                <tr>
                                    <td><a href="<?= htmlspecialchars(
                                        $dir
                                    ) ?>/"><?= htmlspecialchars(
    $board["name"]
) ?></a></td>
                                    <td><?= htmlspecialchars(
                                        $board["description"]
                                    ) ?></td>
                                    <td><?= file_exists("$dir/postcount.txt")
                                        ? htmlspecialchars(
                                            file_get_contents(
                                                "$dir/postcount.txt"
                                            )
                                        )
                                        : "0" ?></td>
                                    <td><?= file_exists("$dir/lastupdated.txt")
                                        ? htmlspecialchars(
                                            file_get_contents(
                                                "$dir/lastupdated.txt"
                                            )
                                        )
                                        : "Never" ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>

                    <section class="stats">
                        <h2>Stats</h2>
                        <p><strong>Total Posts:</strong> <?= htmlspecialchars(
                            $totalPosts
                        ) ?></p>
                    </section>
                </div>
            </div>
        </main>

        <footer id="footer">
            <p>uniIB &copy; <?= date("Y") ?></p>
        </footer>
    </body>
</html>
