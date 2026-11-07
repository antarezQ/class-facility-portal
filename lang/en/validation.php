<?php

// Hanya nama kolom. Kalimatnya memakai bawaan Laravel, yang digabung otomatis.
return [
    'attributes' => [
        'identitas' => 'student / staff ID', 'peran' => 'status', 'unit' => 'unit',
        'unit_organisasi' => 'unit', 'kontak' => 'WhatsApp number', 'keperluan' => 'purpose',
        'tanggal' => 'date', 'jumlah_peserta' => 'participants', 'ruangan_id' => 'room', 'baris' => 'changes',
        'baris.*.jadwal_id' => 'course', 'baris.*.tanggal_asal' => 'date of the session to cancel',
        'baris.*.tanggal' => 'replacement date', 'baris.*.mulai' => 'start time',
        'baris.*.selesai' => 'end time', 'baris.*.ruangan_id' => 'replacement room',
        'baris.*.aset_unit_id' => 'item', 'baris.*.tanggal_pinjam' => 'date of use',
        'baris.*.tanggal_kembali' => 'return date',
    ],
];
