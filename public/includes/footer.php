</main>
<footer class="site-footer">
  <div class="site-footer-inner">
    <div class="brand-block">
      <div class="brand"><i class="fas fa-pepper-hot"></i> Sambal House</div>
      <p>Handcrafted Malaysian chili pastes made fresh in small batches. Bringing the heat from our kitchen to your table since day one.</p>
      <div class="socials">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
        <a href="#" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
      </div>
    </div>
    <div>
      <h4>Shop</h4>
      <ul>
        <li><a href="<?=BASE_URL?>/index.php">All Sambal</a></li>
        <li><a href="<?=BASE_URL?>/cart.php">Your Cart</a></li>
        <?php if(is_logged_in()): ?><li><a href="<?=BASE_URL?>/orders.php">My Orders</a></li><?php endif; ?>
      </ul>
    </div>
    <div>
      <h4>Company</h4>
      <ul>
        <li><a href="<?=BASE_URL?>/team.php">Our Team</a></li>
        <li><a href="#">Our Story</a></li>
        <li><a href="#">Wholesale</a></li>
      </ul>
    </div>
    <div>
      <h4>Help</h4>
      <ul>
        <li><a href="#">Shipping</a></li>
        <li><a href="#">Returns</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </div>
  </div>
  <div class="copy">&copy; <?=date('Y')?> Sambal House &nbsp;·&nbsp; Made with <i class="fas fa-fire" style="color:var(--accent)"></i> in Malaysia</div>
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
