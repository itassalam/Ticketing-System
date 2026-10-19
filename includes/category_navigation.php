<style>
.category-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
    padding: 8px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
}
.category-nav a {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: 7px;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: background .15s, color .15s;
}
.category-nav a:hover { background: #f1f5f9; color: #1e293b; }
.category-nav a.active { background: #eff6ff; color: #1d4ed8; }
.category-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 16px;
}
.category-stat-card {
    min-width: 0;
    min-height: 78px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
}
.category-stat-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
}
.category-stat-value {
    font-size: 22px;
    font-weight: 800;
    color: #111827;
    line-height: 1;
}
.category-stat-label {
    margin-top: 3px;
    color: #9ca3af;
    font-size: 11px;
}
.category-table-tools {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 14px 16px;
    border-bottom: 1px solid #f0f1f3;
    background: #fafbfc;
}
.category-table-search {
    position: relative;
    flex: 1 1 240px;
    min-width: 200px;
}
.category-table-search i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 12px;
}
.category-table-search input {
    width: 100%;
    padding-left: 34px !important;
}
.category-table-tools select.form-control {
    width: auto;
    min-width: 130px;
}
.category-table-tools .category-table-page-size {
    min-width: 96px !important;
}
.category-table-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 18px;
    border-top: 1px solid #f0f1f3;
    background: #fafbfc;
    color: #64748b;
    font-size: 12px;
}
.category-data-table {
    width: 100%;
    border-collapse: collapse;
}
.category-data-table thead th {
    padding: 11px 14px;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
    white-space: nowrap;
}
.category-data-table tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f3f5;
    vertical-align: middle;
}
.category-data-table tbody tr:last-child td { border-bottom: none; }
.category-data-table tbody tr[data-category-row]:hover { background: #fafcff; }
.category-table-pager {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
}
.category-table-pager button {
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e5e7eb;
    border-radius: 7px;
    background: #fff;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}
.category-table-pager button:hover:not(:disabled) { background: #f1f5f9; }
.category-table-pager button.active {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
}
.category-table-pager button:disabled {
    cursor: not-allowed;
    opacity: .4;
}
@media (max-width: 600px) {
    .category-table-search { flex-basis: 100%; }
    .category-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .category-stat-card { padding: 10px; gap: 8px; }
    .category-stat-icon { width: 32px; height: 32px; flex-basis: 32px; }
}
@media (max-width: 360px) {
    .category-stats { grid-template-columns: 1fr; }
}
</style>

<nav class="category-nav" aria-label="Menu kategori">
    <a href="<?= APP_URL ?>/pages/kategori.php" class="<?= $active_menu === 'kategori' ? 'active' : '' ?>">
        <i class="fa fa-desktop"></i> Kategori IT
    </a>
    <a href="<?= APP_URL ?>/pages/kategori_ipsrs.php" class="<?= $active_menu === 'kategori_ipsrs' ? 'active' : '' ?>">
        <i class="fa fa-screwdriver-wrench"></i> Kategori IPSRS
    </a>
    <a href="<?= APP_URL ?>/pages/kategori_atem.php" class="<?= $active_menu === 'kategori_atem' ? 'active' : '' ?>">
        <i class="fa fa-kit-medical"></i> Kategori ATEM
    </a>
</nav>
