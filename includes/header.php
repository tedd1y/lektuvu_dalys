<?php
// Naudojimas puslapyje:
//   $puslapioPavadinimas = 'Tiekėjai';
//   require __DIR__ . '/../includes/header.php';

$base = '/lektuvu_dalys';

$meniu = [
    'Vadybininkas' => [
        'Tiekėjai'        => '/vadybininkas/tiekejai.php',
        'Dalių paieška'   => '/vadybininkas/daliu_paieska.php',
        'Mano užsakymai'  => '/vadybininkas/pateikti_uzsakymai.php',
    ],
    'Tiekejas' => [
        'Mano dalys'      => '/tiekejas/dalys.php',
        'Pridėti dalį'    => '/tiekejas/prideti_dalis.php',
        'Gauti užsakymai' => '/tiekejas/gauti_uzsakymai.php',
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
    <?php if ($tipas === 'Vadybininkas'): ?>
        <script src="<?= $base ?>/includes/krepselis.js" defer></script>
    <?php endif; ?>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col text-slate-800"
      data-naudotojas="<?= (int)($_SESSION['naudotojo_id'] ?? 0) ?>">

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
                <?php if ($tipas === 'Vadybininkas'): ?>
                    <?php $krepselyje = $dabartinis === $base . '/vadybininkas/krepselis.php'; ?>
                    <a href="<?= $base ?>/vadybininkas/krepselis.php" title="Krepšelis"
                       class="relative rounded-lg p-2 <?= $krepselyje ? 'bg-sky-100 text-sky-800' : 'text-slate-600 hover:bg-slate-100' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                        </svg>
                        <span data-krepselio-kiekis
                              class="hidden absolute -top-1 -right-1 min-w-[1.25rem] h-5 rounded-full bg-red-600 px-1 text-center text-xs font-semibold leading-5 text-white">0</span>
                    </a>
                <?php endif; ?>
                <span class="flex flex-col items-end leading-tight">
                    <span class="font-medium text-slate-700">
                        <?= htmlspecialchars($_SESSION['vardas'] . ' ' . $_SESSION['pavarde']) ?>
                    </span>
                    <span class="text-xs text-slate-500">
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
