<?php
session_start();
define('LOGIN_PASSWORD', 'REPLACE_ME');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if ($_POST['login_password'] === LOGIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = "Invalid password!";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    </head>
    <body class="bg-dark text-light d-flex justify-content-center align-items-center vh-100">
        <div class="container text-center">
            <h1 class="mb-4">WordPress Panel Login</h1>
            <?php if (!empty($login_error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            <form method="POST" class="w-50 mx-auto">
                <div class="mb-3">
                    <input type="password" name="login_password" class="form-control" placeholder="Enter Password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$instancesDir = __DIR__ . '/wordpress_instances';
$logFile = __DIR__ . '/installer.log'; // Log file location
$wpCliCacheDir = __DIR__ . '/.wp-cli/cache';
if (!file_exists($wpCliCacheDir)) {
    mkdir($wpCliCacheDir, 0755, true);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Created WP-CLI cache directory: $wpCliCacheDir\n", FILE_APPEND);
}
$instances = [];
$error = ""; // To store error messages
$success = ""; // To store success messages


function writeLog($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

if (is_dir($instancesDir)) {
    $dirs = array_filter(glob($instancesDir . '/*'), 'is_dir');

    foreach ($dirs as $dir) {
        $wpConfig = $dir . '/wp-config.php';
        if (file_exists($wpConfig)) {
            $configContent = file_get_contents($wpConfig);

            preg_match("/define\(\s*'DB_NAME',\s*'(.+?)'\s*\);/", $configContent, $dbNameMatch);
            $dbName = $dbNameMatch[1] ?? 'Unknown';

            preg_match("/define\(\s*'DB_USER',\s*'(.+?)'\s*\);/", $configContent, $dbUserMatch);
            $dbUser = $dbUserMatch[1] ?? 'Unknown';

            preg_match("/define\(\s*'DB_PASSWORD',\s*'(.+?)'\s*\);/", $configContent, $dbPassMatch);
            $dbPass = $dbPassMatch[1] ?? 'Unknown';

            $siteUrl = "http://REPLACE_ME/wordpress_instances/" . basename($dir);

            $instances[] = [
                'title' => getWordPressTitle($dir) ?: basename($dir),
                'directory' => $dir,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_pass' => $dbPass,
                'url' => trim($siteUrl),
            ];
            $instances[array_key_last($instances)]['admin_login_url'] = generateAdminLoginLink(
                $dir,
                $siteUrl,
                basename($dir)
            );

        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_instance'])) {
    $instanceDir = $_POST['instance_dir'];
    $dbName = $_POST['db_name'];

    writeLog("Attempting to delete instance: $instanceDir and database: $dbName");

    $mysqli = new mysqli('localhost', 'REPLACE_ME', 'REPLACE_ME');
    if ($mysqli->connect_error) {
        $error = "MySQL Connection Failed: " . $mysqli->connect_error;
        writeLog($error);
    } else {
        if (!$mysqli->query("DROP DATABASE $dbName")) {
            $error = "Failed to delete database: " . $mysqli->error;
            writeLog($error);
        } else {
            writeLog("Database $dbName deleted successfully.");
        }
    }

    function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($filePath) ? deleteDirectory($filePath) : unlink($filePath);
        }
        return rmdir($dir);
    }

    if (!deleteDirectory($instanceDir)) {
        $error = "Failed to delete instance directory: $instanceDir";
        writeLog($error);
    } else {
        writeLog("Instance directory $instanceDir deleted successfully.");
        $success = "Instance and database deleted successfully.";
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_instances'])) {
    $mysqlUser = $_POST['mysql_user'];
    $mysqlPass = $_POST['mysql_pass'];
    $titles = $_POST['titles'];
    $instancesCreated = [];

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
            $instanceDir = $instancesDir . "/" . $titleSanitized;
            $installUrl = "http://192.168.0.15/wordpress_instances/" . $titleSanitized;

            $result = $mysqli->query("SHOW DATABASES LIKE '$dbName'");
            if ($result && $result->num_rows > 0) {
                $error = "The database <strong>$dbName</strong> already exists. Please use a different title.";
                writeLog($error);
                break;
            }

            if (!$mysqli->query("CREATE DATABASE $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
                $error = "Failed to create database <strong>$dbName</strong>: " . $mysqli->error;
                writeLog($error);
                break;
            }

            writeLog("Database $dbName created successfully.");

            if (!file_exists($instanceDir)) {
                mkdir($instanceDir, 0755, true);
                writeLog("Instance directory $instanceDir created.");

                $wpCliPath = '/usr/local/bin/wp'; // Adjust WP-CLI path
                $phpPath = '/usr/bin/php'; // Adjust PHP path
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

            $instancesCreated[] = [
                'title' => $title,
                'directory' => $instanceDir,
                'admin_user' => $adminUser,
                'admin_pass' => $adminPass,
                'url' => $installUrl,
                'db_name' => $dbName,
            ];
        }

        if (!$error) {
            $success = "All instances were created successfully!";
            writeLog("All instances installed successfully.");
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

function generateAdminLoginLink($instanceDir, $siteUrl, $title)
{
    return $siteUrl . '/wp-login.php';
}

function readLogFile($logFile)
{
    return file_exists($logFile) ? file_get_contents($logFile) : 'No logs available.';
}

function updateWordPressOption($instanceDir, $optionName, $optionValue)
{
    $wpCliPath = '/usr/local/bin/wp';
    $phpPath = '/usr/bin/php';

    $command = sprintf(
        '%s %s option update %s %s --path=%s --allow-root',
        escapeshellarg($phpPath),
        escapeshellarg($wpCliPath),
        escapeshellarg($optionName),
        escapeshellarg($optionValue),
        escapeshellarg($instanceDir)
    );

    $output = shell_exec($command);
    return $output;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_instance'])) {
    $instanceDir = $_POST['instance_dir'];
    $siteTitle = $_POST['site_title'];

    if ($instanceDir && $siteTitle) {
        updateWordPressOption($instanceDir, 'blogname', $siteTitle);

        $success = "Instance configuration updated successfully!";
        writeLog("Updated instance at $instanceDir: Title = $siteTitle");
    } else {
        $error = "Failed to update instance configuration. Please fill in all fields.";
        writeLog("Configuration update failed: Missing data.");
    }
}

function getWordPressTitle($instanceDir)
{
    $wpCliPath = '/usr/local/bin/wp';
    $phpPath = '/usr/bin/php';

    $command = sprintf(
        '%s %s option get blogname --path=%s --allow-root',
        escapeshellarg($phpPath),
        escapeshellarg($wpCliPath),
        escapeshellarg($instanceDir)
    );

    $output = shell_exec($command);
    return trim($output);
}
function getAdminEmailFromWPCLI($instanceDir)
{
    $wpCliPath = '/usr/local/bin/wp';
    $phpPath = '/usr/bin/php';

    $command = sprintf(
        '%s %s option get admin_email --path=%s --allow-root',
        escapeshellarg($phpPath),
        escapeshellarg($wpCliPath),
        escapeshellarg($instanceDir)
    );

    $output = shell_exec($command);
    return trim($output) ?: 'Unknown';
}
?>