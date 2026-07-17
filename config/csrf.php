<?php
/**
 * Proteksi CSRF sederhana — token per sesi (bukan per-form), cukup untuk
 * aplikasi internal ini. Semua file yang include ini harus sudah panggil
 * session_start() lebih dulu.
 */

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Cetak hidden input berisi token — taruh di dalam setiap <form method="POST">. */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/** Panggil di awal handler POST/GET yang mengubah data. Berhenti kalau token tidak cocok. */
function csrfVerify(): void {
    $sent = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        die('Sesi tidak valid atau kedaluwarsa. Silakan muat ulang halaman dan coba lagi.');
    }
}
