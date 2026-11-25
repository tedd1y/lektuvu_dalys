// Krepšelis saugomas naršyklėje (localStorage), atskirai kiekvienam naudotojui.
// Serveriui siunčiami tik prekių ID ir kiekiai, kainas serveris ima iš DB.
const Krepselis = (() => {
    const KEY = 'krepselis_' + (document.body.dataset.naudotojas || '0');

    function gauti() {
        try {
            const d = JSON.parse(localStorage.getItem(KEY));
            return Array.isArray(d) ? d : [];
        } catch (e) {
            return [];
        }
    }

    function issaugoti(prekes) {
        try { localStorage.setItem(KEY, JSON.stringify(prekes)); } catch (e) {}
        atnaujintiZenkliuka();
        document.dispatchEvent(new CustomEvent('krepselis:pasikeite'));
    }

    function prideti(preke) {
        const prekes = gauti();
        const esama = prekes.find(p => p.id === preke.id);
        if (esama) {
            esama.kiekis = Math.min(esama.kiekis + 1, preke.sandelyje);
            Object.assign(esama, { kaina: preke.kaina, sandelyje: preke.sandelyje });
        } else {
            prekes.push({ ...preke, kiekis: 1 });
        }
        issaugoti(prekes);
    }

    function nustatytiKieki(id, kiekis) {
        const prekes = gauti();
        const p = prekes.find(p => p.id === id);
        if (!p) return;
        p.kiekis = Math.max(1, Math.min(kiekis, p.sandelyje));
        issaugoti(prekes);
    }

    function pasalinti(id) {
        issaugoti(gauti().filter(p => p.id !== id));
    }

    function isvalyti() {
        issaugoti([]);
    }

    function kiekisIsViso() {
        return gauti().reduce((s, p) => s + p.kiekis, 0);
    }

    function atnaujintiZenkliuka() {
        const n = kiekisIsViso();
        document.querySelectorAll('[data-krepselio-kiekis]').forEach(el => {
            el.textContent = n;
            el.classList.toggle('hidden', n === 0);
        });
    }

    // „Į krepšelį“ mygtukai: <button data-i-krepseli data-id=".." data-pavadinimas=".." ...>
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-i-krepseli]');
        if (!btn) return;
        e.preventDefault();

        const d = btn.dataset;
        const sandelyje = parseInt(d.sandelyje, 10) || 0;
        if (sandelyje <= 0) return;

        const esama = gauti().find(p => p.id === parseInt(d.id, 10));
        if (esama && esama.kiekis >= sandelyje) {
            pranesti(btn, 'Daugiau nėra');
            return;
        }

        prideti({
            id: parseInt(d.id, 10),
            pavadinimas: d.pavadinimas,
            tiekejas: d.tiekejas,
            gamintojas: d.gamintojas || '',
            modelis: d.modelis || '',
            kaina: parseFloat(d.kaina),
            sandelyje: sandelyje,
        });
        pranesti(btn, '✓ Pridėta');
    });

    function pranesti(btn, tekstas) {
        if (!btn.dataset.tekstas) btn.dataset.tekstas = btn.innerHTML;
        btn.innerHTML = tekstas;
        clearTimeout(btn._laikas);
        btn._laikas = setTimeout(() => { btn.innerHTML = btn.dataset.tekstas; }, 1200);
    }

    // Kitame skirtuke pakeitus krepšelį – atnaujinam ženkliuką ir čia
    window.addEventListener('storage', e => {
        if (e.key === KEY) {
            atnaujintiZenkliuka();
            document.dispatchEvent(new CustomEvent('krepselis:pasikeite'));
        }
    });

    atnaujintiZenkliuka();

    return { gauti, nustatytiKieki, pasalinti, isvalyti };
})();
