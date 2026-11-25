<?php
session_start();

function tikrintiRole(string ...$roles): void {
    global $pdo;

    if (!isset($_SESSION['naudotojo_id'])) {
        header('Location: /lektuvu_dalys/prisijungti.php');
        exit;
    }

    // Rolę kiekvieną kartą paimam iš DB – jei direktorius ją pakeitė,
    // pakeitimas įsigalioja iškart, nereikia persijungti
    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT tipas FROM naudotojas WHERE naudotojo_id = ?');
        $stmt->execute([$_SESSION['naudotojo_id']]);
        $tipas = $stmt->fetchColumn();

        if ($tipas === false) {           // paskyra ištrinta
            $_SESSION = [];
            session_destroy();
            header('Location: /lektuvu_dalys/prisijungti.php');
            exit;
        }
        $_SESSION['tipas'] = $tipas;
    }

    if ($roles && !in_array($_SESSION['tipas'], $roles)) {
        http_response_code(403);
        exit('Neturite teisių');
    }
}
