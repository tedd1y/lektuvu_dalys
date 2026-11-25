<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

tikrintiRole('Direktorius');

// Kiek kokių detalių užsakyta, už kokią sumą ir kada bus pristatytos.
// Atšaukti užsakymai neįskaičiuojami.
// Pristatymo data – užsakyme išsaugota data (pateikimas + ilgiausias dalies pristatymo laikas darbo dienomis).
// Rodoma vėliausia dar nepristatytų (Pateiktas / Priimtas) užsakymų data.
$dalys = $pdo->query(
    "SELECT p.prekes_id, p.pavadinimas, p.gamintojas, p.modelis, p.pristatymo_laikas,
            COALESCE(t.pavadinimas, CONCAT(t.vardas, ' ', t.pavarde)) AS tiekejas,
            SUM(up.kiekis) AS kiekis,
            SUM(up.suma)   AS suma,
            SUM(u.busena IN ('Pateiktas', 'Priimtas')) AS laukiama,
            MAX(CASE WHEN u.busena IN ('Pateiktas', 'Priimtas') THEN u.pristatymo_data END) AS pristatymo_data
     FROM uzsakymo_preke up
     JOIN uzsakymas u  ON u.uzsakymo_id = up.uzsakymo_id
     JOIN preke p      ON p.prekes_id = up.prekes_id
     JOIN naudotojas t ON t.naudotojo_id = p.tiekejo_id
     WHERE u.busena <> 'Atsauktas'
     GROUP BY p.prekes_id
     ORDER BY kiekis DESC, suma DESC"
)->fetchAll();

$visoKiekis = array_sum(array_column($dalys, 'kiekis'));
$visoSuma   = array_sum(array_column($dalys, 'suma'));

$puslapioPavadinimas = 'Statistika';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="text-2xl font-semibold mb-6">Užsakymų statistika</h1>

<?php if (!$dalys): ?>
    <div class="rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">Užsakymų kol kas nėra.</div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-5 py-3 font-medium">Detalė</th>
                    <th class="px-5 py-3 font-medium">Tiekėjas</th>
                    <th class="px-5 py-3 font-medium text-right">Užsakyta</th>
                    <th class="px-5 py-3 font-medium text-right">Suma</th>
                    <th class="px-5 py-3 font-medium text-right">Bus pristatyta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($dalys as $d): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <div class="font-medium text-slate-800"><?= htmlspecialchars($d['pavadinimas']) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($d['gamintojas'] ?? '—') ?> · <?= htmlspecialchars($d['modelis'] ?? '—') ?></div>
                        </td>
                        <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars($d['tiekejas']) ?></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums font-medium"><?= (int)$d['kiekis'] ?> vnt.</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums"><?= eur($d['suma']) ?></td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <?php if ((int)$d['laukiama'] === 0): ?>
                                <span class="text-green-700">Pristatyta</span>
                            <?php elseif ($d['pristatymo_data']): ?>
                                <span class="tabular-nums"><?= htmlspecialchars($d['pristatymo_data']) ?></span>
                            <?php else: ?>
                                <span class="text-slate-400">Nenurodyta</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                <tr>
                    <td colspan="2" class="px-5 py-3 font-semibold">Iš viso</td>
                    <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums font-semibold"><?= (int)$visoKiekis ?> vnt.</td>
                    <td class="px-5 py-3 text-right whitespace-nowrap tabular-nums font-semibold"><?= eur($visoSuma) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500">
        Atšaukti užsakymai neįskaičiuojami. Pristatymo data – užsakyme numatyta data (darbo dienomis); jei dalis užsakyta kelis kartus, rodoma vėliausia dar laukiama data.
    </p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
