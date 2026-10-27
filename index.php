
<?php
session_start();
require 'db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $npm = trim($_POST['npm'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE npm = ?');
        $stmt->execute([$npm]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $user['id'],
                'nama' => $user['nama'],
                'npm' => $user['npm'],
                'role' => $user['role']
            ];
            header('Location: index.php');
            exit;
        }

        $error = 'ID login atau password salah.';
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if (isset($_SESSION['user'])) {
        $user = $_SESSION['user'];

        if ($action === 'tambah' && $user['role'] === 'mahasiswa') {
            $matkul = trim($_POST['mata_kuliah'] ?? '');
            $pertanyaan = trim($_POST['pertanyaan'] ?? '');

            if ($matkul === '' || $pertanyaan === '') {
                $error = 'Mata kuliah dan pertanyaan wajib diisi.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO konsultasi
                    (nama_mahasiswa, npm, mata_kuliah, pertanyaan)
                    VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([
                    $user['nama'],
                    $user['npm'],
                    $matkul,
                    $pertanyaan
                ]);
                $success = 'Konsultasi berhasil dikirim.';
            }
        }

        if ($action === 'update' && $user['role'] === 'dosen') {
            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? 'Menunggu';
            $catatan = trim($_POST['catatan_dosen'] ?? '');

            $allowed = ['Menunggu', 'Diproses', 'Selesai'];

            if (in_array($status, $allowed, true)) {
                $stmt = $pdo->prepare(
                    'UPDATE konsultasi
                     SET status = ?, catatan_dosen = ?
                     WHERE id = ?'
                );
                $stmt->execute([$status, $catatan, $id]);
                $success = 'Tindak lanjut berhasil disimpan.';
            } else {
                $error = 'Status tidak valid.';
            }
        }
    }
}

$user = $_SESSION['user'] ?? null;
$rows = [];

if ($user) {
    if ($user['role'] === 'dosen') {
        $stmt = $pdo->query('SELECT * FROM konsultasi ORDER BY created_at DESC');
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM konsultasi WHERE npm = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$user['npm']]);
        $rows = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Konsultasi Akademik</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
<header class="bg-blue-700 text-white p-5">
    <div class="max-w-5xl mx-auto flex justify-between items-center gap-3">
        <div>
            <h1 class="text-xl font-bold">Sistem Konsultasi Akademik</h1>
            <p class="text-sm text-blue-100">Layanan konsultasi mahasiswa dan dosen</p>
        </div>
        <?php if ($user): ?>
            <form method="post">
                <input type="hidden" name="action" value="logout">
                <button class="bg-white text-blue-700 rounded-lg px-3 py-2">
                    Keluar
                </button>
            </form>
        <?php endif; ?>
    </div>
</header>

<main class="max-w-5xl mx-auto p-4 md:p-6">
    <?php if ($error): ?>
        <div class="mb-4 rounded-lg bg-red-100 p-3 text-red-700">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="mb-4 rounded-lg bg-green-100 p-3 text-green-700">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!$user): ?>
        <section class="max-w-md mx-auto mt-10 bg-white rounded-2xl shadow p-6">
            <h2 class="text-2xl font-bold mb-2">Login</h2>
            <p class="text-slate-500 mb-5">Masuk sebagai mahasiswa atau dosen.</p>
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="block mb-1 font-medium">NPM / ID login</label>
                    <input name="npm" required
                        class="w-full border rounded-lg p-3"
                        placeholder="Masukkan NPM atau ID login">
                </div>
                <div>
                    <label class="block mb-1 font-medium">Password</label>
                    <input type="password" name="password" required
                        class="w-full border rounded-lg p-3"
                        placeholder="Masukkan password">
                </div>
                <button class="w-full bg-blue-700 text-white rounded-lg p-3 font-semibold">
                    Masuk
                </button>
            </form>
            <div class="mt-5 bg-slate-50 rounded-lg p-3 text-sm">
                <p class="font-semibold mb-1">Akun demo</p>
                <p>Mahasiswa: 12345678 / 12345</p>
                <p>Dosen: 12345 / 12345</p>
            </div>
        </section>

    <?php else: ?>
        <section class="bg-white rounded-2xl shadow p-5 mb-6">
            <p class="text-sm text-slate-500">Selamat datang</p>
            <h2 class="text-2xl font-bold"><?= e($user['nama']) ?></h2>
            <p class="text-blue-700">
                Peran: <?= e(ucfirst($user['role'])) ?>
            </p>
        </section>

        <?php if ($user['role'] === 'mahasiswa'): ?>
            <section class="bg-white rounded-2xl shadow p-5 mb-6">
                <h2 class="text-xl font-bold mb-4">Ajukan Konsultasi</h2>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="tambah">
                    <div>
                        <label class="block mb-1 font-medium">Mata kuliah</label>
                        <input name="mata_kuliah" required
                            class="w-full border rounded-lg p-3"
                            placeholder="Contoh: Basis Data">
                    </div>
                    <div>
                        <label class="block mb-1 font-medium">Pertanyaan / kendala</label>
                        <textarea name="pertanyaan" required rows="4"
                            class="w-full border rounded-lg p-3"
                            placeholder="Tuliskan pertanyaan konsultasi..."></textarea>
                    </div>
                    <button class="bg-blue-700 text-white rounded-lg px-5 py-3">
                        Kirim Konsultasi
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <section class="bg-white rounded-2xl shadow p-5">
            <h2 class="text-xl font-bold mb-4">
                <?= $user['role'] === 'dosen' ? 'Semua Konsultasi' : 'Riwayat Konsultasi Saya' ?>
            </h2>

            <?php if (!$rows): ?>
                <p class="text-slate-500">Belum ada data konsultasi.</p>
            <?php endif; ?>

            <div class="space-y-4">
                <?php foreach ($rows as $r): ?>
                    <article class="border rounded-xl p-4">
                        <div class="flex flex-wrap justify-between gap-2 mb-3">
                            <h3 class="font-bold text-lg"><?= e($r['mata_kuliah']) ?></h3>
                            <span class="rounded-full bg-blue-100 text-blue-800 px-3 py-1 text-sm">
                                <?= e($r['status']) ?>
                            </span>
                        </div>

                        <?php if ($user['role'] === 'dosen'): ?>
                            <p class="text-sm text-slate-500 mb-2">
                                Mahasiswa: <?= e($r['nama_mahasiswa']) ?>
                                (<?= e($r['npm']) ?>)
                            </p>
                        <?php endif; ?>

                        <p class="font-medium">Pertanyaan</p>
                        <p class="mb-3 whitespace-pre-wrap"><?= e($r['pertanyaan']) ?></p>

                        <p class="font-medium">Catatan dosen</p>
                        <p class="mb-3 whitespace-pre-wrap">
                            <?= e($r['catatan_dosen'] ?: 'Belum ada catatan.') ?>
                        </p>

                        <p class="text-xs text-slate-500 mb-4">
                            Dikirim: <?= e($r['created_at']) ?>
                        </p>

                        <?php if ($user['role'] === 'dosen'): ?>
                            <form method="post" class="border-t pt-4 space-y-3">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                                <div>
                                    <label class="block mb-1 font-medium">Status</label>
                                    <select name="status" class="w-full border rounded-lg p-3">
                                        <?php foreach (['Menunggu', 'Diproses', 'Selesai'] as $s): ?>
                                            <option value="<?= e($s) ?>"
                                                <?= $r['status'] === $s ? 'selected' : '' ?>>
                                                <?= e($s) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block mb-1 font-medium">Catatan / tindak lanjut</label>
                                    <textarea name="catatan_dosen" rows="3"
                                        class="w-full border rounded-lg p-3"
                                        placeholder="Tulis arahan untuk mahasiswa..."><?= e($r['catatan_dosen']) ?></textarea>
                                </div>

                                <button class="bg-emerald-600 text-white rounded-lg px-4 py-2">
                                    Simpan Tindak Lanjut
                                </button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<footer class="text-center text-slate-500 text-sm p-6">
    Apps by Alvinestarss © 2026
</footer>
</body>
</html>
