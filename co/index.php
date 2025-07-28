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
    <link rel="stylesheet" href="https://unpkg.com/chota">
    <link rel="stylesheet" href="../styles/coordinator.css">
    <script defer src="../js/userstyles.js"></script>
</head>
<body>
    <header>
        <div class="row">
            <div class="col-6">
                <a href="../">Home</a> &gt;
                <span>/co/ - Coordinator Form</span>
            </div>
            <div class="col-6 text-right">
                <a href="../co_mod.php">List All</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="card bg-success text-light" style="margin-bottom:1rem;">
                <header>Success</header>
                <div class="card-body">
                    <p>✓ Form submitted successfully!</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="card bg-error text-light" style="margin-bottom:1rem;">
                <header>Error</header>
                <div class="card-body">
                    <?php foreach ($errors as $error): ?>
                        <p>⚠ <?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Form -->
        <section>
            <h2>Coordinator Application Form</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-3"><label>Name:</label></div>
                    <div class="col-9"><input type="text" name="sendername" placeholder="Full Name" required></div>
                </div>
                <div class="row">
                    <div class="col-3"><label>Department:</label></div>
                    <div class="col-9"><input type="text" name="senderdep" placeholder="e.g., PIT" required></div>
                </div>
                <div class="row">
                    <div class="col-3"><label>Branch:</label></div>
                    <div class="col-9"><input type="text" name="senderbra" placeholder="e.g., CSE" required></div>
                </div>
                <div class="row">
                    <div class="col-3"><label>Division:</label></div>
                    <div class="col-9"><input type="text" name="senderdiv" placeholder="e.g., 6A1" required></div>
                </div>
                <div class="row">
                    <div class="col-3"><label>ID Card:</label></div>
                    <div class="col-9">
                        <input type="file" name="fileToUpload" id="fileToUpload">
                        <small class="text-grey">Max 2MB, JPG/PNG/GIF only</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3"></div>
                    <div class="col-9">
                        <button class="button primary" type="submit" name="submit">Submit Application</button>
                        <?php if (
                            isset($_GET["token"]) &&
                            $_GET["token"] === "manage420"
                        ): ?>
                            <button class="button outline" type="submit" name="submitasmod" style="margin-left:8px;">Mod Submit</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </section>

        <!-- Recent Submissions -->
        <?php if (!empty($submissions)): ?>
            <section>
                <h2>Recent Submissions</h2>
                <?php foreach (array_slice($submissions, -5) as $sub): ?>
                    <div class="card">
                        <header>
                            <div class="row">
                                <div class="col">
                                    <strong><?= htmlspecialchars(
                                        $sub["name"],
                                    ) ?></strong>
                                </div>
                                <div class="col text-right">
                                    <small class="text-grey"><?= date(
                                        "m/d/y H:i",
                                        $sub["timestamp"],
                                    ) ?></small>
                                </div>
                            </div>
                        </header>
                        <div class="card-body">
                            <p>
                                <?= htmlspecialchars($sub["department"]) ?> /
                                <?= htmlspecialchars($sub["branch"]) ?> /
                                <?= htmlspecialchars($sub["division"]) ?>
                                <?php if (isset($sub["file"])): ?>
                                    <br><a href="data/<?= htmlspecialchars(
                                        $sub["file"],
                                    ) ?>" target="_blank">View ID Card</a>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <footer class="text-center">
        <p>uniIB /co/ – Generated <?= date("Y-m-d H:i:s") ?></p>
    </footer>
</body>
</html>
