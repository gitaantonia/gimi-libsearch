# 🚀 GiMi - Facility Booking History & Real-time Countdown

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-777bb4.svg?logo=php)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-f7df1e.svg?logo=javascript)
![License](https://img.shields.io/badge/license-MIT-green.svg)

**GiMi** adalah modul riwayat peminjaman fasilitas yang interaktif. Fokus utama modul ini adalah memberikan pengalaman pengguna yang informatif dengan fitur **Live Countdown** yang menunjukkan sisa waktu pemakaian fasilitas secara *real-time* tanpa perlu memuat ulang halaman.

---

## ✨ Fitur Utama

-   **🕒 Smart Real-time Countdown**: 
    -   *Menuju Mulai:* Memberitahu pengguna berapa lama lagi sesi akan dimulai.
    -   *Sisa Waktu:* Menampilkan sisa waktu pemakaian saat sesi sedang berlangsung secara dinamis.
    -   *Auto-expiry:* Secara otomatis mengubah status tampilan jika sesi telah berakhir.
-   **🛡️ Secure Session**: Integrasi pengecekan login untuk memastikan data hanya dapat diakses oleh pemilik akun.
-   **📱 Responsive Design**: Antarmuka bersih menggunakan font *Plus Jakarta Sans* yang optimal di perangkat mobile maupun desktop.
-   **🎨 Status Badging**: Label status (Pending, Confirmed, Cancelled) dengan kode warna untuk identifikasi cepat.

---

## 🛠️ Teknologi yang Digunakan

| Komponen | Deskripsi |
| :--- | :--- |
| **Backend** | PHP 7.4+ dengan MySQLi Prepared Statements |
| **Frontend** | HTML5, CSS3 (Custom Variables), JavaScript (Vanilla ES6) |
| **Database** | MySQL / MariaDB |
| **Typography** | Plus Jakarta Sans via Google Fonts |

---

## 📦 Instalasi & Penggunaan

1.  **Persyaratan**: Pastikan Anda memiliki folder `regis/` yang berisi `koneksi.php` dan file `helpers.php` di direktori yang sama.
2.  **Database**: Pastikan tabel `bookings` memiliki kolom:
    -   `tanggal` (DATE)
    -   `jam_mulai` & `jam_selesai` (TIME)
    -   `status_booking` (ENUM/VARCHAR)
3.  **Deploy**: Salin file kode ke server lokal (XAMPP/Laragon) atau hosting Anda.
4.  **Akses**: Login sebagai anggota dan buka halaman ini untuk melihat riwayat booking Anda.

---

## 📸 Pratinjau Tampilan Logika

> **Kondisi Menunggu:**
> `⏱️ Mulai dalam: 0j 45m 10s`
> 
> **Kondisi Berlangsung:**
> `⏳ Sisa waktu: 0j 20m 05s` (Teks berwarna Hijau)
>
> **Kondisi Selesai:**
> `✅ Sesi telah berakhir` (Teks berwarna Abu-abu)

---

## 📝 Catatan Teknis

Sistem *countdown* bekerja menggunakan **Unix Timestamp**. Waktu server (PHP) dikonversi menjadi detik dan dilempar ke atribut `data-start` dan `data-end` pada HTML. JavaScript kemudian mengambil waktu lokal klien untuk menghitung selisihnya setiap 1000ms (1 detik).

---

## 🤝 Kontribusi

Kontribusi selalu terbuka! Silakan lakukan *fork* pada repositori ini dan kirimkan *pull request* untuk fitur-fitur baru atau perbaikan bug.

---

Developed with ❤️ by **GiMi Team**