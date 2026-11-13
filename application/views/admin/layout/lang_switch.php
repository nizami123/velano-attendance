<?php /* Tombol ganti bahasa ID | EN (cookie `lang`, lihat controllers/Lang.php) */ $__cur = app_lang(); ?>
<span class="at-lang" title="Language / Bahasa">
    <i class="bi bi-translate"></i>
    <a href="<?= site_url('lang/id') ?>" class="<?= $__cur === 'id' ? 'active' : '' ?>">ID</a>
    <a href="<?= site_url('lang/en') ?>" class="<?= $__cur === 'en' ? 'active' : '' ?>">EN</a>
</span>
