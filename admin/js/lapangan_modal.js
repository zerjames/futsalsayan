// Lapangan functions
function showAddModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Lapangan';
    document.getElementById('lapangan_id').value = '';
    document.getElementById('nama').value = '';
    document.getElementById('deskripsi').value = '';
    document.getElementById('harga_per_jam').value = '';
    document.getElementById('gambar').value = '';
    document.getElementById('form_action').value = 'add';
    document.getElementById('current_image').classList.add('hidden');
    
    // Reset the form completely
    const form = document.getElementById('lapanganForm');
    if (form) {
        form.reset();
    }
    
    document.getElementById('lapanganModal').classList.remove('hidden');
}

function showEditModal(lapangan) {
    document.getElementById('modalTitle').textContent = 'Edit Lapangan';
    document.getElementById('lapangan_id').value = lapangan.id_lapangan;
    document.getElementById('nama').value = lapangan.nama;
    document.getElementById('deskripsi').value = lapangan.deskripsi;
    document.getElementById('harga_per_jam').value = lapangan.harga_per_jam;
    document.getElementById('form_action').value = 'edit';
    if(lapangan.gambar) {
        document.getElementById('preview_image').src = '../uploads/lapangan/' + lapangan.gambar;
        document.getElementById('current_image').classList.remove('hidden');
    } else {
        document.getElementById('current_image').classList.add('hidden');
    }
    document.getElementById('lapanganModal').classList.remove('hidden');
}

function showEditPaketModal(id, tipe, harga, keterangan, gambar) {
    document.getElementById('paketModalTitle').textContent = 'Edit Harga Paket';
    document.getElementById('paket_action').value = 'edit';
    document.getElementById('paket_id').value = id;
    document.getElementById('paket_harga').value = harga;
    document.getElementById('paket_keterangan').value = keterangan;
    if(gambar && gambar !== 'null') {
        document.getElementById('preview_paket_image').src = '../uploads/paket/' + gambar;
        document.getElementById('current_paket_image').classList.remove('hidden');
    } else {
        document.getElementById('current_paket_image').classList.add('hidden');
    }
    if (tipe.startsWith('turnamen_lap')) {
        document.getElementById('paket_type').value = 'turnamen';
        document.getElementById('turnamenFields').classList.remove('hidden');
        document.getElementById('eskulFields').classList.add('hidden');
        const lapangan_id = tipe.match(/lap(\d+)/)[1];
        const jenis = tipe.endsWith('_sore') ? 'sore' : 'malam';
        document.getElementById('paket_lapangan').value = lapangan_id;
        document.getElementById('paket_jenis').value = jenis;
    } else if (tipe.startsWith('eskul_lap')) {
        document.getElementById('paket_type').value = 'eskul';
        document.getElementById('eskulFields').classList.remove('hidden');
        document.getElementById('turnamenFields').classList.add('hidden');
        const lapangan_id = tipe.match(/lap(\d+)/)[1];
        document.getElementById('paket_lapangan_eskul').value = lapangan_id;
    } else {
        document.getElementById('turnamenFields').classList.add('hidden');
        document.getElementById('eskulFields').classList.add('hidden');
    }
    document.getElementById('paketModal').classList.remove('hidden');
}

function showAddPaketModal(type) {
    document.getElementById('paketModalTitle').textContent = 'Tambah Paket';
    document.getElementById('paket_action').value = 'add';
    document.getElementById('paket_id').value = '';
    document.getElementById('paket_harga').value = '';
    document.getElementById('paket_keterangan').value = '';
    document.getElementById('paket_type').value = type;
    
    // Hide current image preview
    document.getElementById('current_paket_image').classList.add('hidden');
    
    // Set default values first
    if (type === 'turnamen') {
        document.getElementById('turnamenFields').classList.remove('hidden');
        document.getElementById('eskulFields').classList.add('hidden');
        document.getElementById('paket_lapangan').value = '1';
        document.getElementById('paket_jenis').value = 'sore';
    } else if (type === 'eskul') {
        document.getElementById('eskulFields').classList.remove('hidden');
        document.getElementById('turnamenFields').classList.add('hidden');
        document.getElementById('paket_lapangan_eskul').value = '1';
    } else {
        document.getElementById('turnamenFields').classList.add('hidden');
        document.getElementById('eskulFields').classList.add('hidden');
    }
    
    // Reset form but preserve the values we just set
    const paketForm = document.querySelector('#paketModal form');
    if (paketForm) {
        // Don't reset the form completely, just clear the input fields
        const inputs = paketForm.querySelectorAll('input[type="text"], input[type="number"], textarea');
        inputs.forEach(input => {
            if (input.name !== 'paket_type' && input.name !== 'action' && input.name !== 'id_paket') {
                input.value = '';
            }
        });
    }
    
    document.getElementById('paketModal').classList.remove('hidden');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        // Reset form when closing modal
        if (modalId === 'lapanganModal') {
            const form = document.getElementById('lapanganForm');
            if (form) {
                form.reset();
            }
        } else if (modalId === 'paketModal') {
            // Reset paket form fields
            const paketForm = modal.querySelector('form');
            if (paketForm) {
                paketForm.reset();
            }
            // Hide all field groups
            document.getElementById('eskulFields').classList.add('hidden');
            document.getElementById('turnamenFields').classList.add('hidden');
            document.getElementById('current_paket_image').classList.add('hidden');
        }
    }
}

function toggleStatus(id, newStatus) {
    if (confirm('Apakah Anda yakin ingin mengubah status lapangan ini?')) {
        const formData = new FormData();
        formData.append('id_lapangan', id);
        formData.append('new_status', newStatus);
        formData.append('action', 'toggle_status');
        fetch('proses_lapangan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            window.location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengubah status lapangan');
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const modals = ['lapanganModal', 'paketModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(modalId);
                }
            });
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal(modalId);
                }
            });
        }
    });

    // Event listener untuk semua tombol batal lapangan
    document.querySelectorAll('.cancelLapanganBtn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            closeModal('lapanganModal');
        });
    });
    // Event listener untuk semua tombol batal paket
    document.querySelectorAll('.cancelPaketBtn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            closeModal('paketModal');
        });
    });

    // Add form submit handlers (debug)
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            console.log('Form submitted:', this.action);
            console.log('Form data:', new FormData(this));
            // Debug untuk form paket
            if (this.hasAttribute('action') && typeof this.action === 'string' && this.action.includes('proses_harga_paket.php')) {
                const formData = new FormData(this);
                console.log('Paket form data:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
            }
        });
    });
}); 