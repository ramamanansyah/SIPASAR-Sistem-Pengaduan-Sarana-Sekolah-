// SIPASAR - JavaScript Functions
// Auth & Session Management

const AUTH_KEY = 'sipasar_auth_session';
const ENCRYPTION_KEY = 'sipasar_secure_key'; // Simple key for basic XOR
const SESSION_DURATION = 24 * 60 * 60 * 1000; // 24 hours

const AuthService = {
    // Basic XOR Encryption/Decryption
    cipher: function(text) {
        const key = ENCRYPTION_KEY;
        let result = '';
        for (let i = 0; i < text.length; i++) {
            result += String.fromCharCode(text.charCodeAt(i) ^ key.charCodeAt(i % key.length));
        }
        return result;
    },

    encrypt: function(data) {
        try {
            const jsonStr = JSON.stringify(data);
            const xored = this.cipher(jsonStr);
            return btoa(xored); // Base64 encode after XOR
        } catch (e) {
            console.error('Encryption failed', e);
            return null;
        }
    },

    decrypt: function(encryptedData) {
        try {
            const xored = atob(encryptedData);
            const jsonStr = this.cipher(xored);
            return JSON.parse(jsonStr);
        } catch (e) {
            console.error('Decryption failed', e);
            return null;
        }
    },

    saveSession: function(userData) {
        const sessionData = {
            ...userData,
            timestamp: new Date().getTime(),
            expiry: new Date().getTime() + SESSION_DURATION
        };
        const encrypted = this.encrypt(sessionData);
        localStorage.setItem(AUTH_KEY, encrypted);
    },

    getSession: function() {
        const encrypted = localStorage.getItem(AUTH_KEY);
        if (!encrypted) return null;

        const session = this.decrypt(encrypted);
        if (!session) return null;

        // Check expiry
        if (new Date().getTime() > session.expiry) {
            this.clearSession();
            return null;
        }

        return session;
    },

    clearSession: function() {
        localStorage.removeItem(AUTH_KEY);
    },

    isAuthenticated: function() {
        return !!this.getSession();
    },

    getUserRole: function() {
        const session = this.getSession();
        return session ? session.role : null;
    }
};

// Middleware to check auth status
function authMiddleware() {
    const path = window.location.pathname;
    const session = AuthService.getSession();
    const isLoginPage = path.includes('index.php') || path.endsWith('/') || path.includes('register.php');

    if (session) {
        // User is logged in
        if (isLoginPage) {
            // Redirect to appropriate page
            redirectToDashboard(session.role);
        } else {
            // We are on an internal page.
            // Check if the current page matches the role (basic check)
            if (session.role === 'siswa' && path.includes('admin')) {
                window.location.href = 'siswa_dashboard.php';
            } else if (session.role === 'admin' && path.includes('siswa')) {
                window.location.href = 'admin_dashboard.php';
            }
        }
    } else {
        // User is not logged in
        if (!isLoginPage) {
            // Redirect to login
            showSessionExpiredModal();
        }
    }
}

function redirectToDashboard(role) {
    if (role === 'siswa') {
        window.location.href = 'siswa_dashboard.php';
    } else {
        window.location.href = 'admin_dashboard.php'; // User requested "Tanggapan Baru"
    }
}

function showSessionExpiredModal() {
    // Check if we already showed it to avoid loops
    if (document.getElementById('sessionExpiredModal')) return;


    const modalHtml = `
    <div id="sessionExpiredModal" class="modal" style="display:block; background: rgba(0,0,0,0.8);">
        <div class="modal-content">
            <h3>Sesi Berakhir</h3>
            <p>Sesi login Anda telah berakhir. Silakan login kembali.</p>
            <div class="modal-buttons">
                <button onclick="window.location.href='index.php'" class="btn btn-primary">Login Kembali</button>
            </div>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

// Initialize Middleware on load
document.addEventListener('DOMContentLoaded', () => {
    authMiddleware();
    // Only start refresh if on dashboard
    if (document.querySelector('.dashboard-body') || document.querySelector('.form-container')) {
         // Check if we are logged in before starting refresh
         if (AuthService.isAuthenticated()) {
             startAutoRefresh();
             restoreDraft(); // Restore draft if exists
             
             // Clear draft if success message is present on dashboard
             if (document.querySelector('.alert-success') && window.location.pathname.includes('siswa_dashboard.php')) {
                 clearDraft();
             }
         }
    }
    
    // Auto-save draft listeners
    const aspirasiForm = document.getElementById('aspirasiForm');
    if (aspirasiForm) {
        const inputs = aspirasiForm.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', saveDraft);
            input.addEventListener('change', saveDraft);
        });
    }
});

// Draft Management
const DRAFT_KEY = 'sipasar_aspirasi_draft';

function saveDraft() {
    const form = document.getElementById('aspirasiForm');
    if (!form) return;

    const draft = {
        kategori: form.querySelector('[name="kategori"]').value,
        lokasi: form.querySelector('[name="lokasi"]').value,
        keterangan: form.querySelector('[name="keterangan"]').value,
        timestamp: new Date().getTime()
    };

    localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
}

function restoreDraft() {
    const form = document.getElementById('aspirasiForm');
    if (!form) return;

    const savedDraft = localStorage.getItem(DRAFT_KEY);
    if (!savedDraft) return;

    try {
        const draft = JSON.parse(savedDraft);
        
        // Only restore if less than 24 hours old
        if (new Date().getTime() - draft.timestamp < 24 * 60 * 60 * 1000) {
            if (draft.kategori) form.querySelector('[name="kategori"]').value = draft.kategori;
            if (draft.lokasi) form.querySelector('[name="lokasi"]').value = draft.lokasi;
            if (draft.keterangan) form.querySelector('[name="keterangan"]').value = draft.keterangan;
            
            if (draft.kategori || draft.lokasi || draft.keterangan) {
                showNotification('Draft aspirasi dipulihkan', 'info');
            }
        } else {
            localStorage.removeItem(DRAFT_KEY);
        }
    } catch (e) {
        console.error('Error restoring draft', e);
    }
}

function clearDraft() {
    localStorage.removeItem(DRAFT_KEY);
}

// AJAX Login Handler
async function handleLogin(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.textContent;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.textContent = 'Memproses...';
    
    try {
        const response = await fetch('proses_login.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Save session to localStorage
            AuthService.saveSession({
                role: result.role,
                username: result.username,
                name: result.name,
                token: result.token // Server provided token
            });
            
            // Redirect
            redirectToDashboard(result.role);
        } else {
            // Show error
            const errorDiv = document.querySelector('.alert-error') || document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.textContent = result.message;
            if (!document.querySelector('.alert-error')) {
                form.insertBefore(errorDiv, form.firstChild);
            }
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
        }
    } catch (error) {
        console.error('Login error:', error);
        alert('Terjadi kesalahan koneksi');
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
    }
}

// Logout Handler
function handleLogout(event) {
    event.preventDefault();
    AuthService.clearSession();
    window.location.href = 'logout.php';
}

// Attach logout handler if link exists
document.addEventListener('click', (e) => {
    if (e.target.matches('.logout-btn') || e.target.closest('.logout-btn')) {
        handleLogout(e);
    }
});

// Auto-refresh configuration
let autoRefreshInterval;
const REFRESH_INTERVAL = 5000; // 5 detik

// Dashboard Auto-Refresh Functions
function startAutoRefresh() {
    // Langsung mulai auto-refresh
    refreshDashboardData();
    checkNotifications();
    
    autoRefreshInterval = setInterval(() => {
        refreshDashboardData();
        checkNotifications();
    }, REFRESH_INTERVAL);
}

// Refresh dashboard data via AJAX
function refreshDashboardData() {
    fetch('api/get_aspirasi.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatsCards(data.stats);
                updateAspirasiTable(data.aspirasi);
                updateLastRefreshTime(data.last_update);
                
                // Show notification if there's new data
                checkForNewData(data);
            }
        })
        .catch(error => {
            console.error('Error refreshing data:', error);
        });
}

// Update statistics cards
function updateStatsCards(stats) {
    const responded = (stats.proses || 0) + (stats.selesai || 0);
    const elements = {
        'stat-total': stats.total,
        'stat-menunggu': stats.menunggu,
        'stat-proses': stats.proses,
        'stat-selesai': stats.selesai,
        'stat-ditanggapi': responded
    };
    
    Object.keys(elements).forEach(id => {
        const element = document.querySelector(`.${id} .stat-number`);
        if (element) {
            const newValue = elements[id];
            const oldValue = parseInt(element.textContent);
            
            if (newValue !== oldValue) {
                element.textContent = newValue;
                element.classList.add('updated');
                setTimeout(() => element.classList.remove('updated'), 1000);
            }
        }
    });
}

// Update aspirasi table
function updateAspirasiTable(aspirasi) {
    const tableBody = document.querySelector('table tbody');
    if (!tableBody) return;
    
    const currentRowCount = tableBody.children.length;
    const newRowCount = aspirasi.length;
    
    // Clear existing rows
    tableBody.innerHTML = '';
    
    // Add new rows
    aspirasi.forEach((item, index) => {
        const row = createTableRow(item, index + 1);
        tableBody.appendChild(row);
    });
    
    // Show notification if new data added
    if (newRowCount > currentRowCount) {
        const newItems = newRowCount - currentRowCount;
        showNotification(`${newItems} data baru ditambahkan`, 'success');
    }
}

// Create table row for aspirasi
function createTableRow(item, no) {
    const row = document.createElement('tr');
    const role = document.body.dataset.role || 'siswa';
    
    if (role === 'admin') {
        row.innerHTML = `
            <td>${no}</td>
            <td>${item.nisn}</td>
            <td>${item.nama}</td>
            <td>${item.kelas}</td>
            <td>${item.ket_kategori}</td>
            <td>${item.lokasi}</td>
            <td>${truncateText(item.keterangan, 50)}</td>
            <td>${formatDate(item.tanggal)}</td>
            <td><span class="status status-${item.status.toLowerCase()}">${item.status}</span></td>
            <td>${item.feedback ? truncateText(item.feedback, 30) : '-'}</td>
            <td>
                <div class="action-buttons">
                    <button class="icon-btn icon-edit btn-edit" data-tooltip="Edit" aria-label="Edit" onclick="editAspirasi(${item.id_aspirasi}, '${item.status}', '${escapeHtml(item.feedback || '')}')">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M3 17.25V21h3.75l11-11-3.75-3.75-11 11zm14.71-9.04a1.003 1.003 0 000-1.42l-2.5-2.5a1.003 1.003 0 00-1.42 0l-1.83 1.83 3.75 3.75 2-1.66z"/>
                        </svg>
                    </button>
                    <button class="icon-btn icon-finish btn-save" type="button" data-tooltip="Selesai" aria-label="Selesai" onclick="markAspirasiSelesai(${item.id_aspirasi})">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/>
                        </svg>
                    </button>
                    <button class="icon-btn icon-delete btn-delete" type="button" data-tooltip="Hapus" aria-label="Hapus" onclick="deleteAspirasi(${item.id_aspirasi})">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2z"/>
                        </svg>
                    </button>
                </div>
            </td>
        `;
    } else {
        row.innerHTML = `
            <td>${no}</td>
            <td>${item.ket_kategori}</td>
            <td>${item.lokasi}</td>
            <td class="keterangan-cell">${item.keterangan}</td>
            <td>${formatDate(item.tanggal)}</td>
            <td><span class="status status-${item.status.toLowerCase()}">${item.status}</span></td>
            <td class="feedback-cell">${item.feedback || '-'}</td>
        `;
    }
    
    return row;
}

// Check for notifications
function checkNotifications() {
    fetch('api/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications.length > 0) {
                displayNotifications(data.notifications);
            }
        })
        .catch(error => {
            console.error('Error checking notifications:', error);
        });
}

// Display notifications
function displayNotifications(notifications) {
    const container = document.getElementById('notification-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    notifications.forEach(notification => {
        const notifElement = document.createElement('div');
        notifElement.className = `notification notification-${notification.type}`;
        notifElement.innerHTML = `
            <span class="notification-message">${notification.message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">×</button>
        `;
        container.appendChild(notifElement);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notifElement.parentElement) {
                notifElement.remove();
            }
        }, 5000);
    });
}

// Show temporary notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `temp-notification temp-notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Update last refresh time
function updateLastRefreshTime(timestamp) {
    const element = document.getElementById('last-refresh');
    if (element) {
        const safeTimestamp = timestamp ? timestamp.replace(' ', 'T') : '';
        const date = safeTimestamp ? new Date(safeTimestamp) : new Date();
        const day = String(date.getDate()).padStart(2, '0');
        const month = date.toLocaleString('id-ID', { month: 'short' });
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        element.textContent = `Terakhir diperbarui: ${day} ${month} ${year}, ${hours}:${minutes}`;
    }
}

// Check for new data
let lastDataCount = 0;
function checkForNewData(data) {
    const currentCount = data.aspirasi.length;
    if (lastDataCount > 0 && currentCount > lastDataCount) {
        const newItems = currentCount - lastDataCount;
        showNotification(`${newItems} data baru tersedia!`, 'success');
    }
    lastDataCount = currentCount;
}

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Login & Register Role Selection
function selectRole(role) {
    // Reset semua button
    document.querySelectorAll('.role-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Aktifkan button yang dipilih
    event.target.classList.add('active');
    document.getElementById('selectedRole').value = role;
    
    // Tampilkan form sesuai role
    if (role === 'admin') {
        // Admin form elements
        showElement('usernameGroup');
        showElement('passwordGroup');
        hideElement('namaGroup');
        hideElement('nisnGroup');
        hideElement('kelasGroup');
        hideElement('passwordSiswaGroup');
        
        // Set required attributes
        setRequired('username', true);
        setRequired('password', true);
        setRequired('nama', false);
        setRequired('nisn', false);
        setRequired('kelas', false);
        setRequired('passwordSiswa', false);
    } else if (role === 'siswa') {
        // Siswa form elements
        hideElement('usernameGroup');
        
        // Check if we're on register page
        if (document.getElementById('namaGroup')) {
            showElement('namaGroup');
            showElement('nisnGroup');
            showElement('kelasGroup');
            showElement('passwordSiswaGroup');
            hideElement('passwordGroup');
            
            setRequired('username', false);
            setRequired('password', false);
            setRequired('nama', true);
            setRequired('nisn', true);
            setRequired('kelas', true);
            setRequired('passwordSiswa', true);
        } else {
            // Login page - show NISN and password
            showElement('nisnGroup');
            showElement('passwordGroup');
            
            setRequired('username', false);
            setRequired('nisn', true);
            setRequired('password', true);
        }
    }
}

// Helper functions
function showElement(id) {
    const element = document.getElementById(id);
    if (element) {
        element.style.display = 'block';
        element.classList.remove('hidden');
    }
}

function hideElement(id) {
    const element = document.getElementById(id);
    if (element) {
        element.style.display = 'none';
        element.classList.add('hidden');
    }
}

function setRequired(fieldName, required) {
    const field = document.getElementById(fieldName);
    if (field) {
        field.required = required;
    }
}

// Admin Dashboard Functions
function editAspirasi(id, status, feedback) {
    document.getElementById('edit_id_aspirasi').value = id;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_feedback').value = feedback;
    document.getElementById('editModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function submitQuickAction(action, id) {
    const form = document.getElementById('quickActionForm');
    if (!form) return;
    const actionInput = document.getElementById('quick_action');
    const idInput = document.getElementById('quick_id_aspirasi');
    const statusInput = document.getElementById('quick_status');
    const feedbackInput = document.getElementById('quick_feedback');
    actionInput.value = action;
    idInput.value = id;
    statusInput.value = action === 'finish' ? 'Selesai' : '';
    feedbackInput.value = '';
    form.submit();
}

function markAspirasiSelesai(id) {
    if (confirm('Tandai aspirasi ini sebagai selesai?')) {
        submitQuickAction('finish', id);
    }
}

function deleteAspirasi(id) {
    if (confirm('Hapus aspirasi ini? Tindakan ini tidak bisa dibatalkan.')) {
        submitQuickAction('delete', id);
    }
}

// Form Aspirasi Validation
function validateAspirasiForm() {
    const kategori = document.getElementById('kategori').value;
    const lokasi = document.getElementById('lokasi').value.trim();
    const keterangan = document.getElementById('keterangan').value.trim();
    
    if (!kategori) {
        alert('Pilih kategori pengaduan!');
        return false;
    }
    
    if (!lokasi) {
        alert('Lokasi sarana harus diisi!');
        return false;
    }
    
    if (!keterangan) {
        alert('Keterangan aspirasi harus diisi!');
        return false;
    }
    
    if (keterangan.length < 10) {
        alert('Keterangan aspirasi minimal 10 karakter!');
        return false;
    }
    
    return true;
}

// Register Form Validation
function validateRegisterForm() {
    const role = document.getElementById('selectedRole').value;
    
    if (!role) {
        alert('Pilih role terlebih dahulu!');
        return false;
    }
    
    if (role === 'siswa') {
        const password = document.getElementById('passwordSiswa').value;
        if (password.length < 6) {
            alert('Password minimal 6 karakter!');
            return false;
        }
    }
    
    return true;
}

// Auto resize textarea
function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

// Document Ready Functions
document.addEventListener('DOMContentLoaded', function() {
    // Set role data attribute for table creation
    if (document.querySelector('.dashboard-body')) {
        const userInfo = document.querySelector('.user-info span');
        if (userInfo && userInfo.textContent.includes('Selamat datang')) {
            document.body.dataset.role = 'admin';
        } else {
            document.body.dataset.role = 'siswa';
        }
        
        // Start auto-refresh for dashboard pages (5 detik)
        startAutoRefresh();
        
        // Check notifications every 10 seconds
        checkNotifications();
        setInterval(checkNotifications, 10000);
        
        // Initialize last data count
        const tableRows = document.querySelectorAll('table tbody tr');
        lastDataCount = tableRows.length;
    }
    
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const status = document.getElementById('export-status');
            if (status) {
                status.textContent = 'Menyiapkan ekspor...';
                setTimeout(() => {
                    status.textContent = 'Ekspor dimulai. Periksa unduhan Anda.';
                }, 800);
            }
        });
    }
    
    // Form aspirasi validation
    const aspirasiForm = document.getElementById('aspirasiForm');
    if (aspirasiForm) {
        aspirasiForm.addEventListener('submit', function(e) {
            if (!validateAspirasiForm()) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto resize textarea
        const keteranganField = document.getElementById('keterangan');
        if (keteranganField) {
            keteranganField.addEventListener('input', function() {
                autoResizeTextarea(this);
            });
        }
    }
    
    // Register form validation
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            if (!validateRegisterForm()) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Modal close on outside click
    const modal = document.getElementById('editModal');
    if (modal) {
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    }
    
    // Auto hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});

// Responsive table scroll indicator
function addTableScrollIndicator() {
    const tableContainers = document.querySelectorAll('.table-container');
    
    tableContainers.forEach(function(container) {
        const table = container.querySelector('table');
        if (table && table.scrollWidth > container.clientWidth) {
            container.classList.add('scrollable');
            
            // Add scroll indicator
            const indicator = document.createElement('div');
            indicator.className = 'scroll-indicator';
            indicator.innerHTML = '← Geser untuk melihat lebih banyak →';
            container.appendChild(indicator);
        }
    });
}

// Call on window resize
window.addEventListener('resize', addTableScrollIndicator);

// Utility function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

// Utility function to truncate text
function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text || '';
    return text.substr(0, maxLength) + '...';
}

// Print function for reports
function printTable() {
    window.print();
}

// Export table to CSV (basic implementation)
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // Exclude action column
            let text = cols[j].innerText;
            text = text.replace(/"/g, '""'); // Escape quotes
            row.push('"' + text + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
});
