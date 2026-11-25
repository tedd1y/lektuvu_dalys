<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';

checkRole('Vadybininkas');

$manoId = (int)$_SESSION['naudotojo_id'];

function grizti(string $tipas, string $tekstas, int $atidaryti = 0): never {
    $_SESSION['zinute'] = ['tipas' => $tipas, 'tekstas' => $tekstas];
    $uzklausa = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: pateikti_uzsakymai.php' . ($uzklausa ? "?$uzklausa" : '') . ($atidaryti ? "#u$atidaryti" : ''));
    exit;
}

// --- Veiksmai (redaguoti / atšaukti) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uzsakymoId = (int)($_POST['uzsakymo_id'] ?? 0);
    $veiksmas   = $_POST['veiksmas'] ?? '';

    try {
        $pdo->beginTransaction();

        // Užsakymas turi būti mano ir dar „Pateiktas“
        $stmt = $pdo->prepare('SELECT busena FROM uzsakymas WHERE uzsakymo_id = ? AND vadybininko_id = ? FOR UPDATE');
        $stmt->execute([$uzsakymoId, $manoId]);
        $busena = $stmt->fetchColumn();

        if ($busena === false) {
            throw new RuntimeException('Užsakymas nerastas.');
        }
        if ($busena !== 'Pateiktas') {
            throw new RuntimeException('Keisti ar atšaukti galima tik užsakymą, kurio būsena „Pateiktas“.');
        }

        if ($veiksmas === 'atsaukti') {
            $pdo->prepare("UPDATE uzsakymas SET busena = 'Atsauktas' WHERE uzsakymo_id = ?")->execute([$uzsakymoId]);
            $pdo->commit();
            grizti('sekme', "Užsakymas Nr. $uzsakymoId atšauktas.", $uzsakymoId);
        }

        if ($veiksmas === 'redaguoti') {
            $nauji = $_POST['kiekis'] ?? [];

            $stmt = $pdo->prepare(
                'SELECT up.eilutes_id, up.kiekis, up.suma, p.pavadinimas, p.kiekis AS sandelyje
                 FROM uzsakymo_preke up JOIN preke p ON p.prekes_id = up.prekes_id
                 WHERE up.uzsakymo_id = ?'
            );
            $stmt->execute([$uzsakymoId]);
            $eilutes = $stmt->fetchAll();

            $liks = 0;
            foreach ($eilutes as $e) {
                $k = (int)($nauji[$e['eilutes_id']] ?? $e['kiekis']);
                if ($k < 0) {
                    throw new RuntimeException('Kiekis negali būti neigiamas.');
                }
                if ($k > (int)$e['sandelyje']) {
                    throw new RuntimeException('„' . $e['pavadinimas'] . '“ sandėlyje yra tik ' . (int)$e['sandelyje'] . ' vnt.');
                }
                if ($k > 0) $liks++;
            }
            if ($liks === 0) {
                throw new RuntimeException('Užsakyme turi likti bent viena dalis. Jei užsakymas nebereikalingas – atšaukite jį.');
            }

            // Vieneto kaina lieka tokia, kokia buvo pateikiant užsakymą
            $atnaujinti = $pdo->prepare('UPDATE uzsakymo_preke SET kiekis = ?, suma = ? WHERE eilutes_id = ?');
            $istrinti   = $pdo->prepare('DELETE FROM uzsakymo_preke WHERE eilutes_id = ?');
            foreach ($eilutes as $e) {
                $k = (int)($nauji[$e['eilutes_id']] ?? $e['kiekis']);
                if ($k === 0) {
                    $istrinti->execute([$e['eilutes_id']]);
                } else {
                    $vnt = (float)$e['suma'] / (int)$e['kiekis'];
                    $atnaujinti->execute([$k, round($vnt * $k, 2), $e['eilutes_id']]);
                }
            }

            $pdo->prepare('UPDATE uzsakymas SET suma = (SELECT SUM(suma) FROM uzsakymo_preke WHERE uzsakymo_id = ?) WHERE uzsakymo_id = ?')
                ->execute([$uzsakymoId, $uzsakymoId]);

            $pdo->commit();
            grizti('sekme', "Užsakymas Nr. $uzsakymoId atnaujintas.", $uzsakymoId);
        }

        throw new RuntimeException('Nežinomas veiksmas.');
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        grizti('klaida', $e->getMessage(), $uzsakymoId);
    }
}

// --- Sąrašas ---
$busenos = [
    'Pateiktas' => ['Pateiktas', 'bg-sky-100 text-sky-800'],
    'Priimtas'  => ['Priimtas',  'bg-amber-100 text-amber-800'],
    'Ivykdytas' => ['Įvykdytas', 'bg-green-100 text-green-800'],
    'Atsauktas' => ['Atšauktas', 'bg-slate-200 text-slate-600'],
];
$filtras = array_key_exists($_GET['busena'] ?? '', $busenos) ? $_GET['busena'] : '';

$sql = 'SELECT uzsakymo_id, suma, pateikimo_data, busena FROM uzsakymas WHERE vadybininko_id = ?';
$params = [$manoId];
if ($filtras) {
    $sql .= ' AND busena = ?';
    $params[] = $filtras;
}
$sql .= ' ORDER BY pateikimo_data DESC, uzsakymo_id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$uzsakymai = $stmt->fetchAll();

// Visų rodomų užsakymų eilutės viena užklausa
$eilutes = [];
if ($uzsakymai) {
    $ids = array_column($uzsakymai, 'uzsakymo_id');
    $vietos = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT up.eilutes_id, up.uzsakymo_id, up.kiekis, up.suma,
                p.pavadinimas, p.gamintojas, p.modelis, p.kiekis AS sandelyje,
                COALESCE(n.pavadinimas, CONCAT(n.vardas, ' ', n.pavarde)) AS tiekejas
         FROM uzsakymo_preke up
         JOIN preke p ON p.prekes_id = up.prekes_id
         JOIN naudotojas n ON n.naudotojo_id = p.tiekejo_id
         WHERE up.uzsakymo_id IN ($vietos)
         ORDER BY up.eilutes_id"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $e) {
        $eilutes[$e['uzsakymo_id']][] = $e;
    }
}

// Kiekių skaičiukai filtrų mygtukams
$stmt = $pdo->prepare('SELECT busena, COUNT(*) FROM uzsakymas WHERE vadybininko_id = ? GROUP BY busena');
$stmt->execute([$manoId]);
$kiekPagalBusena = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

function eur($s): string {
    return number_format((float)$s, 2, ',', ' ') . ' €';
}

$puslapioPavadinimas = 'Mano užsakymai';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="text-2xl font-semibold">Mano užsakymai</h1>
    <a href="daliu_paieska.php" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">+ Naujas užsakymas</a>
</div>

<!-- Filtras pagal būseną -->
<nav class="mb-6 flex flex-wrap gap-2 text-sm">
    <?php $visoSk = array_sum($kiekPagalBusena); ?>
    <a href="pateikti_uzsakymai.php"
       class="rounded-full px-4 py-1.5 border <?= $filtras === '' ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
        Visi <span class="opacity-60"><?= $visoSk ?></span>
    </a>
    <?php foreach ($busenos as $raktas => [$tekstas]): ?>
        <a href="?busena=<?= $raktas ?>"
           class="rounded-full px-4 py-1.5 border <?= $filtras === $raktas ? 'bg-slate-800 border-slate-800 text-white' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
            <?= $tekstas ?> <span class="opacity-60"><?= (int)($kiekPagalBusena[$raktas] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$uzsakymai): ?>
    <div class="rounded-xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
        <?= $filtras ? 'Užsakymų su šia būsena nėra.' : 'Jūs dar nepateikėte nė vieno užsakymo.' ?>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($uzsakymai as $u): ?>
            <?php
            $id       = (int)$u['uzsakymo_id'];
            $mano     = $eilutes[$id] ?? [];
            $galimaKeisti = $u['busena'] === 'Pateiktas';
            [$busTekstas, $busKlase] = $busenos[$u['busena']];
            ?>
            <details id="u<?= $id ?>" class="group bg-white rounded-xl shadow-sm border border-slate-200 open:border-sky-300">
                <summary class="flex flex-wrap items-center gap-x-6 gap-y-2 px-5 py-4 cursor-pointer list-none select-none">
                    <span class="font-semibold w-24">Nr. <?= $id ?></span>
                    <span class="text-sm text-slate-500 w-36"><?= date('Y-m-d H:i', strtotime($u['pateikimo_data'])) ?></span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $busKlase ?>"><?= $busTekstas ?></span>
                    <span class="text-sm text-slate-500"><?= count($mano) ?> poz.</span>
                    <span class="ml-auto font-semibold"><?= eur($u['suma']) ?></span>
                    <span class="text-slate-400 transition group-open:rotate-180">▾</span>
                </summary>

                <div class="border-t border-slate-200">
                    <form method="post">
                        <input type="hidden" name="uzsakymo_id" value="<?= $id ?>">
                        <input type="hidden" name="veiksmas" value="redaguoti">

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-left text-slate-600">
                                    <tr>
                                        <th class="px-5 py-3 font-medium">Dalis</th>
                                        <th class="px-5 py-3 font-medium">Tiekėjas</th>
                                        <th class="px-5 py-3 font-medium text-right">Vnt. kaina</th>
                                        <th class="px-5 py-3 font-medium text-center">Kiekis</th>
                                        <th class="px-5 py-3 font-medium text-right">Suma</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($mano as $e): ?>
                                        <?php $vnt = (float)$e['suma'] / max(1, (int)$e['kiekis']); ?>
                                        <tr>
                                            <td class="px-5 py-3">
                                                <div class="font-medium"><?= htmlspecialchars($e['pavadinimas']) ?></div>
                                                <div class="text-xs text-slate-500"><?= htmlspecialchars($e['gamintojas'] ?? '—') ?> · <?= htmlspecialchars($e['modelis'] ?? '—') ?></div>
                                            </td>
                                            <td class="px-5 py-3"><?= htmlspecialchars($e['tiekejas']) ?></td>
                                            <td class="px-5 py-3 text-right whitespace-nowrap"><?= eur($vnt) ?></td>
                                            <td class="px-5 py-3">
                                                <?php if ($galimaKeisti): ?>
                                                    <div class="mx-auto flex w-fit items-center rounded-lg border border-slate-300" data-kiekis>
                                                        <button type="button" data-zingsnis="-1" class="px-3 py-1 text-lg leading-none hover:bg-slate-100">−</button>
                                                        <input type="number" name="kiekis[<?= (int)$e['eilutes_id'] ?>]"
                                                               value="<?= (int)$e['kiekis'] ?>" min="0" max="<?= max((int)$e['sandelyje'], (int)$e['kiekis']) ?>"
                                                               class="w-14 border-0 text-center tabular-nums focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                                        <button type="button" data-zingsnis="1" class="px-3 py-1 text-lg leading-none hover:bg-slate-100">+</button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center"><?= (int)$e['kiekis'] ?> vnt.</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-3 text-right whitespace-nowrap font-medium"><?= eur($e['suma']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($galimaKeisti): ?>
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-slate-100 bg-slate-50 rounded-b-xl">
                                <p class="text-xs text-slate-500">Nustačius kiekį 0, dalis pašalinama iš užsakymo.</p>
                                <div class="flex gap-2">
                                    <button type="submit" form="atsaukti-<?= $id ?>"
                                            class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        Atšaukti užsakymą
                                    </button>
                                    <button type="submit"
                                            class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">
                                        Išsaugoti pakeitimus
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>

                    <?php if ($galimaKeisti): ?>
                        <form method="post" id="atsaukti-<?= $id ?>"
                              onsubmit="return confirm('Ar tikrai norite atšaukti užsakymą Nr. <?= $id ?>?');">
                            <input type="hidden" name="uzsakymo_id" value="<?= $id ?>">
                            <input type="hidden" name="veiksmas" value="atsaukti">
                        </form>
                    <?php else: ?>
                        <p class="px-5 py-3 text-xs text-slate-500 border-t border-slate-100">
                            Užsakymo, kurio būsena „<?= $busTekstas ?>“, keisti ar atšaukti nebegalima.
                        </p>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// +/- mygtukai prie kiekio
document.addEventListener('click', e => {
    const b = e.target.closest('[data-zingsnis]');
    if (!b) return;
    const input = b.closest('[data-kiekis]').querySelector('input');
    const n = (parseInt(input.value, 10) || 0) + parseInt(b.dataset.zingsnis, 10);
    input.value = Math.max(+input.min, Math.min(+input.max, n));
});

// Po redirect atidarom tą užsakymą, kurį ką tik keitėm
if (location.hash) {
    const el = document.querySelector(location.hash);
    if (el && el.tagName === 'DETAILS') el.open = true;
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
