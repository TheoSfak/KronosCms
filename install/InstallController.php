<?php
declare(strict_types=1);

/**
 * InstallController — Multi-step installation wizard logic.
 * Handles GET (display step) and POST (process step).
 */
class InstallController
{
    private string $rootDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
    }

    public function dispatch(): void
    {
        $step = max(1, min(3, (int) ($_GET['step'] ?? 1)));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($step);
        } else {
            $this->renderStep($step);
        }
    }

    // ------------------------------------------------------------------
    // POST handlers
    // ------------------------------------------------------------------

    private function handlePost(int $step): void
    {
        match ($step) {
            1 => $this->processStep1(),
            2 => $this->processStep2(),
            3 => $this->processStep3(),
            default => $this->redirect(1),
        };
    }

    private function processStep1(): void
    {
        $host   = trim($_POST['db_host'] ?? '127.0.0.1');
        $port   = (int) ($_POST['db_port'] ?? 3306);
        $dbName = trim($_POST['db_name'] ?? '');
        $user   = trim($_POST['db_user'] ?? '');
        $pass   = $_POST['db_pass'] ?? '';

        $errors = [];
        if ($dbName === '') {
            $errors[] = 'Database name is required.';
        }
        if ($user === '') {
            $errors[] = 'Database user is required.';
        }

        if (!empty($errors)) {
            $this->renderStep(1, $errors);
            return;
        }

        // Connect without selecting a database first, then create it if it doesn't exist
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Sanitise DB name (allow only word chars and hyphens)
            if (!preg_match('/^[\w\-]+$/', $dbName)) {
                $this->renderStep(1, ['Database name contains invalid characters.']);
                return;
            }

            // Create the database if it doesn't already exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            unset($pdo);
        } catch (PDOException $e) {
            $this->renderStep(1, ['Database connection failed: ' . $e->getMessage()]);
            return;
        }

        // Store DB config in session for step 2/3
        session_start();
        $_SESSION['install'] = [
            'db_host' => $host,
            'db_port' => $port,
            'db_name' => $dbName,
            'db_user' => $user,
            'db_pass' => $pass,
        ];

        $this->redirect(2);
    }

    private function processStep2(): void
    {
        session_start();
        if (empty($_SESSION['install'])) {
            $this->redirect(1);
        }

        $mode    = in_array($_POST['app_mode'] ?? '', ['cms', 'ecommerce'], true) ? $_POST['app_mode'] : 'cms';
        $appUrl  = rtrim(trim($_POST['app_url'] ?? ''), '/');
        $appName = trim($_POST['app_name'] ?? 'KronosCMS');

        if ($appUrl === '') {
            $this->renderStep(2, ['App URL is required.']);
            return;
        }

        $_SESSION['install']['app_mode'] = $mode;
        $_SESSION['install']['app_url']  = $appUrl;
        $_SESSION['install']['app_name'] = $appName;

        $this->redirect(3);
    }

    private function processStep3(): void
    {
        session_start();
        if (empty($_SESSION['install'])) {
            $this->redirect(1);
        }

        $username    = trim($_POST['admin_username'] ?? '');
        $email       = trim($_POST['admin_email'] ?? '');
        $password    = $_POST['admin_password'] ?? '';
        $passwordCfm = $_POST['admin_password_confirm'] ?? '';

        $errors = [];
        if ($username === '' || strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $passwordCfm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            $this->renderStep(3, $errors);
            return;
        }

        $cfg = $_SESSION['install'];

        // Run installation
        try {
            $this->runInstall($cfg, $username, $email, $password);
        } catch (Exception $e) {
            $this->renderStep(3, ['Installation failed: ' . $e->getMessage()]);
            return;
        }

        unset($_SESSION['install']);
        $this->renderComplete($cfg['app_url'] ?? '/');
    }

    // ------------------------------------------------------------------
    // Core install logic
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $cfg */
    private function runInstall(array $cfg, string $username, string $email, string $password): void
    {
        // 1. Connect to DB
        $dsn = "mysql:host={$cfg['db_host']};port={$cfg['db_port']};dbname={$cfg['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // 2. Create schema — require installer directly (no autoloader yet)
        require_once $this->rootDir . '/vendor/autoload.php';
        $db        = \Kronos\Core\KronosDB::init(
            $cfg['db_host'], (int) $cfg['db_port'],
            $cfg['db_name'], $cfg['db_user'], $cfg['db_pass']
        );
        $installer = new \Kronos\Core\KronosInstaller($db);
        $installer->install();

        // 3. Seed options
        $config = new \Kronos\Core\KronosConfig($db);
        $config->set('kronos_active_mode', $cfg['app_mode']);
        $config->set('app_name', $cfg['app_name']);
        $config->set('app_url', $cfg['app_url']);
        $config->set('kronos_installed', true);
        $config->set('kronos_version', \Kronos\Core\KronosVersion::VERSION);
        $config->set('openai_model', 'gpt-4o');

        // 4. Create admin user
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->insert('kronos_users', [
            'username'     => $username,
            'email'        => $email,
            'password_hash' => $hash,
            'role'         => 'app_manager',
            'display_name' => $username,
        ]);

        // 5. Write config/app.php (JWT secret, DB dsn for reference)
        $jwtSecret = bin2hex(random_bytes(48)); // 96-char hex
        $this->writeEnvFile($cfg, $jwtSecret);
        $this->writeConfigFile($cfg);
    }

    /** @param array<string, mixed> $cfg */
    private function writeEnvFile(array $cfg, string $jwtSecret): void
    {
        $envPath = $this->rootDir . '/.env';
        $appUrl  = $cfg['app_url'] ?? 'http://localhost/KronosCMS/public';
        $dbHost  = $cfg['db_host'];
        $dbPort  = $cfg['db_port'];
        $dbName  = $cfg['db_name'];
        $dbUser  = $cfg['db_user'];
        $dbPass  = addslashes($cfg['db_pass']);

        $content = <<<ENV
APP_ENV=production
APP_DEBUG=false
APP_URL={$appUrl}

DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}

JWT_SECRET={$jwtSecret}
JWT_EXPIRY=86400

OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o

STRIPE_PUBLIC_KEY=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=

PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_MODE=sandbox

HUB_API_URL={$appUrl}/hub-mock/
GITHUB_REPO=TheoSfak/KronosCms
ENV;

        file_put_contents($envPath, $content);
        chmod($envPath, 0600);
    }

    /** @param array<string, mixed> $cfg */
    private function writeConfigFile(array $cfg): void
    {
        $configDir = $this->rootDir . '/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $installedAt = date('Y-m-d H:i:s');
        $version     = \Kronos\Core\KronosVersion::VERSION;

        $content = <<<PHP
<?php
// KronosCMS — Auto-generated configuration file. Do not edit manually.
// Generated: {$installedAt}
return [
    'version'      => '{$version}',
    'installed_at' => '{$installedAt}',
];
PHP;

        file_put_contents($configDir . '/app.php', $content);
    }

    // ------------------------------------------------------------------
    // View rendering
    // ------------------------------------------------------------------

    private function renderStep(int $step, array $errors = []): void
    {
        require __DIR__ . '/steps/step' . $step . '.php';
    }

    private function renderComplete(string $appUrl): void
    {
        require __DIR__ . '/steps/complete.php';
    }

    private function redirect(int $step): void
    {
        $base = defined('KRONOS_BASE') ? KRONOS_BASE : '';
        header('Location: ' . $base . '/install/?step=' . $step);
        exit;
    }

    public function getErrors(): array
    {
        return [];
    }
}
