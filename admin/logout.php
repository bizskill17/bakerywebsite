<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

$_SESSION = [];
session_regenerate_id(true);
flash('You have been logged out.');
redirect('login.php');
