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
            true
        );
        $submissions[] = $submission;
        file_put_contents(
            $config["submissions_file"],
            json_encode($submissions, JSON_PRETTY_PRINT)
        );

        // Update counters
        $count = file_exists("postcount.txt")
            ? (int) file_get_contents("postcount.txt") + 1
            : 1;
        file_put_contents("postcount.txt", $count);
        file_put_contents("lastupdated.txt", date("m-d-Y H:i:s")); // Updated this line

        // Update overchan counter
        $overchanCount = file_exists("../overchan/postcount.txt")
            ? (int) file_get_contents("../overchan/postcount.txt") + 1
            : 1;
        file_put_contents("../overchan/postcount.txt", $overchanCount);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="keywords" content="Photos, Viewer">
        <meta name="description" content="Photo Gallery">
        <meta name="author" content="Anon">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="../favicon.png">
        <title>Openchan /co/ - Coordinator Form</title>
        <script defer src="../js/userstyles.js"></script>
        <link rel="stylesheet" href="../styles/styles.css">
        <link rel="stylesheet" href="../styles/coordinator.css">
        <style>
            .success-popup {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background-color: #4CAF50;
                color: white;
                padding: 15px 25px;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                z-index: 1000;
                animation: fadeInOut 3s ease-in-out forwards;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .success-popup::before {
                content: "✓";
                font-weight: bold;
                font-size: 1.2em;
            }

            @keyframes fadeInOut {
                0% { opacity: 0; top: 0; }
                10% { opacity: 1; top: 20px; }
                90% { opacity: 1; top: 20px; }
                100% { opacity: 0; top: 0; }
            }

            body.dark .success-popup {
                background-color: #2E7D32;
            }
        </style>
    </head>
    <body>
        <header id="nav">
            <span class='left'>
                <a href="../">Home</a>
            </span>
            <span class="right">
                <a href="../co_mod.php">List All</a>
            </span>
        </header>

        <div id="head">
            <?php if ($success): ?>
                <div class="success-popup" id="successPopup">
                    Form submitted successfully!
                </div>
                <script>
                    setTimeout(() => {
                        const popup = document.getElementById('successPopup');
                        if (popup) popup.remove();
                    }, 3000);
                </script>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h1>Coordinator Form</h1>

            <form class="coordinator-form" method="post" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <td class="top">Name</td>
                        <td>
                            <input type="text" class="form-control"
                                name="sendername" placeholder="Name" required>
                        </td>
                    </tr>
                    <tr>
                        <td>Department</td>
                        <td>
                            <input type="text" class="form-control"
                                name="senderdep" placeholder="eg: PIT" required>
                        </td>
                    </tr>
                    <tr>
                        <td>Branch</td>
                        <td>
                            <input type="text" class="form-control"
                                name="senderbra" placeholder="eg: CSE" required>
                        </td>
                    </tr>
                    <tr>
                        <td>Division</td>
                        <td>
                            <input type="text" class="form-control"
                                name="senderdiv" placeholder="eg: 6A1" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="top">ID Card Image Upload</td>
                        <td>
                            <input type="file" name="fileToUpload" id="fileToUpload" class="form-control">
                            <p class="small">(Max 2MB, JPG/PNG/GIF only)</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="top">SUBMIT</td>
                        <td>
                            <button class="btn" type="submit" name="submit">Submit</button>
                            <?php if (
                                isset($_GET["token"]) &&
                                $_GET["token"] === "manage420"
                            ): ?>
                                <button class="btn" type="submit" name="submitasmod">Mod Submit</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </form>
        </div>

        <div id="content">
            <?php
            $submissions = json_decode(
                file_get_contents($config["submissions_file"]),
                true
            );
            if (!empty($submissions)): ?>
                <div class="submissions-list">
                    <h2>Recent Submissions</h2>
                    <table class="form-table">
                        <?php foreach (
                            array_slice($submissions, 0, 5)
                            as $sub
                        ): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars(
                                    $sub["name"]
                                ) ?></strong><br>
                                <?= htmlspecialchars($sub["department"]) ?> /
                                <?= htmlspecialchars($sub["branch"]) ?> /
                                <?= htmlspecialchars($sub["division"]) ?>
                                <br>
                                <small><?= date(
                                    "m-d-y h:i a",
                                    $sub["timestamp"]
                                ) ?></small>
                                <?php if (isset($sub["file"])): ?>
                                    <br><a href="data/<?= htmlspecialchars(
                                        $sub["file"]
                                    ) ?>" target="_blank">View ID Card</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif;
            ?>
        </div>

        <footer id="footer">
            <p>Openchan &copy; <?= date("Y") ?></p>
        </footer>
    </body>
</html>
