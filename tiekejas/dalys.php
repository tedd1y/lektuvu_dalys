<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

checkRole('Tiekejas');

$manoId = (int)$_SESSION['naudotojo_id'];
$q      = trim($_GET['q'] ?? '');

function atgal(string $tipas, string $tekstas, int $dalisId = 0): never {
    global $q;
    $_SESSION['zinute'] = ['tipas' => $tipas, 'tekstas' => $tekstas];
    header('Location: dalys.php' . ($q !== '' ? '?q=' . urlencode($q) : '') . ($dalisId ? "#d$dalisId" : ''));
    exit;
}

// Grąžina mano dalį arba null (svetimų dalių nei redaguoti, nei trinti negalima)
function manoDalis(PDO $pdo, int $id, int $manoId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM preke WHERE prekes_id = ? AND tiekejo_id = ?');
    $stmt->execute([$id, $manoId]);
    return $stmt->fetch() ?: null;
}

$redaguojamaId = (int)($_GET['redaguoti'] ?? 0);
$laukai  = null;   // redaguojamos dalies reikšmės formoje
$klaidos = [];

// --- Veiksmai ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['prekes_id'] ?? 0);
    $dalis = manoDalis($pdo, $id, $manoId);
    if (!$dalis) {
        atgal('klaida', 'Dalis nerasta.');
    }

    if (($_POST['veiksmas'] ?? '') === 'istrinti') {
        // Dalies, kuri jau yra užsakymuose, ištrinti negalima (išliktų užsakymų istorija)
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT uzsakymo_id) FROM uzsakymo_preke WHERE prekes_id = ?');
        $stmt->execute([$id]);
        $uzsakymu = (int)$stmt->fetchColumn();

        if ($uzsakymu > 0) {
            atgal('klaida', "„{$dalis['pavadinimas']}“ ištrinti negalima, nes ji yra $uzsakymu užsakyme(-uose). "
                . 'Jei nebenorite jos siūlyti, nustatykite kiekį 0.', $id);
        }

        $pdo->prepare('DELETE FROM preke WHERE prekes_id = ? AND tiekejo_id = ?')->execute([$id, $manoId]);
        atgal('sekme', "„{$dalis['pavadinimas']}“ ištrinta.");
    }

    if (($_POST['veiksmas'] ?? '') === 'redaguoti') {
        [$laukai, $klaidos, $kaina] = tikrintiDali($pdo, $_POST, $manoId, $id);

        if (!$klaidos) {
            $stmt = $pdo->prepare(
                'UPDATE preke SET pavadinimas = ?, gamintojas = ?, modelis = ?, kaina = ?, kiekis = ?
                 WHERE prekes_id = ? AND tiekejo_id = ?'
            );
            $stmt->execute([
                $laukai['pavadinimas'],
                arbaNull($laukai['gamintojas']),
                arbaNull($laukai['modelis']),
                $kaina,
                (int)$laukai['kiekis'],
                $id,
                $manoId,
            ]);
            atgal('sekme', "„{$laukai['pavadinimas']}“ atnaujinta.", $id);
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

// Redaguojamos dalies pradinės reikšmės (jei dar ne iš POST)
if ($redaguojamaId && $laukai === null) {
    $d = manoDalis($pdo, $redaguojamaId, $manoId);
    if ($d) {
        $laukai = [
            'pavadinimas' => $d['pavadinimas'],
            'gamintojas'  => $d['gamintojas'] ?? '',
            'modelis'     => $d['modelis'] ?? '',
            'kaina'       => number_format((float)$d['kaina'], 2, ',', ''),
            'kiekis'      => (string)$d['kiekis'],
        ];
    } else {
        $redaguojamaId = 0;
    }
}

function eur($k): string {
    return number_format((float)$k, 2, ',', ' ') . ' €';
}

// Įvesties laukas redaguojamoje eilutėje
function ivestis(string $name, array $laukai, array $klaidos, string $klases = '', array $attr = []): string {
    $klaida = $klaidos[$name] ?? null;
    $papildomi = '';
    foreach ($attr as $k => $v) {
        $papildomi .= " $k=\"" . htmlspecialchars((string)$v) . '"';
    }
    $html = '<input form="redaguoti-forma" name="' . $name . '" value="' . htmlspecialchars($laukai[$name]) . '"' . $papildomi
          . ' class="w-full rounded-md border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 ' . $klases . ' '
          . ($klaida ? 'border-red-400 focus:ring-red-400' : 'border-slate-300 focus:ring-sky-500') . '">';
    if ($klaida) {
        $html .= '<p class="mt-1 text-xs text-red-600 font-normal">' . htmlspecialchars($klaida) . '</p>';
    }
    return $html;
}

$puslapioPavadinimas = 'Mano dalys';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <h1 class="text-2xl font-semibold">Mano siūlomos dalys</h1>
    <a href="prideti_dalis.php" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">+ Pridėti dalį</a>
</div>

<form method="get" class="mb-6 flex gap-3 max-w-xl">
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ieškoti tarp savo dalių…"
           class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500">
    <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Ieškoti</button>
</form>

<?php if ($redaguojamaId): ?>
    <form id="redaguoti-forma" method="post" action="dalys.php<?= $q !== '' ? '?q=' . htmlspecialchars(urlencode($q)) : '' ?>" novalidate>
        <input type="hidden" name="veiksmas" value="redaguoti">
        <input type="hidden" name="prekes_id" value="<?= $redaguojamaId ?>">
    </form>
<?php endif; ?>

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
                    <th class="px-4 py-3 font-medium text-center">Užsakymuose</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($dalys as $d): ?>
                    <?php $id = (int)$d['prekes_id']; ?>

                    <?php if ($id === $redaguojamaId): ?>
                        <!-- Redagavimo eilutė -->
                        <tr id="d<?= $id ?>" class="bg-sky-50 align-top">
                            <td class="px-4 py-3 min-w-[12rem]"><?= ivestis('pavadinimas', $laukai, $klaidos, '', ['maxlength' => 100, 'required' => 'required', 'autofocus' => 'autofocus']) ?></td>
                            <td class="px-4 py-3 min-w-[8rem]"><?= ivestis('gamintojas', $laukai, $klaidos, '', ['maxlength' => 100]) ?></td>
                            <td class="px-4 py-3 min-w-[8rem]"><?= ivestis('modelis', $laukai, $klaidos, '', ['maxlength' => 100]) ?></td>
                            <td class="px-4 py-3 w-32"><?= ivestis('kaina', $laukai, $klaidos, 'text-right', ['inputmode' => 'decimal']) ?></td>
                            <td class="px-4 py-3 w-28"><?= ivestis('kiekis', $laukai, $klaidos, 'text-right', ['type' => 'number', 'min' => 0]) ?></td>
                            <td class="px-4 py-3 text-center text-slate-500 pt-5"><?= (int)$d['uzsakymu'] ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <button form="redaguoti-forma" class="rounded-md bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-700">Išsaugoti</button>
                                <a href="dalys.php<?= $q !== '' ? '?q=' . htmlspecialchars(urlencode($q)) : '' ?>#d<?= $id ?>"
                                   class="ml-1 rounded-md px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-200">Atšaukti</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr id="d<?= $id ?>" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($d['pavadinimas']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($d['gamintojas'] ?? '—') ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($d['modelis'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><?= eur($d['kaina']) ?></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?= (int)$d['kiekis'] > 0 ? (int)$d['kiekis'] . ' vnt.' : '<span class="text-red-600">Nėra</span>' ?>
                            </td>
                            <td class="px-4 py-3 text-center text-slate-500"><?= (int)$d['uzsakymu'] ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <a href="?<?= htmlspecialchars(http_build_query(array_filter(['q' => $q, 'redaguoti' => $id]))) ?>#d<?= $id ?>"
                                   class="rounded-md px-3 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-50">Redaguoti</a>
                                <?php if ((int)$d['uzsakymu'] === 0): ?>
                                    <form method="post" class="inline"
                                          onsubmit="return confirm('Ar tikrai norite ištrinti „<?= htmlspecialchars(addslashes($d['pavadinimas'])) ?>“?');">
                                        <input type="hidden" name="veiksmas" value="istrinti">
                                        <input type="hidden" name="prekes_id" value="<?= $id ?>">
                                        <button class="rounded-md px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Ištrinti</button>
                                    </form>
                                <?php else: ?>
                                    <span title="Dalis yra užsakymuose, todėl jos ištrinti negalima. Nustatykite kiekį 0."
                                          class="cursor-help rounded-md px-3 py-1.5 text-xs text-slate-300">Ištrinti</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-slate-500">
        Pakeitus kainą, jau pateiktų užsakymų sumos nesikeičia. Dalies, kuri yra užsakymuose, ištrinti negalima. Jei nebenorite jos siūlyti, nustatykite kiekį 0.
    </p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
