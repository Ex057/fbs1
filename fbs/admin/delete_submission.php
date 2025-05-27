<?php
require 'connect.php';

$submissionId = $_GET['id'] ?? null;

if (!$submissionId) {
    die("Submission ID is required.");
}

// Delete the submission
$stmt = $pdo->prepare("DELETE FROM response_form WHERE id = ?");
$stmt->execute([$submissionId]);

echo "Submission deleted successfully!";
?>