export function arrivalErrorMessage(error) {
    const status = error?.response?.status;
    const errors = Object.values(error?.response?.data?.errors || {}).flat().filter(value => typeof value === 'string');
    if (errors.length) return [...new Set(errors)].join(' ');
    if (status === 413) return 'Foto ditolak karena batas ukuran upload server. Konfigurasi PHP/proxy perlu diperiksa.';
    if (status === 401 || status === 419) return 'Sesi login berakhir. Masuk kembali sebelum mengirim kedatangan.';
    if (status === 403) return 'Akses unit atau izin kedatangan tidak tersedia untuk akun ini.';
    if (status >= 500) return 'Terjadi kesalahan server saat menyimpan kedatangan. Periksa riwayat sebelum mencoba kembali.';
    if (error?.request && !error?.response) return 'Tidak ada respons server. Periksa koneksi dan riwayat sebelum mencoba kembali.';
    return error?.message || 'Pengiriman kedatangan gagal. Silakan periksa kembali data unit.';
}

export function submitArrivalUnits(ids, cards) {
    return Promise.allSettled(ids.map(async id => {
        if (!cards[id]?.send) throw new Error('Form unit belum siap. Pilih kembali unit dan coba lagi.');
        if (await cards[id].send() !== true) throw new Error('Server belum mengonfirmasi penyimpanan unit. Periksa riwayat kedatangan.');
        return true;
    }));
}
