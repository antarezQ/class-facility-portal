<?php

namespace App\Support;

/**
 * Menerjemahkan teks berbahasa Indonesia yang dibuat controller, service, dan
 * config ke bahasa Inggris, di lapisan tampilan saja.
 *
 * Teks yang tidak dikenali dikembalikan apa adanya: kalimat yang berubah di
 * controller, atau data dari database, tetap tampil dan tidak pernah kosong.
 */
class TerjemahPesan
{
    private const LABEL = [
        'Kode mata kuliah' => 'Course code', 'Kelas' => 'Class', 'Program studi' => 'Study program',
        'Dosen' => 'Lecturer', 'Kode dosen' => 'Lecturer code', 'NIP' => 'Staff ID', 'Ruangan' => 'Room',
        'Kapasitas ruangan' => 'Room capacity', 'Hari' => 'Day', 'Jam' => 'Time', 'Durasi' => 'Duration',
        'Jumlah mahasiswa' => 'Students', 'Berlaku mulai' => 'Valid from', 'Berlaku sampai' => 'Valid until',
        'Nomor pengajuan' => 'Request number', 'Keperluan' => 'Purpose', 'Pemohon' => 'Applicant',
        'Status pemohon' => 'Applicant status', 'NIM / NIP' => 'Student / Staff ID',
        'Unit / organisasi' => 'Unit / organization', 'Kontak' => 'Contact', 'Tanggal' => 'Date',
        'Jumlah peserta' => 'Participants', 'Status' => 'Status', 'Catatan petugas' => 'Staff note',
        'Pertemuan asal' => 'Original session', 'Tanggal pengganti' => 'Replacement date',
        'Diajukan oleh' => 'Requested by', 'Alasan' => 'Reason', 'Rentang' => 'Period',
    ];

    /** Kalimat utuh dan nilai singkat yang cocok persis. */
    private const UTUH = [
        // Jenis kegiatan dan status
        'Perkuliahan' => 'Class', 'Perkuliahan di luar waktu normal' => 'Class outside normal hours',
        'Kuliah pengganti' => 'Replacement class', 'Ruangan ditutup' => 'Room closed',
        'Reservasi Ruangan' => 'Room Reservation', 'Reservasi Pergantian Jadwal' => 'Class Reschedule Request',
        'Menunggu' => 'Pending', 'Disetujui' => 'Approved', 'Ditolak' => 'Rejected',
        // Hari
        'Senin' => 'Monday', 'Selasa' => 'Tuesday', 'Rabu' => 'Wednesday', 'Kamis' => 'Thursday',
        'Jumat' => 'Friday', 'Sabtu' => 'Saturday', 'Minggu' => 'Sunday',
        // Kartu menu
        'Cari Ruang Kosong' => 'Find a Free Room',
        'Cari ruangan bebas menurut tanggal, jam, dan kapasitas' => 'Search by date, time, and capacity',
        'Pinjam Aset' => 'Borrow Equipment',
        'Pinjam proyektor, mikrofon, kabel, dan perlengkapan lain' => 'Borrow projectors, microphones, cables, and more',
        'Dashboard Gedung' => 'Building Dashboard',
        'Daftar ruangan, jadwal perkuliahan, dan timeline ruangan' => 'Rooms, class schedule, and room timeline',
        // Modul penampung
        // Pesan controller
        'Anda sudah keluar.' => 'You have signed out.',
        'Tanggal yang dipilih jatuh pada hari Minggu, tidak ada jadwal perkuliahan.' => 'The selected date is a Sunday, so there are no classes.',
        'Jam selesai harus lebih besar daripada jam mulai.' => 'End time must be later than start time.',
        'Belum ada semester yang sedang berjalan, jadi pengajuan belum bisa diterima. Hubungi petugas.' => 'There is no current semester, so requests cannot be accepted yet. Please contact staff.',
        'Tanggal jatuh pada hari Minggu, gedung tidak beroperasi.' => 'The date is a Sunday, when the building is closed.',
        'Isi minimal satu pergantian.' => 'Add at least one change.',
        'Pilih minimal satu barang yang ingin dipinjam.' => 'Choose at least one item to borrow.',
    ];

    /**
     * Pola kalimat dari controller dan service. Diterapkan berurutan, sehingga
     * awalan seperti "Pergantian ke-2:" dan isi kalimatnya diterjemahkan terpisah.
     */
    private const POLA = [
        '/^Pergantian ke-(\d+): /' => 'Change $1: ',
        '/^Barang ke-(\d+): /' => 'Item $1: ',
        // Pengajuan
        '/Pengajuan (\S+) terkirim dengan (\d+) pergantian\. Petugas akan menghubungi Anda lewat WhatsApp\./' => 'Request $1 has been sent with $2 changes. Staff will contact you on WhatsApp.',
        '/Pengajuan (\S+) terkirim dengan (\d+) barang\. Petugas akan menghubungi Anda lewat WhatsApp\./' => 'Request $1 has been sent with $2 items. Staff will contact you on WhatsApp.',
        '/Pengajuan (\S+) terkirim\. Petugas akan menghubungi Anda lewat WhatsApp\./' => 'Request $1 has been sent. Staff will contact you on WhatsApp.',
        '/Tanggal ini di luar masa perkuliahan, jadi tidak ada kelas yang terganggu\./' => 'This date is outside the teaching period, so no classes are affected.',
        // Baris pergantian
        '/jam selesai harus setelah jam mulai\./' => 'the end time must be after the start time.',
        '/pertemuan (.+?) sudah dibatalkan pada pergantian ke-(\d+) di formulir ini\./' => 'the session on $1 is already cancelled in change $2 of this form.',
        '/perkuliahan pada (.+?) sudah diubah ke (.+?) \(sudah disetujui\)\. Bila yang ingin dipindah adalah pertemuan penggantinya, isi (.+?) sebagai tanggal yang dibatalkan\./' => 'the class on $1 has already moved to $2 (approved). To move the replacement session, enter $3 as the date to cancel.',
        '/perkuliahan pada (.+?) sudah diubah ke (.+?) \(masih menunggu persetujuan\)\. Bila yang ingin dipindah adalah pertemuan penggantinya, isi (.+?) sebagai tanggal yang dibatalkan\./' => 'the class on $1 has already moved to $2 (awaiting approval). To move the replacement session, enter $3 as the date to cancel.',
        '/mata kuliah ini dijadwalkan setiap hari (\w+), bukan (\w+)\./' => 'this course runs every $1, not $2.',
        '/tanggal itu di luar masa perkuliahan\./' => 'that date is outside the teaching period.',
        '/tanggal pengganti jatuh pada hari Minggu\./' => 'the replacement date is a Sunday.',
        '/dosen pengampu sedang mengajar di tempat lain pada jam itu\./' => 'the lecturer is teaching elsewhere at that time.',
        '/kelas (.+?) punya mata kuliah lain pada jam itu\./' => 'class $1 has another course at that time.',
        '/[Rr]uangan sedang ditutup pada (.+?) karena (.+?)\./' => 'the room is closed on $1 because of $2.',
        '/[Rr]uangan sudah dipesan pada (.+?)\./' => 'the room is already reserved at $1.',
        '/[Rr]uangan sudah dipakai kuliah pengganti (.+?) pada (.+?)\./' => 'the room is already used by replacement class $1 at $2.',
        '/[Rr]uangan dipakai (.+?) \((.+?)\)\./' => 'the room is used by $1 ($2).',
        // Pinjam aset
        '/tanggal kembali mendahului tanggal pinjam\./' => 'the return date is before the borrowing date.',
        '/tanggal pinjam sudah lewat\./' => 'the borrowing date has passed.',
        '/(\S+) sudah dipilih pada baris lain\./' => '$1 is already selected in another row.',
        '/(\S+) tidak ditandai boleh dipinjam publik\./' => '$1 is not available for public borrowing.',
        '/(\S+) dalam kondisi rusak\./' => '$1 is damaged.',
        '/(\S+) sedang dipinjam \((\S+)\) sampai (.+?)\./' => '$1 is on loan ($2) until $3.',
        '/(\S+) berstatus (.+?)\./' => '$1 has the status $2.',
        // Nilai panel rincian
        '/^(\d+) orang$/' => '$1 people',
        '/^(\d+) kursi$/' => '$1 seats',
        '/^(\d+) jam (\d+) menit$/' => '$1 h $2 min',
        '/^(\d+) jam$/' => '$1 h',
        '/^(\d+) menit$/' => '$1 min',
        '/^Ditutup: /' => 'Closed: ',
        '/ · pengganti /' => ' · replaces ',
        // Nama atribut pada pesan validasi bawaan Laravel
        '/\bnama pemohon\b/' => 'applicant name',
        '/\bnomor WhatsApp\b/' => 'WhatsApp number',
        '/\bjam mulai\b/' => 'start time',
        '/\bjam selesai\b/' => 'end time',
        '/\bfield ruangan\b/' => 'field room',
        '/\bThe ruangan\b/' => 'The room',
    ];

    public static function teks(?string $teks): string
    {
        $teks = (string) $teks;

        if ($teks === '' || app()->getLocale() !== 'en') {
            return $teks;
        }

        $persis = self::UTUH[$teks] ?? self::UTUH[ucfirst($teks)] ?? null;

        if ($persis !== null) {
            return $persis;
        }

        foreach (self::POLA as $pola => $ganti) {
            $teks = preg_replace($pola, $ganti, $teks) ?? $teks;
        }

        if (! str_contains($teks, 'runs every')) {
            return $teks;
        }

        // Nama hari di dalam kalimat "setiap hari Senin, bukan Selasa".
        return preg_replace_callback(
            '/\b(Senin|Selasa|Rabu|Kamis|Jumat|Sabtu|Minggu)\b/',
            fn ($m) => self::UTUH[$m[1]],
            $teks
        ) ?? $teks;
    }

    public static function label(string $label): string
    {
        return app()->getLocale() === 'en' ? (self::LABEL[$label] ?? $label) : $label;
    }

    /**
     * Panel rincian: label selalu diterjemahkan, sedangkan nilainya hanya bila
     * dikenali, supaya data seperti nama mata kuliah tetap utuh.
     *
     * @param  array<int, array{label: string, nilai: string}>  $baris
     * @return array<int, array{label: string, nilai: string}>
     */
    public static function baris(array $baris): array
    {
        return array_map(fn (array $b) => [
            'label' => self::label($b['label']),
            'nilai' => self::teks($b['nilai']),
        ], $baris);
    }
}
