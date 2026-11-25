<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/funkcijos.php';

checkRole('Tiekejas');

$laukai  = DALIES_LAUKAI;
$klaidos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$laukai, $klaidos, $kaina] = tikrintiDali($pdo, $_POST, (int)$_SESSION['naudotojo_id']);

    if (!$klaidos) {
        $stmt = $pdo->prepare(
            'INSERT INTO preke (tiekejo_id, pavadinimas, gamintojas, modelis, kaina, kiekis)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['naudotojo_id'],
            $laukai['pavadinimas'],
            arbaNull($laukai['gamintojas']),
            arbaNull($laukai['modelis']),
            $kaina,
            (int)$laukai['kiekis'],
        ]);

        $_SESSION['zinute'] = ['tipas' => 'sekme', 'tekstas' => '„' . $laukai['pavadinimas'] . '“ pridėta prie jūsų siūlomų dalių.'];
        header('Location: prideti_dalis.php');
        exit;
    }
}

function laukas(string $name, string $label, string $value, array $klaidos, array $extra = []): void {
    $klaida = $klaidos[$name] ?? null;
    $privalomas = !empty($extra['required']);
    ?>
    <div>
        <label for="<?= $name ?>" class="block text-sm font-medium text-slate-700 mb-1">
            <?= htmlspecialchars($label) ?><?= $privalomas ? ' <span class="text-red-500">*</span>' : '' ?>
        </label>
        <div class="relative">
            <input id="<?= $name ?>" name="<?= $name ?>" value="<?= htmlspecialchars($value) ?>"
                   type="<?= $extra['type'] ?? 'text' ?>"
                   <?= isset($extra['inputmode']) ? 'inputmode="' . $extra['inputmode'] . '"' : '' ?>
                   <?= isset($extra['placeholder']) ? 'placeholder="' . htmlspecialchars($extra['placeholder']) . '"' : '' ?>
                   <?= isset($extra['maxlength']) ? 'maxlength="' . $extra['maxlength'] . '"' : '' ?>
                   <?= $privalomas ? 'required' : '' ?>
                   class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 <?= isset($extra['priedas']) ? 'pr-12' : '' ?> <?= $klaida ? 'border-red-400 focus:ring-red-400' : 'border-slate-300 focus:ring-sky-500' ?>">
            <?php if (isset($extra['priedas'])): ?>
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400"><?= $extra['priedas'] ?></span>
            <?php endif; ?>
        </div>
        <?php if ($klaida): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($klaida) ?></p>
        <?php endif; ?>
    </div>
<?php }

$puslapioPavadinimas = 'Pridėti dalį';
require __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl">
    <h1 class="text-2xl font-semibold mb-1">Pridėti siūlomą dalį</h1>
    <p class="text-slate-500 mb-6">Dalis iškart bus matoma vadybininkams paieškoje ir jūsų tiekėjo puslapyje.</p>

    <form method="post" novalidate class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-5">
        <?php laukas('pavadinimas', 'Pavadinimas', $laukai['pavadinimas'], $klaidos,
            ['required' => true, 'maxlength' => 100, 'placeholder' => 'pvz. Hidraulinis siurblys']); ?>

        <div class="grid gap-5 sm:grid-cols-2">
            <?php laukas('gamintojas', 'Gamintojas', $laukai['gamintojas'], $klaidos,
                ['maxlength' => 100, 'placeholder' => 'pvz. Parker']); ?>
            <?php laukas('modelis', 'Modelis', $laukai['modelis'], $klaidos,
                ['maxlength' => 100, 'placeholder' => 'pvz. PV-200']); ?>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <?php laukas('kaina', 'Kaina', $laukai['kaina'], $klaidos,
                ['required' => true, 'inputmode' => 'decimal', 'placeholder' => '0,00', 'priedas' => '€']); ?>
            <?php laukas('kiekis', 'Kiekis sandėlyje', $laukai['kiekis'], $klaidos,
                ['required' => true, 'type' => 'number', 'inputmode' => 'numeric', 'placeholder' => '0', 'priedas' => 'vnt.']); ?>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
            <a href="<?= $base ?>/index.php" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Atšaukti</a>
            <button class="rounded-lg bg-sky-600 px-5 py-2 font-medium text-white hover:bg-sky-700">Pridėti dalį</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
