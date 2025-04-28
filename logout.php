<?php
session_start();        // Szükséges a session kezeléséhez
session_unset();        // Minden session változó törlése
session_destroy();      // Session lezárása

header("Location: index.php"); // Visszairányítás a főoldalra
exit();                 // Megállítja a script futását
?>