<?php
include 'functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Instances Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css?v=1.0">
</head>
<body class="bg-dark text-light">
    <div class="container py-5">
        <h1 class="text-center mb-4">WordPress Instances Panel</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <button class="btn btn-success mb-4" data-bs-toggle="modal" data-bs-target="#createInstanceModal">
            Create New Instance
        </button>
        <button class="btn btn-info mb-4" data-bs-toggle="modal" data-bs-target="#logsViewerModal" onclick="loadLogs()">
            View Logs
        </button>
<button onclick="window.location.href='?action=logout'" class="btn btn-danger float-end">Logout</button>

        <div class="card bg-secondary text-light shadow-lg p-4">
            <table class="table table-dark table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Instance Title</th>
                        <th>Database Name</th>
                        <th>URL</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instances as $index => $instance): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($instance['title']) ?></td>
                            <td><?= htmlspecialchars($instance['db_name']) ?></td>
                            <td>
                                <a href="<?= htmlspecialchars($instance['url']) ?>" target="_blank" class="btn btn-link">Visit</a>
                            </td>
                            <td>
    <?php if (!empty($instance['admin_login_url'])): ?>
        <a href="<?= htmlspecialchars($instance['admin_login_url']) ?>" target="_blank" class="btn btn-primary btn-sm">Login</a>
    <?php else: ?>
        <span class="text-danger">Login link unavailable</span>
    <?php endif; ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="delete_instance" value="1">
                                    <input type="hidden" name="instance_dir" value="<?= htmlspecialchars($instance['directory']) ?>">
                                    <input type="hidden" name="db_name" value="<?= htmlspecialchars($instance['db_name']) ?>">
                                </form>
    <form method="post" style="display:inline;">
    <button 
        type="button"
        class="btn btn-primary btn-sm" 
        data-bs-toggle="modal" 
        data-bs-target="#configureInstanceModal" 
        onclick="setConfigInstance('<?= htmlspecialchars($instance['directory']) ?>', '<?= htmlspecialchars($instance['title']) ?>', '<?= htmlspecialchars($instance['db_name']) ?>')">
        Configure
    </button>
</form>
    <form method="post" style="display:inline;">
    <input type="hidden" name="delete_instance" value="1">
    <input type="hidden" name="instance_dir" value="<?= htmlspecialchars($instance['directory']) ?>">
    <input type="hidden" name="db_name" value="<?= htmlspecialchars($instance['db_name']) ?>">
    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
</form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="createInstanceModal" tabindex="-1" aria-labelledby="createInstanceModalLabel">
        <div class="modal-dialog">
            <div class="modal-content bg-secondary text-light">
                <div class="modal-header">
                    <h5 class="modal-title" id="createInstanceModalLabel">Create New WordPress Instances</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="create_instances" value="1">
                       <div class="mb-3">
                            <label for="mysql_user" class="form-label">MySQL Username</label>
                            <input type="text" id="mysql_user" name="mysql_user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="mysql_pass" class="form-label">MySQL Password</label>
                            <input type="password" id="mysql_pass" name="mysql_pass" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="titles" class="form-label">Instance Titles (comma-separated)</label>
                            <input type="text" id="titles" name="titles[]" class="form-control" placeholder="e.g., Site1, Site2" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Create</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<div class="modal fade" id="logsViewerModal" tabindex="-1" aria-labelledby="logsViewerModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="logsViewerModalLabel">Installer Logs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre style="white-space: pre-wrap; word-wrap: break-word;">
                    <?= htmlspecialchars(readLogFile($logFile)) ?>
                </pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="configureInstanceModal" tabindex="-1" aria-labelledby="configureInstanceModalLabel">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="configureInstanceModalLabel">Configure Instance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="instance_dir" id="configInstanceDir">
                    <div class="mb-3">
                        <label for="site_title" class="form-label">Site Title</label>
                        <input type="text" class="form-control" name="site_title" id="siteTitle" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_instance" class="btn btn-success">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="assets/script.js?v=1.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>