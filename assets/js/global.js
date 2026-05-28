// assets/js/global.js
// Fungsi umum: dark/light mode toggle, hamburger, notif dropdown

document.addEventListener('DOMContentLoaded', function () {

    // ==========================================
    // DARK / LIGHT MODE TOGGLE
    // ==========================================
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon   = document.getElementById('themeIcon');
    const root        = document.documentElement;

    const savedTheme = localStorage.getItem('bib_theme') || 'dark';
    applyTheme(savedTheme);

    function applyTheme(theme) {
        if (theme === 'light') {
            root.setAttribute('data-theme', 'light');
            if (themeIcon) themeIcon.textContent = '☀️';
        } else {
            root.removeAttribute('data-theme');
            if (themeIcon) themeIcon.textContent = '🌙';
        }
        localStorage.setItem('bib_theme', theme);
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const current = localStorage.getItem('bib_theme') || 'dark';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    // ==========================================
    // HAMBURGER MOBILE NAV
    // ==========================================
    const hamburger = document.getElementById('hamburger');
    const mobileNav = document.getElementById('mobileNav');

    if (hamburger && mobileNav) {
        hamburger.addEventListener('click', function () {
            const isOpen = mobileNav.classList.toggle('open');
            hamburger.setAttribute('aria-expanded', isOpen);
            hamburger.textContent = isOpen ? '✕' : '☰';
        });

        // Tutup kalau klik di luar
        document.addEventListener('click', function (e) {
            if (!hamburger.contains(e.target) && !mobileNav.contains(e.target)) {
                mobileNav.classList.remove('open');
                hamburger.setAttribute('aria-expanded', 'false');
                hamburger.textContent = '☰';
            }
        });
    }

    // ==========================================
    // NOTIFIKASI DROPDOWN
    // ==========================================
    const notifBtn      = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = notifDropdown.classList.toggle('open');
            notifBtn.setAttribute('aria-expanded', isOpen);
        });

        document.addEventListener('click', function (e) {
            if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                notifDropdown.classList.remove('open');
                notifBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ==========================================
    // AUTO HIDE ALERTS setelah 5 detik
    // ==========================================
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity    = '0';
            alert.style.transform  = 'translateY(-8px)';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });

    // ==========================================
    // KONFIRMASI HAPUS / AKSI DESTRUKTIF
    // ==========================================
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            const msg = el.getAttribute('data-confirm') || 'Yakin ingin melanjutkan?';
            if (!confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

});
