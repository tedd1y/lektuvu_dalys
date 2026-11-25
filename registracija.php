<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

// jei prisijunges
if (isset($_SESSION['naudotojo_id'])) {
    header('Location: index.php');
    exit;
}

$laukai = ['vardas' => '', 'pavarde' => '', 'pavadinimas' => '', 'pastas' => '', 'telefonas' => ''];
$klaidos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($laukai as $k => $_) {
        $laukai[$k] = trim($_POST[$k] ?? '');
    }
    $slaptazodis = $_POST['slaptazodis'] ?? '';
    $pakartotas  = $_POST['slaptazodis2'] ?? '';

    if ($laukai['vardas'] === '')  $klaidos[] = 'Įveskite vardą.';
    if ($laukai['pavarde'] === '') $klaidos[] = 'Įveskite pavardę.';
    if (!filter_var($laukai['pastas'], FILTER_VALIDATE_EMAIL)) $klaidos[] = 'Įveskite teisingą el. pašto adresą.';
    if (mb_strlen($slaptazodis) < 6) $klaidos[] = 'Slaptažodis turi būti bent 6 simbolių.';
    if ($slaptazodis !== $pakartotas) $klaidos[] = 'Slaptažodžiai nesutampa.';

    if (!$klaidos) {
        $stmt = $pdo->prepare('SELECT 1 FROM naudotojas WHERE pastas = ?');
        $stmt->execute([$laukai['pastas']]);
        if ($stmt->fetch()) {
            $klaidos[] = 'Naudotojas su tokiu el. paštu jau egzistuoja.';
        }
    }

    if (!$klaidos) {
        $stmt = $pdo->prepare(
            'INSERT INTO naudotojas (vardas, pavarde, pavadinimas, pastas, slaptazodis, telefonas)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $laukai['vardas'],
            $laukai['pavarde'],
            $laukai['pavadinimas'] !== '' ? $laukai['pavadinimas'] : null,
            $laukai['pastas'],
            password_hash($slaptazodis, PASSWORD_DEFAULT),
            $laukai['telefonas'] !== '' ? $laukai['telefonas'] : null,
        ]);

        $_SESSION['zinute'] = ['tipas' => 'sekme', 'tekstas' => 'Registracija sėkminga. Dabar galite prisijungti.'];
        header('Location: prisijungti.php');
        exit;
    }
}

function laukas(string $name, string $label, string $value, string $type = 'text', bool $privalomas = true): void { ?>
    <div>
        <label for="<?= $name ?>" class="block text-sm font-medium text-slate-700 mb-1">
            <?= htmlspecialchars($label) ?><?= $privalomas ? ' <span class="text-red-500">*</span>' : '' ?>
        </label>
        <input type="<?= $type ?>" id="<?= $name ?>" name="<?= $name ?>"
               value="<?= htmlspecialchars($value) ?>" <?= $privalomas ? 'required' : '' ?>
               class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
    </div>
<?php }
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registracija – Lėktuvo dalių užsakymų sistema</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center px-4 py-10">

<div class="w-full max-w-md">
    <h1 class="text-center text-2xl font-semibold text-slate-800 mb-1">Lėktuvo dalių užsakymai</h1>
    <p class="text-center text-slate-500 mb-6">Sukurkite paskyrą</p>

    <div class="bg-white rounded-xl shadow p-6">
        <?php if ($klaidos): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc pl-4 space-y-0.5">
                    <?php foreach ($klaidos as $k): ?>
                        <li><?= htmlspecialchars($k) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php laukas('vardas', 'Vardas', $laukai['vardas']); ?>
                <?php laukas('pavarde', 'Pavardė', $laukai['pavarde']); ?>
            </div>
            <?php laukas('pavadinimas', 'Įmonės pavadinimas', $laukai['pavadinimas'], 'text', false); ?>
            <?php laukas('pastas', 'El. paštas', $laukai['pastas'], 'email'); ?>
            <?php laukas('telefonas', 'Telefonas', $laukai['telefonas'], 'tel', false); ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php laukas('slaptazodis', 'Slaptažodis', '', 'password'); ?>
                <?php laukas('slaptazodis2', 'Pakartokite slaptažodį', '', 'password'); ?>
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-sky-600 px-4 py-2 font-medium text-white hover:bg-sky-700">
                Registruotis
            </button>
        </form>
    </div>

    <p class="text-center text-sm text-slate-600 mt-4">
        Jau turite paskyrą?
        <a href="prisijungti.php" class="text-sky-600 hover:underline">Prisijunkite</a>
    </p>
</div>

</body>
</html>
