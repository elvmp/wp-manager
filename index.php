<?php
session_start();

$logFile = __DIR__ . '/installer.log'; // Log file location

function writeLog($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

$error = ""; // To store error messages
$success = ""; // To store success messages

// Define the WP-CLI cache directory
$wpCliCacheDir = __DIR__ . '/.wp-cli/cache';
if (!file_exists($wpCliCacheDir)) {
    mkdir($wpCliCacheDir, 0755, true);
    writeLog("Created WP-CLI cache directory: $wpCliCacheDir");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mysqlUser = $_POST['mysql_user'];
    $mysqlPass = $_POST['mysql_pass'];
    $titles = $_POST['titles'];
    $numInstances = (int) $_POST['num_instances'];
    $instances = [];

    writeLog("Starting WordPress installation for $numInstances instance(s).");

    $mysqli = new mysqli('localhost', $mysqlUser, $mysqlPass);

    if ($mysqli->connect_error) {
        $error = "MySQL Connection Failed: " . $mysqli->connect_error;
        writeLog($error);
    } else {
        foreach ($titles as $title) {
            $titleSanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($title));
            $dbName = "wp_inst_" . $titleSanitized;
            $adminUser = $titleSanitized . "_admin";
            $adminPass = bin2hex(random_bytes(8)); // Generate a random password
            $instanceDir = __DIR__ . "/wordpress_instances/" . $titleSanitized;


            $installUrl = "http://192.168.0.15/wordpress_instances/" . $titleSanitized;

            // Check if the database already exists
            $result = $mysqli->query("SHOW DATABASES LIKE '$dbName'");
            if ($result && $result->num_rows > 0) {
                $error = "The database <strong>$dbName</strong> already exists. Please use a different title.";
                writeLog($error);
                break; // Stop further processing
            }

            if (!$mysqli->query("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
                $error = "Failed to create database <strong>$dbName</strong>: " . $mysqli->error;
                writeLog($error);
                break; // Stop further processing
            }
            writeLog("Database $dbName created successfully.");

            if (!file_exists($instanceDir)) {
                mkdir($instanceDir, 0755, true);
                writeLog("Instance directory $instanceDir created.");

                $wpCliPath = '/usr/local/bin/wp'; // Adjust to your WP-CLI location
                $phpPath = '/usr/bin/php'; // Adjust to your PHP binary location

                $downloadCommand = sprintf(
                    "WP_CLI_CACHE_DIR=%s %s %s core download --path=%s --locale=en_US",
                    escapeshellarg($wpCliCacheDir),
                    escapeshellarg($phpPath),
                    escapeshellarg($wpCliPath),
                    escapeshellarg($instanceDir)
                );

                $downloadOutput = shell_exec($downloadCommand . " 2>&1");
                writeLog("WP-CLI Download Command: $downloadCommand");
                writeLog("WP-CLI Download Output for $title: $downloadOutput");

                if (strpos($downloadOutput, 'Error:') !== false) {
                    $error = "Failed to download WordPress for <strong>$title</strong>. Output: $downloadOutput";
                    writeLog($error);
                    break;
                }
            }

            if (!file_exists("$instanceDir/wp-config-sample.php")) {
                $error = "WordPress files are missing for <strong>$title</strong>. Check permissions or retry.";
                writeLog($error);
                break;
            }

            $configFile = file_get_contents("$instanceDir/wp-config-sample.php");
            $configFile = str_replace('database_name_here', $dbName, $configFile);
            $configFile = str_replace('username_here', $mysqlUser, $configFile);
            $configFile = str_replace('password_here', $mysqlPass, $configFile);
            file_put_contents("$instanceDir/wp-config.php", $configFile);
            writeLog("Generated wp-config.php for $title.");

            $installCommand = sprintf(
                "WP_CLI_CACHE_DIR=%s %s %s core install --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s --path=%s --locale=en_US --skip-email",
                escapeshellarg($wpCliCacheDir),
                escapeshellarg($phpPath),
                escapeshellarg($wpCliPath),
                escapeshellarg($installUrl),
                escapeshellarg($title),
                escapeshellarg($adminUser),
                escapeshellarg($adminPass),
                escapeshellarg('admin@example.com'),
                escapeshellarg($instanceDir)
            );

            $installOutput = shell_exec($installCommand . " 2>&1");
            writeLog("WP-CLI Install Command: $installCommand");
            writeLog("WP-CLI Install Output for $title: $installOutput");

            if (strpos($installOutput, 'Error:') !== false) {
                $error = "WordPress setup failed for <strong>$title</strong>. Output: $installOutput";
                writeLog($error);
                break;
            }

            if (!file_exists("$instanceDir/wp-config.php")) {
                $error = "wp-config.php is missing for <strong>$title</strong>. Installation incomplete.";
                writeLog($error);
                break;
            }

            $instances[] = [
                'title' => $title,
                'directory' => $instanceDir,
                'admin_user' => $adminUser,
                'admin_pass' => $adminPass,
                'url' => $installUrl,
                'database' => $dbName,
            ];
        }

        if (!$error) {
            $success = "All WordPress instances have been installed successfully.";
            $_SESSION['instances'] = $instances;
            writeLog("All instances installed successfully.");
            header('Location: panel.php');
            exit;
        }
    }

    $mysqli->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Multi-Installer</title>

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-dark text-light">
    <div class="container py-5">
        <div class="card bg-secondary text-light shadow-lg p-4">
            <h1 class="text-center mb-4">WordPress Multi-Installer</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <?= $dependencyStatus ?>
            </div>

            <?php if (!empty($missingDependencies)): ?>
                <div class="mb-4">
                    <?= $osInstructions ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label for="mysql_user" class="form-label">MySQL Username</label>
                    <input type="text" id="mysql_user" name="mysql_user" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="mysql_pass" class="form-label">MySQL Password</label>
                    <div class="input-group">
                        <input type="password" id="mysql_pass" name="mysql_pass" class="form-control" required>
                        <button type="button" id="togglePassword" class="btn btn-outline-light"><i class="material-icons">visibility</i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="num_instances" class="form-label">Number of Instances</label>
                    <input type="number" id="num_instances" name="num_instances" class="form-control" required>
                </div>
                <div id="titles-container" class="mb-3"></div>
                <button type="submit" class="btn btn-success w-100">Install WordPress</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordField = document.getElementById('mysql_pass');
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="material-icons">visibility</i>' : '<i class="material-icons">visibility_off</i>';
        });

        document.getElementById('num_instances').addEventListener('input', function () {
            const container = document.getElementById('titles-container');
            container.innerHTML = '';
            const num = parseInt(this.value) || 0;
            for (let i = 0; i < num; i++) {
                const div = document.createElement('div');
                div.className = 'mb-3';
                div.innerHTML = `
                    <label class="form-label">Title for Instance ${i + 1}</label>
                    <input type="text" name="titles[]" class="form-control" required>
                `;
                container.appendChild(div);
            }
        });
    </script>
</body>
</html>
