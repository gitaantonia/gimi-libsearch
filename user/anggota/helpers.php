<?php
// Helper functions for anggota section

function getCategoryIcon($kategori)
{
    if ($kategori === 'ruang_komputer') {
        return '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>';
    } elseif ($kategori === 'ruang_diskusi') {
        return '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    } else {
        return '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>';
    }
}

function formatCategoryText($teks)
{
    return ucwords(str_replace('_', ' ', $teks));
}

function getFacilityData($db, $id_fasilitas)
{
    $query = mysqli_query($db, "SELECT * FROM fasilitas WHERE id = '$id_fasilitas'");
    return mysqli_fetch_assoc($query);
}

function getBookedSlots($db, $id_fasilitas, $tanggal)
{
    $sql_booking = "SELECT b.*, u.nama 
                    FROM bookings b 
                    JOIN anggota u ON b.id_anggota = u.id_anggota 
                    WHERE b.id_fasilitas = '$id_fasilitas' 
                    AND b.tanggal = '$tanggal' 
                    AND b.status_booking != 'cancelled'";
    $res_booking = mysqli_query($db, $sql_booking);

    $booked_slots = [];
    while ($row = mysqli_fetch_assoc($res_booking)) {
        $key = substr($row['jam_mulai'], 0, 5);
        $booked_slots[$key] = $row['nama'];
    }
    return $booked_slots;
}
?>