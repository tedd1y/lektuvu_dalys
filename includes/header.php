<?php
// Naudojimas puslapyje:
//   $puslapioPavadinimas = 'Tiekėjai';
//   require __DIR__ . '/../includes/header.php';

$base = '/lektuvu_dalys';

$meniu = [
    'Vadybininkas' => [
        'Tiekėjai'        => '/vadybininkas/tiekejai.php',
        'Dalių paieška'   => '/vadybininkas/daliu_paieska.php',
        'Mano užsakymai'  => '/vadybininkas/uzsakymai.php',
    ],
    'Tiekejas' => [
        'Mano dalys'      => '/tiekejas/dalys.php',
        'Gauti užsakymai' => '/tiekejas/uzsakymai.php',
    ],
    'Direktorius' => [
        'Naudotojai'      => '/direktorius/naudotojai.php',
        'Statistika'      => '/direktorius/statistika.php',
    ],
];

$roliuPavadinimai = [
    'Naudotojas'   => 'Naudotojas',
    'Vadybininkas' => 'Vadybininkas',
    'Tiekejas'     => 'Tiekėjas',
    'Direktorius'  => 'Direktorius',
];

$tipas       = $_SESSION['tipas'] ?? null;
$dabartinis  = $_SERVER['SCRIPT_NAME'];
$nuorodos    = $meniu[$tipas] ?? [];

// Vienkartinė žinutė po redirect (pvz. „Užsakymas pateiktas“)
$zinute = $_SESSION['zinute'] ?? null;
unset($_SESSION['zinute']);
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($puslapioPavadinimas ?? 'Pradžia') ?> – Lėktuvo dalių užsakymų sistema</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col text-slate-800">

<header class="bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 flex flex-wrap items-center justify-between gap-4 py-3">
        <div class="flex flex-wrap items-center gap-6">
            <a href="<?= $base ?>/index.php" class="font-semibold text-sky-700">✈ Lėktuvo dalys</a>

            <nav class="flex flex-wrap gap-1">
                <?php foreach ($nuorodos as $tekstas => $kelias): ?>
                    <?php $aktyvi = $dabartinis === $base . $kelias; ?>
                    <a href="<?= $base . $kelias ?>"
                       class="rounded-lg px-3 py-1.5 text-sm <?= $aktyvi ? 'bg-sky-100 text-sky-800 font-medium' : 'text-slate-600 hover:bg-slate-100' ?>">
                        <?= htmlspecialchars($tekstas) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <?php if (isset($_SESSION['naudotojo_id'])): ?>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-slate-600">
                    <?= htmlspecialchars($_SESSION['vardas'] . ' ' . $_SESSION['pavarde']) ?>
                    <span class="ml-1 rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-500">
                        <?= htmlspecialchars($roliuPavadinimai[$tipas] ?? $tipas) ?>
                    </span>
                </span>
                <a href="<?= $base ?>/atsijungti.php" class="text-red-600 hover:underline">Atsijungti</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<main class="flex-1 max-w-6xl w-full mx-auto px-4 py-8">
    <?php if ($zinute): ?>
        <?php $sekme = ($zinute['tipas'] ?? 'sekme') === 'sekme'; ?>
        <div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= $sekme ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700' ?>">
            <?= htmlspecialchars($zinute['tekstas']) ?>
        </div>
    <?php endif; ?>
