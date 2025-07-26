<?php
// Enable error reporting
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

// Configuration
$config = [
    "data_dir" => "data/",
    "submissions_file" => "submissions.json",
    "allowed_types" => ["jpg", "jpeg", "png", "gif"],
    "max_size" => 2 * 1024 * 1024, // 2MB
];

// Create directories if needed
if (!file_exists($config["data_dir"])) {
    mkdir($config["data_dir"], 0755, true);
}

// Initialize submissions file if it doesn't exist
if (!file_exists($config["submissions_file"])) {
    file_put_contents($config["submissions_file"], json_encode([]));
}

// Handle form submission
$errors = [];
$success = false;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit"])) {
    $submission = [
        "name" => $_POST["sendername"] ?? "",
        "department" => $_POST["senderdep"] ?? "",
        "branch" => $_POST["senderbra"] ?? "",
        "division" => $_POST["senderdiv"] ?? "",
        "timestamp" => time(),
        "ip" => $_SERVER["REMOTE_ADDR"],
    ];

    // Handle file upload
    if (!empty($_FILES["fileToUpload"]["name"])) {
        $file = $_FILES["fileToUpload"];
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $filename = uniqid() . "." . $ext;
        $target = $config["data_dir"] . $filename;

        // Validate file
        if (!in_array($ext, $config["allowed_types"])) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
        } elseif ($file["size"] > $config["max_size"]) {
            $errors[] = "File is too large (max 2MB).";
        } elseif (!move_uploaded_file($file["tmp_name"], $target)) {
            $errors[] = "Error uploading file.";
        } else {
            $submission["file"] = $filename;
        }
    }

    if (empty($errors)) {
        // Load existing submissions
        $submissions = json_decode(
            file_get_contents($config["submissions_file"]),
            true,
        );
        $submissions[] = $submission;
        file_put_contents(
            $config["submissions_file"],
            json_encode($submissions, JSON_PRETTY_PRINT),
        );

        // Update counters
        $count = file_exists("postcount.txt")
            ? (int) file_get_contents("postcount.txt") + 1
            : 1;
        file_put_contents("postcount.txt", $count);
        file_put_contents("lastupdated.txt", date("Y-m-d H:i:s"));

        // Update overchan counter
        $overchanCount = file_exists("../overchan/postcount.txt")
            ? (int) file_get_contents("../overchan/postcount.txt") + 1
            : 1;
        file_put_contents("../overchan/postcount.txt", $overchanCount);
        $success = true;
    }
}

// Load submissions for display
$submissions =
    json_decode(file_get_contents($config["submissions_file"]), true) ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>/co/ - Coordinator Form – uniIB</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!-- Windows 98 UI -->
    <link rel="stylesheet" href="https://unpkg.com/98.css" />
    <link rel="stylesheet" href="../styles/coordinator.css" />
    <script defer src="../js/userstyles.js"></script>
</head>
<body class="windowed">
    <!-- Title Bar -->
    <div class="title-bar">
        <div class="title-bar-text">/co/ - Coordinator Form</div>
        <div class="title-bar-controls">
            <button aria-label="Minimize"></button>
            <button aria-label="Maximize"></button>
            <button aria-label="Close"></button>
        </div>
    </div>

    <div class="window" style="margin:1em; padding:1em;">
        <!-- Toolbar -->
        <div class="toolbar" style="display:flex; justify-content:space-between;">
            <div>
                <a href="../" class="toolbar-button">Home</a> &gt;
                <span class="toolbar-button">/co/ - Coordinator Form</span>
            </div>
            <div>
                <a href="../co_mod.php" class="toolbar-button">List All</a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="window success-window" style="margin-bottom:1em;">
                <div class="title-bar">
                    <div class="title-bar-text">Success</div>
                </div>
                <div class="window-body">
                    <p>✓ Form submitted successfully!</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="window error-window" style="margin-bottom:1em;">
                <div class="title-bar">
                    <div class="title-bar-text">Error</div>
                </div>
                <div class="window-body">
                    <?php foreach ($errors as $error): ?>
                        <p>⚠ <?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Form -->
        <fieldset class="field-set">
            <legend>Coordinator Application Form</legend>
            <form method="post" enctype="multipart/form-data">
                <table>
                    <tr>
                        <td>Name:</td>
                        <td><input type="text" name="sendername" placeholder="Full Name" required></td>
                    </tr>
                    <tr>
                        <td>Department:</td>
                        <td><input type="text" name="senderdep" placeholder="e.g., PIT" required></td>
                    </tr>
                    <tr>
                        <td>Branch:</td>
                        <td><input type="text" name="senderbra" placeholder="e.g., CSE" required></td>
                    </tr>
                    <tr>
                        <td>Division:</td>
                        <td><input type="text" name="senderdiv" placeholder="e.g., 6A1" required></td>
                    </tr>
                    <tr>
                        <td>ID Card:</td>
                        <td>
                            <input type="file" name="fileToUpload" id="fileToUpload">
                            <div class="file-help">Max 2MB, JPG/PNG/GIF only</div>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <button class="default" type="submit" name="submit">Submit Application</button>
                            <?php if (
                                isset($_GET["token"]) &&
                                $_GET["token"] === "manage420"
                            ): ?>
                                <button class="default" type="submit" name="submitasmod" style="margin-left:8px;">Mod Submit</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </form>
        </fieldset>

        <!-- Recent Submissions -->
        <?php if (!empty($submissions)): ?>
            <fieldset class="field-set">
                <legend>Recent Submissions</legend>
                <div class="submissions-list">
                    <?php foreach (array_slice($submissions, -5) as $sub): ?>
                        <div class="submission-item">
                            <div class="submission-header">
                                <strong><?= htmlspecialchars(
                                    $sub["name"],
                                ) ?></strong>
                                <span class="submission-time"><?= date(
                                    "m/d/y H:i",
                                    $sub["timestamp"],
                                ) ?></span>
                            </div>
                            <div class="submission-details">
                                <?= htmlspecialchars($sub["department"]) ?> /
                                <?= htmlspecialchars($sub["branch"]) ?> /
                                <?= htmlspecialchars($sub["division"]) ?>
                                <?php if (isset($sub["file"])): ?>
                                    <a href="data/<?= htmlspecialchars(
                                        $sub["file"],
                                    ) ?>" target="_blank" class="id-link">View ID Card</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <!-- Footer -->
        <footer style="margin-top:1em; text-align:center;">
            <p>uniIB /co/ – Generated <?= date("Y-m-d H:i:s") ?></p>
        </footer>
    </div>
</body>
</html>
