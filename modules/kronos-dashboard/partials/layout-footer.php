
  </main>
</div><!-- .main-content -->

<script src="<?= kronos_asset('js/dashboard.js') ?>"></script>
<script>
  // Pass PHP data to JS
  window.KronosConfig = {
    appUrl:  <?= json_encode(kronos_option('app_url', '/')) ?>,
    apiBase: <?= json_encode(kronos_option('app_url', '/') . '/api/kronos/v1') ?>,
    mode:    <?= json_encode(kronos_mode()) ?>,
    user:    <?= json_encode(kronos_current_user()) ?>,
    csrf:    <?= json_encode(kronos_csrf_token()) ?>,
  };
</script>
</body>
</html>
