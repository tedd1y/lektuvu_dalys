<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

// jei prisijunges
if (isset($_SESSION['naudotojo_id'])) {
    header('Location: index.php');
    exit;
}

$klaida = '';
$pastas = '';

// žinutė po registracijos
$zinute = $_SESSION['zinute']['tekstas'] ?? '';
unset($_SESSION['zinute']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pastas      = trim($_POST['pastas'] ?? '');
    $slaptazodis = $_POST['slaptazodis'] ?? '';

    if ($pastas === '' || $slaptazodis === '') {
        $klaida = 'Užpildykite visus laukus.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT naudotojo_id, vardas, pavarde, slaptazodis, tipas
             FROM naudotojas WHERE pastas = ?'
        );
        $stmt->execute([$pastas]);
        $naudotojas = $stmt->fetch();

        if ($naudotojas && password_verify($slaptazodis, $naudotojas['slaptazodis'])) {
            session_regenerate_id(true);
            $_SESSION['naudotojo_id'] = $naudotojas['naudotojo_id'];
            $_SESSION['vardas']       = $naudotojas['vardas'];
            $_SESSION['pavarde']      = $naudotojas['pavarde'];
            $_SESSION['tipas']        = $naudotojas['tipas'];

            header('Location: index.php');
            exit;
        }

        $klaida = 'Neteisingas el. paštas arba slaptažodis.';
    }
}
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisijungimas – Lėktuvo dalių užsakymų sistema</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4">

<div class="w-full max-w-sm">
    <h1 class="text-center text-2xl font-semibold text-slate-800 mb-1">Lėktuvo dalių užsakymai</h1>
    <p class="text-center text-slate-500 mb-6">Prisijunkite prie sistemos</p>

    <div class="bg-white rounded-xl shadow p-6">
        <?php if ($zinute !== ''): ?>
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                <?= htmlspecialchars($zinute) ?>
            </div>
        <?php endif; ?>

        <?php if ($klaida !== ''): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($klaida) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4" novalidate>
            <div>
                <label for="pastas" class="block text-sm font-medium text-slate-700 mb-1">El. paštas</label>
                <input type="email" id="pastas" name="pastas" required autofocus
                       value="<?= htmlspecialchars($pastas) ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
            </div>

            <div>
                <label for="slaptazodis" class="block text-sm font-medium text-slate-700 mb-1">Slaptažodis</label>
                <input type="password" id="slaptazodis" name="slaptazodis" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-sky-600 px-4 py-2 font-medium text-white hover:bg-sky-700">
                Prisijungti
            </button>
        </form>
    </div>

    <p class="text-center text-sm text-slate-600 mt-4">
        Neturite paskyros?
        <a href="registracija.php" class="text-sky-600 hover:underline">Registruokitės</a>
    </p>
</div>

</body>
</html>
