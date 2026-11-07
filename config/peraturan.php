<?php

/*
|--------------------------------------------------------------------------
| Peraturan peminjaman
|--------------------------------------------------------------------------
| Ditampilkan sebagai pop-up sebelum formulir terbuka. Ubah, tambah, atau
| hapus butirnya di sini — tidak perlu menyentuh tampilan.
|
| Teks resmi berbahasa Inggris dan tampil sama di mode EN maupun ID.
*/

// Nomor ini juga dipakai di config/jadwal.php. Dibaca dari env langsung
// karena satu berkas config tidak bisa memanggil config() yang lain saat
// aplikasi baru dinyalakan.
$wa = env('PORTAL_WA_TAMPIL', '+62 800-0000-0000');

return [
    'ruangan' => [
        'judul'  => 'Room Reservation Terms',
        'butir'  => [
            'Submit your request at least 1 working day before the date of use.',
            'The room may only be used on the approved date and time. To extend your time, please contact the service office on WhatsApp (' . $wa . ').',
            'You are responsible for keeping the room clean and tidy, including returning chairs and tables to their original positions.',
            'You are responsible for any facilities damaged or lost during use.',
            "The room may not be passed to anyone else without the service office's knowledge.",
            "Cancel through the service office's WhatsApp no later than the day before, so the room can be used for other purposes.",
        ],
        'penutup' => 'By submitting a request, you agree to the terms above.',
    ],

    'pergantian' => [
        'judul' => 'Class Reschedule Terms',
        'butir' => [
            'Requests may be submitted by lecturers or students, with the knowledge of the course lecturer.',
            'Submit your request at least 1 working day before the session being cancelled.',
            'The replacement class must be the same length as the original class.',
            'The replacement time must not clash with other courses of the lecturer or the class.',
            'The change applies only after approval from the service unit.',
        ],
        'penutup' => 'I have read and agree to the applicable terms.',
    ],
];
