<?php
declare(strict_types=1);

use Kronos\Auth\KronosJWT;
use Kronos\Core\KronosApp;
use Kronos\Core\KronosModule;

class KronosSmtpModule extends KronosModule
{
    public function getName(): string
    {
        return 'kronos-smtp';
    }

    public function boot(): void
    {
        $this->ensureTables();

        add_filter('kronos/mail/send', [$this, 'sendMail'], 10, 5);
        add_action('kronos/dashboard/nav/tools', function (string $currentUri): void {
            $active = str_starts_with($currentUri, '/dashboard/smtp') ? 'active' : '';
            echo '<a href="' . kronos_url('/dashboard/smtp') . '" class="nav-item ' . $active . '"><span class="nav-icon">@</span> SMTP</a>';
        });

        $router = KronosApp::getInstance()->router();
        $auth = fn(array $params, callable $next) => $this->requireDashboardAuth($params, $next);
        $router->get('/dashboard/smtp', [$this, 'dashboard'], [$auth]);
        $router->post('/dashboard/smtp', [$this, 'dashboard'], [$auth]);
    }

    public function sendMail(mixed $handled, string $to, string $subject, string $message, array $headers = []): ?bool
    {
        if ($handled !== null) {
            return is_bool($handled) ? $handled : null;
        }

        $mode = (string) kronos_option('smtp_mode', 'log');
        $success = false;
        $error = '';

        if ($mode === 'php_mail') {
            $success = @mail($to, $subject, $message, implode("\r\n", $headers));
            $error = $success ? '' : 'PHP mail returned false.';
        } elseif ($mode === 'smtp') {
            try {
                $success = $this->sendViaSmtp($to, $subject, $message, $headers);
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $success = false;
            }
        } else {
            $success = true;
            $error = 'Logged only.';
        }

        $this->logMail($to, $subject, $mode, $success, $error);
        return $success;
    }

    public function dashboard(array $params = []): void
    {
        $notice = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            kronos_verify_csrf();
            $action = (string) ($_POST['action'] ?? 'save');
            if ($action === 'test') {
                $to = trim((string) ($_POST['test_email'] ?? kronos_option('admin_email', '')));
                $ok = $to !== '' && kronos_mail($to, 'Kronos SMTP test', 'This is a KronosCMS SMTP test message.');
                $notice = $ok ? 'Test message processed.' : 'Test message failed. Check the mail log.';
            } else {
                foreach (['smtp_mode','smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_password','smtp_from_email','smtp_from_name'] as $key) {
                    kronos_set_option($key, trim((string) ($_POST[$key] ?? '')));
                }
                $notice = 'SMTP settings saved.';
            }
        }

        $logs = KronosApp::getInstance()->db()->getResults('SELECT * FROM kronos_smtp_mail_log ORDER BY created_at DESC LIMIT 25');
        $pageTitle = 'SMTP Mailer';
        $dashDir = KronosApp::getInstance()->rootDir() . '/modules/kronos-dashboard';
        require $dashDir . '/partials/layout-header.php';
        ?>
        <?php if ($notice): ?><div class="alert alert-success"><?= kronos_e($notice) ?></div><?php endif; ?>
        <div class="content-editor-layout">
          <form class="editor-main" method="post" action="<?= kronos_url('/dashboard/smtp') ?>">
            <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
            <input type="hidden" name="action" value="save">
            <div class="card plugin-settings-card smtp-card">
              <div class="plugin-hero smtp-hero">
                <span class="plugin-hero-icon">@</span>
                <div>
                  <p class="plugin-eyebrow">Mail delivery</p>
                  <h2>SMTP Mailer</h2>
                  <p>Control how KronosCMS sends site notifications, form submissions, and test messages.</p>
                </div>
              </div>
              <div class="form-grid two-col">
                <div class="form-group form-span-2"><label>Mode</label><select name="smtp_mode">
                  <?php foreach (['log' => 'Log only', 'php_mail' => 'PHP mail()', 'smtp' => 'SMTP server'] as $value => $label): ?>
                  <option value="<?= kronos_e($value) ?>" <?= kronos_option('smtp_mode', 'log') === $value ? 'selected' : '' ?>><?= kronos_e($label) ?></option>
                  <?php endforeach; ?>
                </select><small class="field-hint">Use log mode while testing, then switch to a real SMTP provider when the site goes live.</small></div>
                <div class="form-group"><label>SMTP Host</label><input name="smtp_host" autocomplete="off" placeholder="smtp.example.com" value="<?= kronos_e(kronos_option('smtp_host', '')) ?>"></div>
                <div class="form-group"><label>Port</label><input name="smtp_port" inputmode="numeric" autocomplete="off" value="<?= kronos_e(kronos_option('smtp_port', '587')) ?>"></div>
                <div class="form-group"><label>Encryption</label><select name="smtp_encryption">
                  <?php foreach (['none', 'tls', 'ssl'] as $value): ?>
                  <option value="<?= kronos_e($value) ?>" <?= kronos_option('smtp_encryption', 'tls') === $value ? 'selected' : '' ?>><?= kronos_e(strtoupper($value)) ?></option>
                  <?php endforeach; ?>
                </select></div>
                <div class="form-group"><label>Username</label><input name="smtp_username" autocomplete="username" value="<?= kronos_e(kronos_option('smtp_username', '')) ?>"></div>
                <div class="form-group form-span-2"><label>Password</label><input type="password" name="smtp_password" autocomplete="current-password" value="<?= kronos_e(kronos_option('smtp_password', '')) ?>"></div>
                <div class="form-group"><label>From Email</label><input type="email" name="smtp_from_email" autocomplete="email" value="<?= kronos_e(kronos_option('smtp_from_email', kronos_option('admin_email', ''))) ?>"></div>
                <div class="form-group"><label>From Name</label><input name="smtp_from_name" autocomplete="organization" value="<?= kronos_e(kronos_option('smtp_from_name', kronos_option('app_name', 'KronosCMS'))) ?>"></div>
              </div>
              <div class="settings-actions">
                <button class="btn btn-primary">Save SMTP Settings</button>
              </div>
            </div>
          </form>
          <aside class="editor-sidebar">
            <form class="card plugin-side-card" method="post" action="<?= kronos_url('/dashboard/smtp') ?>">
              <input type="hidden" name="_kronos_csrf" value="<?= kronos_csrf_token() ?>">
              <input type="hidden" name="action" value="test">
              <h3>Send Test</h3>
              <p class="text-muted">Send one message through the current mail mode and inspect the result below.</p>
              <div class="form-group"><label>Recipient</label><input type="email" name="test_email" value="<?= kronos_e(kronos_option('admin_email', '')) ?>"></div>
              <button class="btn btn-secondary mt-2">Send Test</button>
            </form>
            <div class="card plugin-side-card"><h3>Recent Mail Log</h3>
              <div class="smtp-log-list">
              <?php foreach ($logs as $log): ?>
              <div class="smtp-log-item">
                <strong><?= kronos_e($log['subject']) ?></strong>
                <small><span><?= kronos_e($log['status']) ?></span><?= kronos_e($log['created_at']) ?></small>
              </div>
              <?php endforeach; ?>
              </div>
              <?php if (!$logs): ?><p class="text-muted">No mail processed yet.</p><?php endif; ?>
            </div>
          </aside>
        </div>
        <?php require $dashDir . '/partials/layout-footer.php';
    }

    private function sendViaSmtp(string $to, string $subject, string $message, array $headers): bool
    {
        $host = (string) kronos_option('smtp_host', '');
        $port = (int) kronos_option('smtp_port', '587');
        if ($host === '') {
            throw new RuntimeException('SMTP host is empty.');
        }

        $encryption = (string) kronos_option('smtp_encryption', 'tls');
        $transportHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @stream_socket_client($transportHost . ':' . $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new RuntimeException($errstr ?: 'Could not connect to SMTP server.');
        }

        $read = fn(): string => (string) fgets($socket, 515);
        $write = function (string $line) use ($socket): void { fwrite($socket, $line . "\r\n"); };
        $expect = function (array $codes) use ($read): string {
            $response = $read();
            if (!in_array(substr($response, 0, 3), $codes, true)) {
                throw new RuntimeException('SMTP error: ' . trim($response));
            }
            return $response;
        };

        $expect(['220']);
        $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $expect(['250']);
        if ($encryption === 'tls') {
            $write('STARTTLS');
            $expect(['220']);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $expect(['250']);
        }
        $username = (string) kronos_option('smtp_username', '');
        $password = (string) kronos_option('smtp_password', '');
        if ($username !== '') {
            $write('AUTH LOGIN');
            $expect(['334']);
            $write(base64_encode($username));
            $expect(['334']);
            $write(base64_encode($password));
            $expect(['235']);
        }

        $from = (string) kronos_option('smtp_from_email', kronos_option('admin_email', 'noreply@localhost'));
        $write('MAIL FROM:<' . $from . '>');
        $expect(['250']);
        $write('RCPT TO:<' . $to . '>');
        $expect(['250', '251']);
        $write('DATA');
        $expect(['354']);
        $bodyHeaders = array_merge([
            'From: ' . (string) kronos_option('smtp_from_name', 'KronosCMS') . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $subject,
        ], $headers);
        $write(implode("\r\n", $bodyHeaders) . "\r\n\r\n" . $message . "\r\n.");
        $expect(['250']);
        $write('QUIT');
        fclose($socket);
        return true;
    }

    private function ensureTables(): void
    {
        KronosApp::getInstance()->db()->runSchema([
            "CREATE TABLE IF NOT EXISTS kronos_smtp_mail_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                recipient VARCHAR(191) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                mode VARCHAR(40) NOT NULL,
                status VARCHAR(40) NOT NULL,
                error_message TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ]);
    }

    private function logMail(string $to, string $subject, string $mode, bool $success, string $error): void
    {
        KronosApp::getInstance()->db()->insert('kronos_smtp_mail_log', [
            'recipient' => $to,
            'subject' => $subject,
            'mode' => $mode,
            'status' => $success ? 'sent' : 'failed',
            'error_message' => $error,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function requireDashboardAuth(array $params, callable $next): void
    {
        if (empty($_COOKIE['kronos_token'])) {
            kronos_redirect('/dashboard/login');
        }
        try {
            $app = KronosApp::getInstance();
            $jwt = new KronosJWT((string) $app->env('JWT_SECRET', ''), (int) $app->env('JWT_EXPIRY', 86400));
            $jwt->decode((string) $_COOKIE['kronos_token']);
        } catch (Throwable) {
            kronos_redirect('/dashboard/login');
        }
        $next();
    }
}
