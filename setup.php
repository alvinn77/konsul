
<?php
require 'db.php';

$accounts = [
    ['Mahasiswa Demo', '12345678', '12345', 'mahasiswa'],
    ['Tia Tanjung', '12345', '12345', 'dosen']
];

foreach ($accounts as $account) {
    [$nama, $npm, $plainPassword, $role] = $account;

    $check = $pdo->prepare('SELECT id FROM users WHERE npm = ?');
    $check->execute([$npm]);

    if (!$check->fetch()) {
        $insert = $pdo->prepare(
            'INSERT INTO users (nama, npm, password, role)
             VALUES (?, ?, ?, ?)'
        );

        $insert->execute([
            $nama,
            $npm,
            password_hash($plainPassword, PASSWORD_DEFAULT),
            $role
        ]);
    }
}

echo 'Akun demo berhasil disiapkan. Hapus file setup.php setelah selesai.';
