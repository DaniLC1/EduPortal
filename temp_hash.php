<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Jelszó Hash Generátor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
        }
        input, button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            font-size: 1rem;
        }
        .result {
            margin-top: 15px;
            padding: 10px;
            background: #fff;
            border-radius: 6px;
            word-break: break-all;
        }
    </style>
</head>
<body>
<h2>Jelszó Hash Generátor</h2>


<form method="POST">
    <label>Jelszó:</label>
    <input type="text" name="password" required />
    <button type="submit">Generálás</button>
</form>


<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<div class='result'><strong>Hashelt jelszó:</strong><br> $hash</div>";
}
?>
</body>
</html>
