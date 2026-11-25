<?php
session_start();

function checkRole(string ...$roles): void {
    if (!isset($_SESSION['naudotojo_id'])) {
        header('Location: /lektuvu_dalys/prisijungti.php');
        exit;
    }
    if ($roles && !in_array($_SESSION['tipas'], $roles)) {
        http_response_code(403);
        exit('Neturite teisių');
    }
}