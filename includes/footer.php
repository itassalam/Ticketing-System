<?php // includes/footer.php ?>

<footer class="app-footer">
  <div>&copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong> &mdash; Sistem Helpdesk RS Assalam</div>
  <div class="af-r">
    <span>Open Source &amp; Free Forever</span>
    <span class="af-ver">v1.0.0</span>
  </div>
</footer>

</div><!-- /.main -->

<script>
// Sidebar, dropdown user, alert auto-dismiss, dan salin teks
// sudah ditangani di header.php — jangan didefinisikan ulang di sini.

// Tab switching
function switchTab(btn, tabId) {
  const container = btn.closest('.panel') || btn.parentElement.closest('div');
  container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  container.querySelectorAll('.tab-c').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  const target = document.getElementById(tabId);
  if (target) target.classList.add('active');
}

// Modal helpers — mendukung modal berbasis class .show (halaman lain)
// sekaligus modal berbasis style.display (modal "Tentang" di header)
function openModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.add('show');
  el.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('show');
  el.style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('click', function(e) {
  if (e.target.classList && e.target.classList.contains('modal-ov')) {
    e.target.classList.remove('show');
    e.target.style.display = 'none';
    document.body.style.overflow = '';
  }
});
</script>
</body>
</html>