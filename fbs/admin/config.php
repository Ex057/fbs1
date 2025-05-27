<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

require 'connect.php';

$conn = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");


// Handle Create
if (isset($_POST['create'])) {
    $url = $_POST['url'];
    $username = $_POST['username'];
    $password = base64_encode($_POST['password']); // Base64 encode the password
    $key = $_POST['key'];
    $description = $_POST['description'];
    $status = isset($_POST['status']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO dhis2_instances (url, username, password, `key`, description, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bindParam(1, $url);
    $stmt->bindParam(2, $username);
    $stmt->bindParam(3, $password);
    $stmt->bindParam(4, $key);
    $stmt->bindParam(5, $description);
    $stmt->bindParam(6, $status, PDO::PARAM_INT);
    $stmt->execute();
    $stmt->closeCursor();
    header("Location: config.php");
    exit();
}

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $url = $_POST['url'];
    $username = $_POST['username'];
    $password = base64_encode($_POST['password']); // Base64 encode the password
    $key = $_POST['key'];
    $description = $_POST['description'];
    $status = isset($_POST['status']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE dhis2_instances SET url=?, username=?, password=?, `key`=?, description=?, status=? WHERE id=?");
    $stmt->bindParam(1, $url);
    $stmt->bindParam(2, $username);
    $stmt->bindParam(3, $password);
    $stmt->bindParam(4, $key);
    $stmt->bindParam(5, $description);
    $stmt->bindParam(6, $status, PDO::PARAM_INT);
    $stmt->bindParam(7, $id, PDO::PARAM_INT);
    $stmt->execute();
    $stmt->closeCursor();
    header("Location: config.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM dhis2_instances WHERE id=?");
    $stmt->bindParam(1, $id, PDO::PARAM_INT);
    $stmt->execute();
    $stmt->closeCursor();
    header("Location: config.php");
    exit();
}

// Fetch for edit
$edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM dhis2_instances WHERE id=?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
}

// Fetch all configs
$stmt = $conn->query("SELECT * FROM dhis2_instances ORDER BY id DESC");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DHIS2 Instances Management</title>
    <link href="argon-dashboard-master/assets/css/nucleo-icons.css" rel="stylesheet">
    <link href="argon-dashboard-master/assets/css/nucleo-svg.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="argon-dashboard-master/assets/css/argon-dashboard.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background-color: #f8f9fa; margin: 0; padding: 0; display: flex; }
        .sidebar { width: 250px; background-color: #343a40; color: white; padding-top: 20px; }
        .sidebar a { padding: 10px 20px; display: block; color: #adb5bd; text-decoration: none; }
        .sidebar a:hover { background-color: #495057; color: white; }
        .main-content { flex-grow: 1; padding: 20px; }
        /* .container-fluid { max-width: 1200px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 20px rgba(0, 0, 0, 0.1); } */
        h1 { text-align: center; color: #343a40; margin-bottom: 30px; }
        .mb-3 { margin-bottom: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; color: white; background-color: #007bff; cursor: pointer; transition: background-color 0.3s ease; }
        .btn-secondary { background-color: #6c757d; }
        .btn-danger { background-color: #dc3545; }
        .btn:hover { opacity: 0.9; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #495057; font-weight: bold; }
        input[type="text"], input[type="password"], textarea { width: calc(100% - 12px); padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; margin-bottom: 5px; }
        textarea { resize: vertical; }
        input[type="checkbox"] { margin-right: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.05); }
        th, td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        thead th { background-color: #007bff; color: white; font-weight: bold; }
        tbody tr:nth-child(even) { background-color: #f8f9fa; }
        .actions a { margin-right: 8px; text-decoration: none; }
        #creationSection { background-color: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #eee; margin-bottom: 20px; }
        #configTableWrapper { margin-top: 20px; }
        .toggle-button-group { margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include 'components/aside.php'; ?>
    <main class="main-content position-relative border-radius-lg">
        <?php include 'components/navbar.php'; ?>
        <div class="container-fluid py-4">
            <h1>DHIS2 Instances Management</h1>

            <div class="toggle-button-group">
                <button type="button" class="btn btn-primary" id="showCreationBtn" style="<?= $edit ? 'display:none;' : '' ?>">Add Instance</button>
                <button type="button" class="btn btn-secondary" id="hideCreationBtn" style="display:none;">Cancel Creation</button>
            </div>

            <div id="creationSection" style="<?= $edit ? '' : 'display:none;' ?>">
                <form method="post" class="mb-3" id="configForm">
                    <?php if ($edit): ?>
                        <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id']) ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="url">URL</label>
                        <input type="text" class="form-control" id="url" name="url" required value="<?= htmlspecialchars($edit['url'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required value="<?= htmlspecialchars($edit['username'] ?? '') ?>">
                    </div>
                  <div class="form-group">
                      <label for="password">Password</label>
                      <input type="password" class="form-control" id="password" name="password" required value="<?= htmlspecialchars($edit['password'] ?? '') ?>">
                  </div>
                    <div class="form-group">
                        <label for="key">Key</label>
                        <input type="text" class="form-control" id="key" name="key" required value="<?= htmlspecialchars($edit['key'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="status" name="status" <?= (isset($edit['status']) && $edit['status']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status">Active</label>
                    </div>
                    <button type="submit" name="<?= $edit ? 'update' : 'create' ?>" class="btn btn-primary"><?= $edit ? 'Update' : 'Create' ?></button>
                    <?php if ($edit): ?>
                        <a href="config.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="toggle-button-group">
                <button type="button" class="btn btn-info" id="showTableBtn">Curent Instances</button>
                <button type="button" class="btn btn-secondary" id="hideTableBtn" style="display:none;">Hide DHIS2 Instances Table</button>
            </div>

            <div id="configTableWrapper" style="display:none;">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>URL</th>
                            <th>Username</th>
                            <th>Key</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configs as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['url']) ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= htmlspecialchars($row['key']) ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td>
                                  <button type="button"
                                    class="btn btn-sm status-toggle-btn <?= $row['status'] ? 'btn-success' : 'btn-danger' ?>"
                                    data-id="<?= $row['id'] ?>"
                                    data-status="<?= $row['status'] ?>"
                                    title="Toggle Status">
                                    <i class="fas <?= $row['status'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                  </button>
                                </td>
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                  document.querySelectorAll('.status-toggle-btn').forEach(function(btn) {
                                    btn.addEventListener('click', function() {
                                      var id = this.getAttribute('data-id');
                                      var currentStatus = this.getAttribute('data-status');
                                      var button = this;
                                      fetch('toggle_status.php', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                        body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(currentStatus)
                                      })
                                      .then(response => response.json())
                                      .then(data => {
                                        if (data.success) {
                                          button.setAttribute('data-status', data.new_status);
                                          button.classList.toggle('btn-success', data.new_status == 1);
                                          button.classList.toggle('btn-danger', data.new_status == 0);
                                          button.querySelector('i').className = 'fas ' + (data.new_status == 1 ? 'fa-toggle-on' : 'fa-toggle-off');
                                        }
                                      });
                                    });
                                  });
                                });
                                </script>
                                <td><?= htmlspecialchars($row['created']) ?></td>
                                <td class="actions">
                                    <a href="config.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="config.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this config?')"><i class="fas fa-trash"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <a href="survey.php" class="btn btn-danger mt-3">Back</a>
        </div>
        <?php include 'components/fixednav.php'; ?>
    </main>

    <script src="argon-dashboard-master/assets/js/core/popper.min.js"></script>
    <script src="argon-dashboard-master/assets/js/core/bootstrap.min.js"></script>
    <script src="argon-dashboard-master/assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="argon-dashboard-master/assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="argon-dashboard-master/assets/js/argon-dashboard.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var showCreationBtn = document.getElementById('showCreationBtn');
            var hideCreationBtn = document.getElementById('hideCreationBtn');
            var creationSection = document.getElementById('creationSection');
            var showTableBtn = document.getElementById('showTableBtn');
            var hideTableBtn = document.getElementById('hideTableBtn');
            var configTableWrapper = document.getElementById('configTableWrapper');

            if (showCreationBtn) {
                showCreationBtn.addEventListener('click', function() {
                    creationSection.style.display = 'block';
                    showCreationBtn.style.display = 'none';
                    hideCreationBtn.style.display = 'inline-block';
                });
            }

            if (hideCreationBtn) {
                hideCreationBtn.addEventListener('click', function() {
                    creationSection.style.display = 'none';
                    hideCreationBtn.style.display = 'none';
                    showCreationBtn.style.display = 'inline-block';
                });
            }

            if (showTableBtn) {
                showTableBtn.addEventListener('click', function() {
                    configTableWrapper.style.display = 'block';
                    showTableBtn.style.display = 'none';
                    hideTableBtn.style.display = 'inline-block';
                });
            }

            if (hideTableBtn) {
                hideTableBtn.addEventListener('click', function() {
                    configTableWrapper.style.display = 'none';
                    hideTableBtn.style.display = 'none';
                    showTableBtn.style.display = 'inline-block';
                });
            }
        });
    </script>
</body>
</html>