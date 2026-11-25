<?php

// Mygtukas „Į krepšelį“ – duomenis paima includes/krepselis.js
function krepselioMygtukas(array $preke, string $tiekejas, bool $mazas = false): string
{
    $kiekis = (int)$preke['kiekis'];
    $dydis  = $mazas ? 'px-3 py-1.5 text-xs' : 'px-4 py-2 text-sm w-full';

    if ($kiekis <= 0) {
        return '<button type="button" disabled class="rounded-lg bg-slate-100 text-slate-400 cursor-not-allowed ' . $dydis . '">Nėra sandėlyje</button>';
    }

    $attr = [
        'data-id'         => (int)$preke['prekes_id'],
        'data-pavadinimas'=> $preke['pavadinimas'],
        'data-tiekejas'   => $tiekejas,
        'data-gamintojas' => $preke['gamintojas'] ?? '',
        'data-modelis'    => $preke['modelis'] ?? '',
        'data-kaina'      => $preke['kaina'],
        'data-sandelyje'  => $kiekis,
    ];
    $html = '';
    foreach ($attr as $k => $v) {
        $html .= ' ' . $k . '="' . htmlspecialchars((string)$v) . '"';
    }

    return '<button type="button" data-i-krepseli' . $html
         . ' class="rounded-lg bg-sky-600 font-medium text-white hover:bg-sky-700 whitespace-nowrap ' . $dydis . '">+ Į krepšelį</button>';
}
