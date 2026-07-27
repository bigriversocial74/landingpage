<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$role = current_user()['role'] ?? 'client';
logout_user();
redirect('portal/login.php?role=' . $role);
