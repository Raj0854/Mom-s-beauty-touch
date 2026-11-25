<?php
declare(strict_types=1);

/**
 * Moms Beauty Touch — Admin Portal Footer Template
 * PHP 8.3+ | XAMPP Compatible
 */

// Deny direct HTTP access
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}
?>
    </div><!-- /.admin-content -->

    <footer class="admin-footer">
      <div>&copy; <?= date('Y') ?> Moms Beauty Touch. All rights reserved.</div>
      <div>PHP <?= PHP_VERSION ?> &bull; MySQL / MariaDB &bull; Admin Portal</div>
    </footer>
  </main><!-- /.admin-main -->
</div><!-- /.admin-wrapper -->

<!-- Admin Interaction Script -->
<script src="assets/js/admin.js"></script>
</body>
</html>

