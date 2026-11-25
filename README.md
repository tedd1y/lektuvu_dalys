# Lėktuvų dalių užsakymų sistema

Internetinė sistema lėktuvų dalims užsakyti. Naudojami PHP, MySQL/MariaDB ir Tailwind CSS.

## Reikalavimai

- **XAMPP** (arba kitas Apache + PHP + MySQL/MariaDB rinkinys)
  - PHP **8.1** ar naujesnis
  - MariaDB 10.4+ arba MySQL 8+
- **Interneto ryšys.** Tailwind CSS kraunamas iš CDN, todėl be interneto puslapiai bus be stilių.

## Paleidimas

### 1. Įdiekite ir paleiskite XAMPP

1. Atsisiųskite XAMPP iš <https://www.apachefriends.org> ir įdiekite.
2. Atidarykite XAMPP valdymo skydelį.
   - **macOS**: `manager-osx`
   - **Windows**: `xampp-control.exe`
3. Paleiskite **Apache** ir **MySQL**.

### 2. Nukopijuokite projektą

Projekto aplankas **privalo vadintis `lektuvu_dalys`**, nes kode naudojamas kelias `/lektuvu_dalys`.

Jį reikia įdėti į XAMPP `htdocs` aplanką:

| OS      | Kelias                                             |
|---------|----------------------------------------------------|
| macOS   | `/Applications/XAMPP/xamppfiles/htdocs/lektuvu_dalys` |
| Windows | `C:\xampp\htdocs\lektuvu_dalys`                     |
| Linux   | `/opt/lampp/htdocs/lektuvu_dalys`                   |

Su git:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs
git clone <repozitorijos-adresas> lektuvu_dalys
```

Arba tiesiog nukopijuokite aplanką.

### 3. Importuokite duomenų bazę

`database.sql` pats sukuria DB `lektuvu_dalys` su visomis lentelėmis ir testiniais duomenimis.

**Per phpMyAdmin:**

1. Atidarykite <http://localhost/phpmyadmin>.
2. Viršuje pasirinkite **Import**.
3. Pasirinkite failą `database.sql` ir spauskite **Import**.

**Arba per terminalą:**

```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root < database.sql
```

Windows terminale:

```
C:\xampp\mysql\bin\mysql -u root < database.sql
```

> Pakartotinai importuojant esamos lentelės ištrinamos ir sukuriamos iš naujo.

### 4. Patikrinkite prisijungimą prie DB

Numatyti nustatymai yra faile `config/db.php`:

```php
'mysql:host=localhost;dbname=lektuvu_dalys;charset=utf8mb4',
'root', '',
```

Standartiniame XAMPP vartotojas `root` neturi slaptažodžio, todėl nieko keisti nereikia. Jei jūsų MySQL turi kitą vartotoją ar slaptažodį, pakeiskite juos šiame faile.

### 5. Atidarykite sistemą

<http://localhost/lektuvu_dalys/>

## Prisijungimas

Testiniai naudotojai iš `database.sql`:

| Prieiga      | El. paštas                     |
|--------------|--------------------------------|
| Vadybininkas | `vadybininkas.saulius@lekt.lt` |
| Tiekėjas     | `info@srotas.lt`               |
| Tiekėjas     | `info@ran.com`                 |
| Tiekėjas     | `info@balticaero.lt`           |
| Direktorius  | `direktorius.algis@lekt.lt`    |

Slaptažodžiai visų naudotojų yra **Slapta**:

1. Užsiregistruokite per <http://localhost/lektuvu_dalys/registracija.php>.

   Nauja paskyra gauna prieigą **„Naudotojas“**, kuri neturi jokių teisių. Prieigą suteikia direktorius.

2. Pirmąjį direktorių nustatykite tiesiai DB, phpMyAdmin skiltyje **SQL**:

   ```sql
   UPDATE lektuvu_dalys.naudotojas
   SET tipas = 'Direktorius'
   WHERE pastas = 'jusu@pastas.lt';
   ```

3. Prisijungę kaip direktorius, kitiems naudotojams prieigą keiskite puslapyje **Naudotojai**.

## Rolės

| Prieiga      | Ką gali                                                                                     |
|--------------|---------------------------------------------------------------------------------------------|
| Naudotojas   | Prisiregistruoti, prisijungti ir atsijungti. Laukia, kol direktorius suteiks prieigą         |
| Vadybininkas | Peržiūrėti tiekėjus ir jų dalis, ieškoti ir lyginti dalis, pateikti užsakymus, redaguoti ar atšaukti dar nepriimtus užsakymus |
| Tiekėjas     | Pridėti, redaguoti ir trinti savo dalis, peržiūrėti gautus užsakymus ir keisti jų būseną      |
| Direktorius  | Peržiūrėti naudotojus ir keisti jų prieigą, matyti užsakymų statistiką                       |

---

Titas Štuopis IFA-5-6
