<?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
  <a href="/profile.php" class="block md:inline-block hover:text-green-200">Edit Profil</a>
<?php endif; ?> 