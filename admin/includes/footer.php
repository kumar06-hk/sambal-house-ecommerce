<footer class="site-footer" style="margin-top:60px;padding:30px 28px 18px;">
  <div class="copy" style="margin-top:0;border:none;padding:0;">
    Sambal House Admin &nbsp;·&nbsp; &copy; <?=date('Y')?> &nbsp;·&nbsp; <i class="fas fa-fire" style="color:var(--accent)"></i> Spice up the dashboard
  </div>
</footer>
<?php if($f = consume_flash()): ?>
<script>
Swal.fire({
  icon: <?= json_encode($f['type']) ?>,
  title: <?= json_encode($f['title']) ?>,
  text: <?= json_encode($f['text']) ?>,
  <?php if(($f['type'] ?? '') === 'success'): ?>timer: 1500, showConfirmButton: false<?php endif; ?>
});
</script>
<?php endif; ?>
</body>
</html>
