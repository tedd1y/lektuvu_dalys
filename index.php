<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

checkRole(); // bet kuris prisijungęs naudotojas

$korteles = [
    'Vadybininkas' => [
        ['Tiekėjai', 'Peržiūrėkite tiekėjų sąrašą ir jų siūlomas dalis.', 'vadybininkas/tiekejai.php'],
        ['Dalių paieška', 'Ieškokite dalių pagal pavadinimą ir palyginkite pasiūlymus.', 'vadybininkas/daliu_paieska.php'],
        ['Mano užsakymai', 'Peržiūrėkite, redaguokite ar atšaukite pateiktus užsakymus.', 'vadybininkas/pateikti_uzsakymai.php'],
    ],
    'Tiekejas' => [
        ['Mano dalys', 'Peržiūrėkite, redaguokite ar ištrinkite savo siūlomas dalis.', 'tiekejas/dalys.php'],
        ['Pridėti dalį', 'Pridėkite naują siūlomą dalį.', 'tiekejas/prideti_dalis.php'],
        ['Gauti užsakymai', 'Peržiūrėkite užsakymus ir keiskite jų būseną.', 'tiekejas/gauti_uzsakymai.php'],
    ],
    'Direktorius' => [
        ['Naudotojai', 'Peržiūrėkite naudotojus ir keiskite jų teises.', 'direktorius/naudotojai.php'],
        ['Statistika', 'Užsakymų statistika ir apžvalga.', 'direktorius/statistika.php'],
    ],
];

$mano = $korteles[$_SESSION['tipas']] ?? [];

$puslapioPavadinimas = 'Pradžia';
require __DIR__ . '/includes/header.php';
?>

<h1 class="text-2xl font-semibold mb-1">Sveiki, <?= htmlspecialchars($_SESSION['vardas']) ?>!</h1>

<?php if ($mano): ?>
    <p class="text-slate-500 mb-6">Pasirinkite, ką norite daryti.</p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($mano as [$pavadinimas, $aprasymas, $nuoroda]): ?>
            <a href="<?= htmlspecialchars($nuoroda) ?>"
               class="block rounded-xl bg-white p-5 shadow-sm border border-slate-200 hover:border-sky-400 hover:shadow transition">
                <h2 class="font-semibold text-sky-700 mb-1"><?= htmlspecialchars($pavadinimas) ?></h2>
                <p class="text-sm text-slate-600"><?= htmlspecialchars($aprasymas) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="mt-6 rounded-xl bg-amber-50 border border-amber-200 p-5 text-amber-800">
        Jūsų paskyra sukurta, tačiau dar neturi teisių. Kreipkitės į direktorių, kad jis suteiktų jums rolę.
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
