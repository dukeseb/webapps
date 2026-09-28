<?php

declare(strict_types=1);

require __DIR__ . '/src/Auth.php';

Auth::logout();
header('Location: /login.php');
exit;
