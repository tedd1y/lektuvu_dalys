<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

tikrintiRole(); // bet kuris prisijungęs naudotojas

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

<h1 class="text-2xl font-semibold mb-6">Pasirinkite veiksmą</h1>

<?php if ($mano): ?>
    <ul class="max-w-2xl divide-y divide-slate-100 rounded-xl bg-white border border-slate-200 shadow-sm overflow-hidden">
        <?php foreach ($mano as [$pavadinimas, $aprasymas, $nuoroda]): ?>
            <li>
                <a href="<?= htmlspecialchars($nuoroda) ?>" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50">
                    <span>
                        <span class="block font-medium text-slate-800"><?= htmlspecialchars($pavadinimas) ?></span>
                        <span class="block text-sm text-slate-500"><?= htmlspecialchars($aprasymas) ?></span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <div class="mt-6 rounded-xl bg-amber-50 border border-amber-200 p-5 text-amber-800">
        Jūsų paskyra sukurta, tačiau dar neturi prieigos. Kreipkitės į direktorių, kad jis suteiktų jums prieigą.
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
