<?php
/**
 * Kronos Default Theme — functions.php
 *
 * Registers routes for static theme pages (About, Services, Contact)
 * and adds navigation links to the header and footer.
 *
 * Loaded automatically by KronosThemeManager::boot().
 * $app is available as an injected variable.
 */
declare(strict_types=1);

// ── Nav links ────────────────────────────────────────────────────
add_action('kronos/theme/nav', function () {
    echo kronos_render_menu('header', [
        '/'         => 'Home',
        '/about'    => 'About',
        '/services' => 'Services',
        '/contact'  => 'Contact',
    ]);
});

add_action('kronos/theme/footer-nav', function () {
    echo kronos_render_menu('footer', [
        '/'         => 'Home',
        '/about'    => 'About',
        '/services' => 'Services',
        '/contact'  => 'Contact',
        '/dashboard/' => 'Admin',
    ]);
});

// ── Route registration ────────────────────────────────────────────
// Routes are registered directly here because functions.php is loaded
// after modules have booted (do_action('kronos/core/init') has already fired).
$router   = $app->router();
$themeDir = $app->rootDir() . '/themes/kronos-default/templates';

$staticPages = ['about', 'services', 'contact'];

foreach ($staticPages as $pageSlug) {
    $tpl = $themeDir . '/' . $pageSlug . '.php';
    $router->get('/' . $pageSlug, function (array $params) use ($tpl): void {
        if (!file_exists($tpl)) {
            kronos_abort(404);
        }
        require $tpl;
    });
}

// Contact form POST handler
$router->post('/contact', function (array $params) use ($app, $themeDir): void {
    $name    = trim((string) ($_POST['name']    ?? ''));
    $email   = trim((string) ($_POST['email']   ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    if (!$name || !$email || !$message || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = urlencode('Please fill in all required fields with a valid email.');
        header('Location: ' . $base . '/contact?error=' . $err);
        exit;
    }

    // Store submission in DB
    try {
        $app->db()->insert('kronos_posts', [
            'title'      => 'Contact: ' . (!empty($subject) ? $subject : 'Message from ' . $name),
            'slug'       => 'contact-' . time() . '-' . rand(1000, 9999),
            'post_type'  => 'contact_submission',
            'status'     => 'published',
            'content'    => json_encode(['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message]),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        header('Location: ' . $base . '/contact?sent=1');
    } catch (\Throwable) {
        $err = urlencode('Could not send your message. Please try again.');
        header('Location: ' . $base . '/contact?error=' . $err);
    }
    exit;
});
