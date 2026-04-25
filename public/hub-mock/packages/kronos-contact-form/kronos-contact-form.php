<?php
declare(strict_types=1);

use Kronos\Auth\KronosJWT;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosModule;

class KronosContactFormModule extends KronosModule
{
    public function getName(): string
    {
        return 'kronos-contact-form';
    }

    public function boot(): void
    {
        $this->ensureTables();
        $this->ensureDefaultForm();

        add_filter('kronos/contact_form/enabled', fn(): bool => true);
        add_action('kronos/dashboard/nav/content', function (string $currentUri): void {
            $active = str_starts_with($currentUri, '/dashboard/forms') ? 'active' : '';
            echo '<a href="' . kronos_url('/dashboard/forms') . '" class="nav-item ' . $active . '"><span class="nav-icon">▥</span> Forms</a>';
        });

        $router = KronosApp::getInstance()->router();
        $auth = fn(array $params, callable $next) => $this->requireDashboardAuth($params, $next);
        $router->get('/dashboard/forms', [$this, 'dashboard'], [$auth]);
        $router->post('/dashboard/forms', [$this, 'dashboard'], [$auth]);
        $router->get('/forms/{slug}', [$this, 'publicForm']);
        $router->post('/forms/{slug}', [$this, 'submitPublicForm']);
    }

    public function dashboard(array $params = []): void
    {
        $db = KronosApp::getInstance()->db();
        $notice = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            kronos_verify_csrf();
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'save_form') {
                $title = trim((string) ($_POST['title'] ?? 'Contact Form'));
                $slug = kronos_sanitize_slug((string) ($_POST['slug'] ?? $title));
                $recipient = $this->normalizeRecipient((string) ($_POST['recipient_email'] ?? ''));
                $fields = $this->parseFields((string) ($_POST['fields'] ?? ''));
                $id = (int) ($_POST['form_id'] ?? 0);
                $data = [
                    'title' => $title ?: 'Contact Form',
                    'slug' => $slug ?: 'contact-form',
                    'recipient_email' => $recipient,
                    'fields_json' => json_encode($fields, JSON_UNESCAPED_SLASHES),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                if ($id > 0) {
                    $db->update('kronos_contact_forms', $data, ['id' => $id]);
                } else {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $db->insert('kronos_contact_forms', $data);
                }
                $notice = 'Form saved.';
            }
        }

        $forms = $db->getResults('SELECT * FROM kronos_contact_forms ORDER BY updated_at DESC, id DESC');
        $selectedId = (int) ($_GET['id'] ?? ($forms[0]['id'] ?? 0));
        $selected = $selectedId > 0
            ? $db->getRow('SELECT * FROM kronos_contact_forms WHERE id = ? LIMIT 1', [$selectedId])
            : null;
        $submissions = $selected
            ? $db->getResults('SELECT * FROM kronos_contact_submissions WHERE form_id = ? ORDER BY created_at DESC LIMIT 50', [(int) $selected['id']])
            : [];

        $pageTitle = 'Forms';
        $dashDir = KronosApp::getInstance()->rootDir() . '/modules/kronos-dashboard';
        require $dashDir . '/partials/layout-header.php';
        ?>
        <?php if ($notice): ?><div class="alert alert-success"><?= kronos_e($notice) ?></div><?php endif; ?>
        <div class="wp-list-header">
          <div>
            <h2>Forms</h2>
            <div class="wp-view-links">
              <?php foreach ($forms as $form): ?>
              <a class="<?= (int) $form['id'] === $selectedId ? 'current' : '' ?>" href="<?= kronos_url('/dashboard/forms?id=' . (int) $form['id']) ?>"><?= kronos_e($form['title']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <a class="btn btn-primary" href="<?= kronos_url('/forms/' . ($selected['slug'] ?? 'contact-form')) ?>" target="_blank">View Form</a>
        </div>

        <div class="content-editor-layout">
          <form class="editor-main" method="post" action="<?= kronos_url('/dashboard/forms' . ($selected ? '?id=' . (int) $selected['id'] : '')) ?>">
            <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
            <input type="hidden" name="action" value="save_form">
            <input type="hidden" name="form_id" value="<?= (int) ($selected['id'] ?? 0) ?>">
            <div class="card">
              <h3>Form Builder</h3>
              <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" value="<?= kronos_e($selected['title'] ?? 'Contact Form') ?>">
              </div>
              <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" value="<?= kronos_e($selected['slug'] ?? 'contact-form') ?>">
              </div>
              <div class="form-group">
                <label>Notification Email</label>
                <input type="email" name="recipient_email" value="<?= kronos_e($selected['recipient_email'] ?? $this->defaultRecipient()) ?>">
              </div>
              <div class="form-group">
                <label>Fields</label>
                <textarea name="fields" rows="9"><?= kronos_e($this->fieldsToText($selected['fields_json'] ?? '')) ?></textarea>
                <small>One field per line: label | type | required. Types: text, email, textarea.</small>
              </div>
              <button class="btn btn-primary" type="submit">Save Form</button>
            </div>
          </form>

          <aside class="editor-sidebar">
            <div class="card">
              <h3>Embed</h3>
              <p><code>[kronos_form slug="<?= kronos_e($selected['slug'] ?? 'contact-form') ?>"]</code></p>
              <p><a href="<?= kronos_url('/forms/' . ($selected['slug'] ?? 'contact-form')) ?>" target="_blank">Open public form</a></p>
            </div>
            <div class="card">
              <h3>Submissions</h3>
              <?php if (!$submissions): ?>
              <p class="text-muted">No submissions yet.</p>
              <?php else: ?>
              <?php foreach ($submissions as $submission): ?>
              <?php $payload = json_decode((string) $submission['payload_json'], true) ?: []; ?>
              <div class="activity-item">
                <strong><?= kronos_e($payload['email'] ?? $payload['name'] ?? 'Submission') ?></strong>
                <small><?= kronos_e($submission['created_at']) ?></small>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </aside>
        </div>
        <?php
        require $dashDir . '/partials/layout-footer.php';
    }

    public function publicForm(array $params): void
    {
        $form = $this->getFormBySlug((string) ($params['slug'] ?? ''));
        if (!$form) {
            kronos_abort(404);
        }
        $this->renderPublicForm($form);
    }

    public function submitPublicForm(array $params): void
    {
        $form = $this->getFormBySlug((string) ($params['slug'] ?? ''));
        if (!$form) {
            kronos_abort(404);
        }

        $fields = json_decode((string) $form['fields_json'], true) ?: [];
        $payload = [];
        foreach ($fields as $field) {
            $key = kronos_sanitize_slug((string) ($field['label'] ?? 'field'));
            $value = trim((string) ($_POST[$key] ?? ''));
            if (!empty($field['required']) && $value === '') {
                kronos_redirect('/forms/' . $form['slug'] . '?error=required');
            }
            $payload[$key] = $value;
        }

        $db = KronosApp::getInstance()->db();
        $db->insert('kronos_contact_submissions', [
            'form_id' => (int) $form['id'],
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->sendNotification($form, $payload);

        kronos_redirect('/forms/' . $form['slug'] . '?sent=1');
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, string> $payload
     */
    private function sendNotification(array $form, array $payload): bool
    {
        $recipient = $this->normalizeRecipient((string) ($form['recipient_email'] ?? ''));

        $lines = ['New submission from ' . (string) ($form['title'] ?? 'Contact Form'), ''];
        foreach ($payload as $key => $value) {
            $lines[] = ucwords(str_replace('-', ' ', $key)) . ': ' . $value;
        }

        return kronos_mail(
            $recipient,
            'New form submission: ' . (string) ($form['title'] ?? 'Contact Form'),
            implode("\n", $lines),
            ['Content-Type: text/plain; charset=UTF-8']
        );
    }

    private function renderPublicForm(array $form): void
    {
        $fields = json_decode((string) $form['fields_json'], true) ?: [];
        $title = (string) $form['title'];
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= kronos_e($title) ?> - <?= kronos_e(kronos_option('app_name', 'KronosCMS')) ?></title>
<link rel="stylesheet" href="<?= kronos_asset('css/theme.css') ?>">
</head>
<body>
<main style="max-width:720px;margin:48px auto;padding:0 20px">
  <h1><?= kronos_e($title) ?></h1>
  <?php if (isset($_GET['sent'])): ?><div class="alert alert-success">Message sent.</div><?php endif; ?>
  <?php if (isset($_GET['error'])): ?><div class="alert alert-danger">Please fill in all required fields.</div><?php endif; ?>
  <form method="post" action="<?= kronos_url('/forms/' . $form['slug']) ?>" class="contact-form">
    <?php foreach ($fields as $field): ?>
    <?php $label = (string) ($field['label'] ?? 'Field'); $key = kronos_sanitize_slug($label); ?>
    <p>
      <label><?= kronos_e($label) ?><?= !empty($field['required']) ? ' *' : '' ?></label><br>
      <?php if (($field['type'] ?? 'text') === 'textarea'): ?>
      <textarea name="<?= kronos_e($key) ?>" rows="5" <?= !empty($field['required']) ? 'required' : '' ?>></textarea>
      <?php else: ?>
      <input type="<?= kronos_e($field['type'] ?? 'text') ?>" name="<?= kronos_e($key) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
      <?php endif; ?>
    </p>
    <?php endforeach; ?>
    <button type="submit">Send Message</button>
  </form>
</main>
</body>
</html><?php
    }

    private function requireDashboardAuth(array $params, callable $next): void
    {
        if (empty($_COOKIE['kronos_token'])) {
            kronos_redirect('/dashboard/login');
        }
        try {
            $app = KronosApp::getInstance();
            $jwt = new KronosJWT((string) $app->env('JWT_SECRET', ''), (int) $app->env('JWT_EXPIRY', 86400));
            $payload = $jwt->decode((string) $_COOKIE['kronos_token']);
            $_REQUEST['_kronos_user'] = $payload['data'] ?? [];
        } catch (Throwable) {
            kronos_redirect('/dashboard/login');
        }
        $next();
    }

    private function ensureTables(): void
    {
        KronosApp::getInstance()->db()->runSchema([
            "CREATE TABLE IF NOT EXISTS kronos_contact_forms (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL UNIQUE,
                recipient_email VARCHAR(191) NULL,
                fields_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS kronos_contact_submissions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                form_id INT UNSIGNED NOT NULL,
                payload_json LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX form_id (form_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ]);
    }

    private function ensureDefaultForm(): void
    {
        $db = KronosApp::getInstance()->db();
        if ($db->getVar("SELECT COUNT(*) FROM kronos_contact_forms WHERE slug = 'contact-form'")) {
            $db->query(
                "UPDATE kronos_contact_forms SET recipient_email = ? WHERE slug = 'contact-form' AND (recipient_email IS NULL OR recipient_email = '')",
                [$this->defaultRecipient()]
            );
            return;
        }
        $db->insert('kronos_contact_forms', [
            'title' => 'Contact Form',
            'slug' => 'contact-form',
            'recipient_email' => $this->defaultRecipient(),
            'fields_json' => json_encode($this->defaultFields(), JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function normalizeRecipient(string $recipient): string
    {
        $recipient = trim($recipient);
        return $recipient !== '' ? $recipient : $this->defaultRecipient();
    }

    private function defaultRecipient(): string
    {
        $adminEmail = trim((string) kronos_option('admin_email', ''));
        return $adminEmail !== '' ? $adminEmail : 'admin@localhost.test';
    }

    private function getFormBySlug(string $slug): ?array
    {
        return KronosApp::getInstance()->db()->getRow(
            'SELECT * FROM kronos_contact_forms WHERE slug = ? LIMIT 1',
            [kronos_sanitize_slug($slug)]
        );
    }

    private function parseFields(string $raw): array
    {
        $fields = [];
        foreach (preg_split('/\R/', trim($raw)) ?: [] as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (($parts[0] ?? '') === '') {
                continue;
            }
            $type = in_array($parts[1] ?? 'text', ['text', 'email', 'textarea'], true) ? $parts[1] : 'text';
            $fields[] = [
                'label' => $parts[0],
                'type' => $type,
                'required' => in_array(strtolower($parts[2] ?? ''), ['1', 'yes', 'true', 'required'], true),
            ];
        }
        return $fields ?: $this->defaultFields();
    }

    private function fieldsToText(string $json): string
    {
        $fields = json_decode($json, true) ?: $this->defaultFields();
        return implode("\n", array_map(
            fn(array $field): string => ($field['label'] ?? 'Field') . ' | ' . ($field['type'] ?? 'text') . ' | ' . (!empty($field['required']) ? 'required' : ''),
            $fields
        ));
    }

    private function defaultFields(): array
    {
        return [
            ['label' => 'Name', 'type' => 'text', 'required' => true],
            ['label' => 'Email', 'type' => 'email', 'required' => true],
            ['label' => 'Message', 'type' => 'textarea', 'required' => true],
        ];
    }
}
