function showEditPaketModal(id, tipe, harga, keterangan) {
    // Set form values
    document.getElementById('paket_id').value = id;
    document.getElementById('paket_tipe').value = tipe;
    document.getElementById('paket_harga').value = harga;
    document.getElementById('paket_keterangan').value = keterangan.replace(/\\'/g, "'");
    
    // Show modal
    document.getElementById('paketModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Close modals when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('paketModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal('paketModal');
        }
    });
});
