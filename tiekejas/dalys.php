<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

tikrintiRole('Tiekejas');

$manoId = (int)$_SESSION['naudotojo_id'];
$q      = trim($_GET['q'] ?? '');

// Grąžina mano dalį arba null (svetimų dalių nei redaguoti, nei trinti negalima)
function manoDalis(PDO $pdo, int $id, int $manoId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM preke WHERE prekes_id = ? AND tiekejo_id = ?');
    $stmt->execute([$id, $manoId]);
    return $stmt->fetch() ?: null;
}

$redaguojamaId = 0;     // > 0, kai išsaugant buvo klaidų – tą popup atidarom iš naujo
$laukai  = null;   // tos dalies įvestos reikšmės
$klaidos = [];

// --- Veiksmai ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['prekes_id'] ?? 0);
    $dalis = manoDalis($pdo, $id, $manoId);
    if (!$dalis) {
        grizti('klaida', 'Dalis nerasta.');
    }

    if (($_POST['veiksmas'] ?? '') === 'istrinti') {
        // Dalies, kuri jau yra užsakymuose, ištrinti negalima (išliktų užsakymų istorija)
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT uzsakymo_id) FROM uzsakymo_preke WHERE prekes_id = ?');
        $stmt->execute([$id]);
        $uzsakymu = (int)$stmt->fetchColumn();

        if ($uzsakymu > 0) {
            grizti('klaida', "„{$dalis['pavadinimas']}“ ištrinti negalima, nes ji yra $uzsakymu užsakyme(-uose). "
                . 'Jei nebenorite jos siūlyti, nustatykite kiekį 0.', "d$id");
        }

        $pdo->prepare('DELETE FROM preke WHERE prekes_id = ? AND tiekejo_id = ?')->execute([$id, $manoId]);
        grizti('sekme', "„{$dalis['pavadinimas']}“ ištrinta.");
    }

    if (($_POST['veiksmas'] ?? '') === 'redaguoti') {
        [$laukai, $klaidos, $kaina] = tikrintiDali($pdo, $_POST, $manoId, $id);

        if (!$klaidos) {
            $stmt = $pdo->prepare(
                'UPDATE preke SET pavadinimas = ?, gamintojas = ?, modelis = ?, kaina = ?, kiekis = ?, pristatymo_laikas = ?
                 WHERE prekes_id = ? AND tiekejo_id = ?'
            );
            $stmt->execute([
                $laukai['pavadinimas'],
                arbaNull($laukai['gamintojas']),
                arbaNull($laukai['modelis']),
                $kaina,
                (int)$laukai['kiekis'],
                (int)$laukai['pristatymo_laikas'],
                $id,
                $manoId,
            ]);
            grizti('sekme', "„{$laukai['pavadinimas']}“ atnaujinta.", "d$id");
        }

        // Klaidos – rodom tą pačią eilutę redagavimo režimu su įvestomis reikšmėmis
        $redaguojamaId = $id;
    }
}

// --- Mano dalys ---
$sql = 'SELECT p.*, COUNT(DISTINCT up.uzsakymo_id) AS uzsakymu
        FROM preke p
        LEFT JOIN uzsakymo_preke up ON up.prekes_id = p.prekes_id
        WHERE p.tiekejo_id = ?';
$params = [$manoId];
if ($q !== '') {
    $sql .= ' AND (p.pavadinimas LIKE ? OR p.gamintojas LIKE ? OR p.modelis LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' GROUP BY p.prekes_id ORDER BY p.pavadinimas';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dalys = $stmt->fetchAll();

// Formos reikšmės iš DB eilutės
function reiksmes(array $d): array {
    return [
        'pavadinimas' => $d['pavadinimas'],
        'gamintojas'  => $d['gamintojas'] ?? '',
        'modelis'     => $d['modelis'] ?? '',
        'kaina'       => number_format((float)$d['kaina'], 2, ',', ''),
        'kiekis'      => (string)$d['kiekis'],
        'pristatymo_laikas' => $d['pristatymo_laikas'] !== null ? (string)$d['pristatymo_laikas'] : '',
    ];
}

// Laukas popup formoje (su klaida po juo)
function laukas(int $id, string $name, string $label, array $laukai, array $klaidos, array $attr = [], string $priedas = ''): string {
    $klaida = $klaidos[$name] ?? null;
    $papildomi = '';
    foreach ($attr as $k => $v) {
        $papildomi .= " $k=\"" . htmlspecialchars((string)$v) . '"';
    }
    $privalomas = isset($attr['required']);
    $html = '<div><label for="' . $name . '-' . $id . '" class="block text-sm font-medium text-slate-700 mb-1">' . htmlspecialchars($label)
          . ($privalomas ? ' <span class="text-red-500">*</span>' : '') . '</label><div class="relative">'
          . '<input id="' . $name . '-' . $id . '" name="' . $name . '" value="' . htmlspecialchars($laukai[$name]) . '"' . $papildomi
          . ' class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 ' . ($priedas ? 'pr-12 ' : '')
          . ($klaida ? 'border-red-400 focus:ring-red-400' : 'border-slate-300 focus:ring-sky-500') . '">';
    if ($priedas) {
        $html .= '<span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400">' . $priedas . '</span>';
    }
    $html .= '</div>';
    if ($klaida) {
        $html .= '<p class="mt-1 text-sm text-red-600">' . htmlspecialchars($klaida) . '</p>';
    }
    return $html . '</div>';
}

$puslapioPavadinimas = 'Mano dalys';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="text-2xl font-semibold mb-6">Mano siūlomos dalys</h1>

<form method="get" class="mb-6 flex gap-3 max-w-xl">
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ieškoti tarp savo dalių…"
           class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
    <button class="rounded-lg bg-sky-600 px-5 py-2 text-sm font-medium text-white hover:bg-sky-700">Ieškoti</button>
</form>

<?php if (!$dalys): ?>
    <div class="rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
        <?php if ($q !== ''): ?>
            Pagal „<?= htmlspecialchars($q) ?>“ nieko nerasta. <a href="dalys.php" class="text-sky-600 hover:underline">Rodyti visas</a>
        <?php else: ?>
            Jūs dar nesiūlote jokių dalių. <a href="prideti_dalis.php" class="text-sky-600 hover:underline">Pridėkite pirmąją →</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Pavadinimas</th>
                    <th class="px-4 py-3 font-medium">Gamintojas</th>
                    <th class="px-4 py-3 font-medium">Modelis</th>
                    <th class="px-4 py-3 font-medium text-right">Kaina</th>
                    <th class="px-4 py-3 font-medium text-right">Sandėlyje</th>
                    <th class="px-4 py-3 font-medium text-right">Pristatymas</th>
                    <th class="px-4 py-3 font-medium text-center">Užsakymuose</th>
                    <th class="px-4 py-3"><span class="sr-only">Veiksmai</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($dalys as $d): ?>
                    <?php $id = (int)$d['prekes_id']; ?>
                    <tr id="d<?= $id ?>" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($d['pavadinimas']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($d['gamintojas'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($d['modelis'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><?= eur($d['kaina']) ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <?= (int)$d['kiekis'] > 0 ? (int)$d['kiekis'] . ' vnt.' : '<span class="text-red-600">Nėra</span>' ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap text-slate-600"><?= pristatymoLaikas($d['pristatymo_laikas']) ?></td>
                        <td class="px-4 py-3 text-center text-slate-500"><?= (int)$d['uzsakymu'] ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            <div class="flex justify-end gap-1">
                                <button type="button" data-atidaryti="redaguoti-<?= $id ?>"
                                        aria-label="Redaguoti „<?= htmlspecialchars($d['pavadinimas']) ?>“" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 hover:text-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg></button>
                                <?php if ((int)$d['uzsakymu'] === 0): ?>
                                    <form method="post"
                                          onsubmit="return confirm('Ar tikrai norite ištrinti „<?= htmlspecialchars(addslashes($d['pavadinimas'])) ?>“?');">
                                        <input type="hidden" name="veiksmas" value="istrinti">
                                        <input type="hidden" name="prekes_id" value="<?= $id ?>">
                                        <button aria-label="Ištrinti „<?= htmlspecialchars($d['pavadinimas']) ?>“" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-600 hover:border-red-300 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-sky-500"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></button>
                                    </form>
                                <?php else: ?>
                                    <span title="Dalis yra užsakymuose, todėl jos ištrinti negalima. Nustatykite kiekį 0."
                                          class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-300 cursor-not-allowed"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Redagavimo popup langai -->
    <?php foreach ($dalys as $d): ?>
        <?php
        $id  = (int)$d['prekes_id'];
        $suKlaidomis = $id === $redaguojamaId;
        $v   = $suKlaidomis ? $laukai : reiksmes($d);
        $kl  = $suKlaidomis ? $klaidos : [];
        ?>
        <dialog id="redaguoti-<?= $id ?>" <?= $suKlaidomis ? 'data-atidaryti-iskart' : '' ?>
                class="w-full max-w-xl rounded-xl p-0 shadow-xl backdrop:bg-slate-900/50">
            <form method="post" action="dalys.php<?= $q !== '' ? '?q=' . htmlspecialchars(urlencode($q)) : '' ?>" novalidate>
                <input type="hidden" name="veiksmas" value="redaguoti">
                <input type="hidden" name="prekes_id" value="<?= $id ?>">

                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-800">Redaguoti dalį</h2>
                    <button type="button" data-uzdaryti aria-label="Uždaryti" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <?= laukas($id, 'pavadinimas', 'Pavadinimas', $v, $kl, ['maxlength' => 100, 'required' => 'required']) ?>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?= laukas($id, 'gamintojas', 'Gamintojas', $v, $kl, ['maxlength' => 100]) ?>
                        <?= laukas($id, 'modelis', 'Modelis', $v, $kl, ['maxlength' => 100]) ?>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?= laukas($id, 'kaina', 'Kaina', $v, $kl, ['inputmode' => 'decimal', 'required' => 'required'], '€') ?>
                        <?= laukas($id, 'kiekis', 'Kiekis sandėlyje', $v, $kl, ['type' => 'number', 'min' => 0, 'required' => 'required'], 'vnt.') ?>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?= laukas($id, 'pristatymo_laikas', 'Pristatymo laikas', $v, $kl, ['type' => 'number', 'min' => 1, 'required' => 'required'], 'd. d.') ?>
                    </div>
                </div>

                <div class="flex justify-end gap-2 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-xl">
                    <button type="button" data-uzdaryti class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-200">Atšaukti</button>
                    <button class="rounded-lg bg-sky-600 px-5 py-2 text-sm font-medium text-white hover:bg-sky-700">Išsaugoti</button>
                </div>
            </form>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener('click', e => {
    const atidaryti = e.target.closest('[data-atidaryti]');
    if (atidaryti) document.getElementById(atidaryti.dataset.atidaryti)?.showModal();

    const uzdaryti = e.target.closest('[data-uzdaryti]');
    if (uzdaryti) uzdaryti.closest('dialog')?.close();
});
// Uždaryti paspaudus šalia lango
document.querySelectorAll('dialog').forEach(d => {
    d.addEventListener('click', e => { if (e.target === d) d.close(); });
});
// Jei išsaugant buvo klaidų – popup atidarom iš naujo su klaidomis
document.querySelector('dialog[data-atidaryti-iskart]')?.showModal();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
