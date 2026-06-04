<?php
require_once '../config/database.php';
session_start();
?>
<!DOCTYPE html>
<html lang="id" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tim Pengembang — BIB</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        .about-hero {
            text-align: center;
            padding: 4rem 0 3rem;
            position: relative;
        }

        .about-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(0,200,150,0.06) 1px, transparent 0);
            background-size: 28px 28px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 80%);
            pointer-events: none;
        }

        .about-hero-content { position: relative; z-index: 1; }

        .about-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 1rem;
            border: 1px solid rgba(0,229,255,0.3);
            background: rgba(0,229,255,0.08);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent-2);
            margin-bottom: 1.25rem;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-top: 3rem;
            margin-bottom: 4rem;
        }

        .member-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2rem 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .member-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .member-card:hover {
            border-color: rgba(0,200,150,0.35);
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }

        .member-card:hover::before { opacity: 1; }

        .member-photo {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            margin: 0 auto 1.25rem;
            border: 3px solid var(--accent);
            overflow: hidden;
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px rgba(0,200,150,0.2);
            position: relative;
        }

        .member-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-photo-placeholder {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent);
        }

        .member-name {
            font-family: var(--font-display);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.4rem;
            line-height: 1.3;
        }

        .member-nim {
            font-size: 0.8rem;
            color: var(--accent-2);
            font-weight: 600;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }

        .member-role {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .role-dev {
            background: rgba(0,200,150,0.1);
            color: var(--accent);
            border: 1px solid rgba(0,200,150,0.25);
        }

        .role-design {
            background: rgba(168,85,247,0.1);
            color: #a855f7;
            border: 1px solid rgba(168,85,247,0.25);
        }

        .role-lead {
            background: rgba(0,229,255,0.1);
            color: var(--accent-2);
            border: 1px solid rgba(0,229,255,0.25);
        }

        /* Project info section */
        .project-info {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            margin-bottom: 4rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .project-info-item .label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            margin-bottom: 0.4rem;
        }

        .project-info-item .value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text);
        }

        @media (max-width: 768px) {
            .team-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            .project-info { grid-template-columns: 1fr; gap: 1.25rem; }
            .member-photo { width: 90px; height: 90px; }
            .member-photo-placeholder { font-size: 2rem; }
        }

        @media (max-width: 480px) {
            .team-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body class="page-fade">
<?php include 'components/navbar.php'; ?>

<div class="container">

    <!-- Hero -->
    <div class="about-hero">
        <div class="about-hero-content">
            <div class="about-chip">Kelompok Pengembang</div>
            <h1 style="font-family:var(--font-display);font-size:clamp(2rem,5vw,3.5rem);color:var(--text);margin-bottom:1rem;">
                Tim di Balik <span style="background:linear-gradient(90deg,var(--accent),var(--accent-2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">BIB</span>
            </h1>
            <p style="color:var(--text-muted);max-width:520px;margin:0 auto;font-size:1rem;line-height:1.7;">
                Platform manajemen turnamen ini dikembangkan sebagai proyek mata kuliah
                oleh mahasiswa Universitas Sam Ratulangi.
            </p>
        </div>
    </div>

    <!-- Team Cards -->
    <div class="team-grid">

        <!-- Randi -->
        <div class="member-card">
            <div class="member-photo">
                <?php if (file_exists('../assets/img/randi.jpg') || file_exists('../assets/img/randi.png')): ?>
                    <img src="../assets/img/randi.jpg" alt="Randi Franco Taroreh"
                         onerror="this.parentElement.innerHTML='<div class=\'member-photo-placeholder\'>RF</div>'">
                <?php else: ?>
                    <div class="member-photo-placeholder">RF</div>
                <?php endif; ?>
            </div>
            <div class="member-name">Randi Franco Taroreh</div>
            <div class="member-nim">240211060077</div>
            <span class="member-role role-dev">⚙️ Backend & Frontend</span>
        </div>

        <!-- Glenn -->
        <div class="member-card">
            <div class="member-photo">
                <?php if (file_exists('../assets/img/glenn.jpg') || file_exists('../assets/img/glenn.png')): ?>
                    <img src="../assets/img/glenn.jpg" alt="Glenn Norton Sumbaluwu"
                         onerror="this.parentElement.innerHTML='<div class=\'member-photo-placeholder\'>GN</div>'">
                <?php else: ?>
                    <div class="member-photo-placeholder">GN</div>
                <?php endif; ?>
            </div>
            <div class="member-name">Glenn Norton Sumbaluwu</div>
            <div class="member-nim">240211060067</div>
            <span class="member-role role-dev">⚙️ Backend & Frontend</span>
        </div>

        <!-- Raja -->
        <div class="member-card">
            <div class="member-photo">
                <?php if (file_exists('../assets/img/raja.jpg') || file_exists('../assets/img/raja.png')): ?>
                    <img src="../assets/img/raja.jpg" alt="Raja Timothi Brilliant Kiroyan"
                         onerror="this.parentElement.innerHTML='<div class=\'member-photo-placeholder\'>RT</div>'">
                <?php else: ?>
                    <div class="member-photo-placeholder">RT</div>
                <?php endif; ?>
            </div>
            <div class="member-name">Raja Timothi Brilliant Kiroyan</div>
            <div class="member-nim">240211060054</div>
            <span class="member-role role-lead">🎨 UI/UX Designer</span>
        </div>

        <!-- Danielle -->
        <div class="member-card">
            <div class="member-photo">
                <?php if (file_exists('../assets/img/danielle.jpg') || file_exists('../assets/img/danielle.png')): ?>
                    <img src="../assets/img/danielle.jpg" alt="Danielle Godwin Kawulusan"
                         onerror="this.parentElement.innerHTML='<div class=\'member-photo-placeholder\'>DG</div>'">
                <?php else: ?>
                    <div class="member-photo-placeholder">DG</div>
                <?php endif; ?>
            </div>
            <div class="member-name">Danielle Godwin Kawulusan</div>
            <div class="member-nim">240211060065</div>
            <span class="member-role role-design">✏️ UI/UX Designer</span>
        </div>

    </div>

    <!-- Info Project -->
    <div class="section-title mb-2"><h2>Tentang Proyek</h2></div>
    <div class="project-info">
        <div class="project-info-item">
            <div class="label">Nama Platform</div>
            <div class="value">BIB — Best In Battle</div>
        </div>
        <div class="project-info-item">
            <div class="label">Mata Kuliah</div>
            <div class="value">Pengantar Pemrograman Web</div>
        </div>
        <div class="project-info-item">
            <div class="label">Institusi</div>
            <div class="value">Universitas Sam Ratulangi</div>
        </div>
        <div class="project-info-item">
            <div class="label">Teknologi</div>
            <div class="value">PHP Native, MySQL, HTML, CSS, JavaScript</div>
        </div>
        <div class="project-info-item">
            <div class="label">Tahun</div>
            <div class="value">2026</div>
        </div>
        <div class="project-info-item">
            <div class="label">Deskripsi</div>
            <div class="value">Platform manajemen turnamen generik — olahraga, esports, akademik, dan lainnya.</div>
        </div>
    </div>

</div>

<?php include 'components/footer.php'; ?>
<script src="../assets/js/global.js"></script>
</body>
</html>