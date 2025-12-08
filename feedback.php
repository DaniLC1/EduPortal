<?php
// Success üzenetek listája
$success_messages = [
    1 => "A tárgy sikeresen mentve!",
    2 => "A tárgy sikeresen módosítva!",
    3 => "Sikeresen felvett kurzus.",
    4 => "Sikeresen leadott kurzus.",
    5 => "Dolgozat beadása sikeres!",
    6 => "Dolgozat sikeresen mentve/módosítva!",
    7 => "Üzenet sikeresen mentve.",
    8 => "Üzenet sikeresen módosítva.",
    9 => "Üzenet sikeresen törölve/visszavonva!",
    10 => "A jegy sikeresen módosítva!",
    11 => "Az adatok sikeresen mentve.",
    12 => "Felhasználó sikeresen rögzítve!",
    13 => "Kérelem sikeresen beadva.",
    14 => "A kérelem inaktiválva.",
    15 => "A kérelelm törölve!",
    16 => "Meglévő kérelelm frissítve (verziózva).",
    17 => "Meglévő kérelelm frissítve.",
    18 => "Hozzászólás/javaslat sikeresen mentve",
];
?>

<?php if (isset($_GET['success'])): ?>
    <?php
    $code = (int) $_GET['success'];
    $msg = $success_messages[$code] ?? "Sikeres művelet.";
    ?>
    <div class="success-message">
        ✅ <?= htmlspecialchars($msg) ?>
    </div>
    <hr>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="error-message">
        ⚠️ <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <hr>
<?php endif; ?>

