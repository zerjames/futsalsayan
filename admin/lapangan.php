<?php
// Include database dan functions di awal
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include header setelah semua logic PHP dan redirect selesai
require_once '../includes/header.php';
?>

<!-- Add page-specific styles -->
<style>
    /* Modal styles */
    .hidden {
        display: none !important;
    }
    
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 50;
    }

    .modal-content {
        background-color: white;
        padding: 1.5rem;
        border-radius: 0.5rem;
        max-width: 32rem;
        width: 100%;
        margin: 1rem;
    }

    /* Form styles */
    input[type="text"],
    input[type="number"],
    textarea {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        margin-top: 0.25rem;
    }

    input[type="text"]:focus,
    input[type="number"]:focus,
    textarea:focus {
        outline: none;
        border-color: #10b981;
        ring: 2px;
        ring-color: #10b981;
    }
</style>

<div class="container mx-auto px-4 py-8">
    <!-- Page Title -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Kelola Lapangan & Paket</h1>
    </div>

    <?php include 'includes/nav_buttons.php'; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <?php include 'includes/lapangan_table.php'; ?>
</div>

<script>
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
    document.getElementById('lapanganModal').classList.remove('hidden');
}

function showEditModal(lapangan) {
    document.getElementById('modalTitle').textContent = 'Edit Lapangan';
    document.getElementById('lapangan_id').value = lapangan.id;
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

// Paket functions
function showEditPaketModal(id, tipe, harga, keterangan, gambar) {
    console.log('Opening modal with:', { id, tipe, harga, keterangan, gambar });
    
    document.getElementById('paketModalTitle').textContent = 'Edit Harga Paket';
    document.getElementById('paket_action').value = 'edit';
    document.getElementById('paket_id').value = id;
    document.getElementById('paket_harga').value = harga;
    document.getElementById('paket_keterangan').value = keterangan;
    
    // Handle gambar preview
    if(gambar && gambar !== 'null') {
        document.getElementById('preview_paket_image').src = '../uploads/paket/' + gambar;
        document.getElementById('current_paket_image').classList.remove('hidden');
    } else {
        document.getElementById('current_paket_image').classList.add('hidden');
    }
    
    // Show appropriate fields based on package type
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
    
    document.getElementById('paketModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function toggleStatus(id, newStatus) {
    if (confirm('Apakah Anda yakin ingin mengubah status lapangan ini?')) {
        const csrfToken = window.csrfToken || (document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : '');
        const formData = new FormData();
        formData.append('id', id);
        formData.append('id_lapangan', id);
        formData.append('new_status', newStatus);
        formData.append('action', 'toggle_status');
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }

        fetch('proses_lapangan.php', {
            method: 'POST',
            body: formData,
            headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}
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

// Close modals when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const modals = ['lapanganModal', 'paketModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(modalId);
                }
            });
        }
    });

    // Add form submit handlers
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            console.log('Form submitted:', this.action);
            console.log('Form data:', new FormData(this));
            
            // Debug untuk form paket
            if (this.action.includes('proses_harga_paket.php')) {
                const formData = new FormData(this);
                console.log('Paket form data:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
            }
        });
    });
});
</script>
