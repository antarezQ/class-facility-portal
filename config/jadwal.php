<?php

return [
    'nama_instansi' => env('PORTAL_NAMA', 'Class Facility Portal'),
    'nama_pendek'   => env('PORTAL_NAMA_PENDEK', 'Facility Portal'),

    // Nomor contoh. Isi lewat .env saat dipasang sungguhan.
    'nomor_wa'        => env('PORTAL_WA', '080000000000'),
    'nomor_wa_tampil' => env('PORTAL_WA_TAMPIL', '+62 800-0000-0000'),

    // Hanya ruangan berawalan salah satu di sini yang bisa dicari dan direservasi.
    'ruangan_dikelola' => ['A', 'B'],

    // Jam operasional gedung, sekaligus batas waktu normal perkuliahan.
    'jam_buka'  => '06:30',
    'jam_tutup' => '18:30',

    // Batas akhir sumbu waktu pada Timeline dan Papan. Melar sendiri bila ada
    // kegiatan yang melewatinya.
    'jam_tampil_akhir' => '20:30',

    // Hari yang dihitung sebagai kapasitas ruang, dihitung dari Senin.
    'hari_operasional' => 5,
    'pekan_efektif'    => 16,

    'hari' => ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU'],

    // Urutan gedung saat ruangan ditampilkan.
    'urutan_gedung' => ['A', 'B'],

    // Kapasitas ruangan diperkirakan dari jumlah mahasiswa terbanyak,
    // dibulatkan ke atas ke kelipatan berikut.
    'pembulatan_kapasitas' => 5,
];
