/*******************************
Telepítési útmutató a Laragon-hoz
(szükséges ahhoz hogy a böngésző URL-ben https-el jelenjen meg a link)
*******************************/
1. lépés:
Töltsük le a telepítőt a Laragon hivatalos oldaláról (laragon.org->download menü)
A szoftver használható ingyen is: Laragon engedélyez „non-commercial, unlicensed” használatot, ami személyes / nem-kereskedelmi projektekhez elég.

2. lépés:
Telepítsük és jegyezzük meg melyik meghajtóra lett telepítve.
A telepítés során hagyhatunk mindent alapértelmezetten, vagy ha valaki nem szeretné a C meghajtóra telepíteni akkor az elérési útvonalt érdemes megjegyezni.

3. lépés (opcionális):
Apache port átállítása 8080-ra:
 3.1. Menu -> Apache > httpd.conf megnyitása:
    Itt keresd ezt a részt:
    Listen 80
    Cseréld erre:
    Listen 8080
 3.2.(Szintén) Menu -> Apache -> httpd.conf
    Keresd meg ezt a sort:
    ServerName localhost:80
    Írd át:
    ServerName localhost:8080
 Ha nincs ilyen sor: ServerName localhost:80; akkor csak szimplán be kell illeszteni: ServerName localhost:8080

Majd Mentés + Apache restart.

4. lépés:
HTTPS engedélyezés (Laragon automatikusan tud tanúsítványt generálni), ehhez be kell állítani:
Menu → Preferences → Services & Ports:
    Pipáld be SSL: Auto-create SSL certificates
    Indítsd újra Laragont
Ezután a projekted automatikusan ilyen URL-t kap:
Pl: https://projektem.test:8080

5. lépés (opcionális):
Amennyiben az adatbázis MySQL és ehhez a Laragon álltal biztosított phpMyAdmin közeget szeretnénk használni:
Próbáljuk meg megnyitni: Menu -> MySQL -> phpMyAdmin;
Ha az alábbi oldalra visz:
https://laragon.org/docs/operations
Akkor még szükséges hozzá adni a laragonhoz phpMyAdmin-t (ahogy az oldal is írja: Laragon doesn't include phpMyAdmin by default but you can add it easily:)
Menu -> Tools -> Quick add -> phpmyadmin
Majd ha ez kész nyissuk meg újra a bönészően a phpMyAdmin-t (vagy a Laragonban: Menu -> MySQL -> phpMyAdmin),
ahol várhatóen kér egy bejelentkezést amihhez a fájlok közt megtalálhatjuk az Username-et és a Password-t, vagy ki is kapcsolhatjuk, az utóbbihoz:
Meg kell keresni a következő fájlt: config.inc.php; aminek az elérési útja (ha csak nem telepítettük egyedi helyre):
C:\laragon\etc\apps\phpMyAdmin\config.inc.php
Ebben a fájlban megkeressük ezt:
    $cfg['Servers'][$i]['auth_type'] = 'cookie';
És lecseréljük:
    $cfg['Servers'][$i]['auth_type'] = 'config';
És így a phpMyAdmin autómatikusan be fog jelentkezni a root felhasználóval.

6. lépés:
Ezután belkapcsoljuk a lokál szervereket és elindítjuk az SSL-t is:
Menu -> Apache -> SSL -> Enabled
Ha SSL be van kapcsolva, és futnak a szolgáltatások:
    -A projekted elérhető lesz így:
        https://*projektem*.test:8080

7. lépés (opcionális):
Ahhoz hogy a teljes default XAMPP-os környezetet elérjük még szükség van 2 változtatásra:
7.1: Az adatbázis létrehozásánál nem a laragon álltal alapértelmezettnek használt karakterkódolás kell, hanem: utf8mb4_general_ci

7.2: Ki kell kapcsolni az ONLY_FULL_GROUP_BY -t (vagy azokat a lekérdezéseket ahol nem így van/volt eredetileg átírni, hogy az összes mezőt, ami szerepel a SELECT-ben szerepeltetni kell a GROUP BY részben is
    7.2.1: Nyisd meg a my.ini fájlt:
        laragon\bin\mysql\mysql-8.4\my.ini
    7.2.2: Add hozzá az alábbi sort a [mysqld] szekció alá:
        [mysqld]
        sql_mode=""
Laragon és a projekt újra töltése és kész.
