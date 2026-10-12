<?php

declare(strict_types=1);

namespace Osmium\Services\GoogleAnalytics\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\GoogleAnalytics\Models\GoogleAnalyticsConfig;

/**
 * Analytics (GTM/GA4) settings controller - a full-page form POST/redirect
 * flow (not the AJAX pattern other services use), matching how the
 * hardcoded settings pages it was extracted from already worked.
 *
 * Routes:
 *   - index() → /admin/settings/google-analytics/  (GET shows the form, POST saves it)
 */
class GoogleAnalyticsController extends AdminController
{
    private const CONFIG_FILE_PATH = 'app/config/services/google-analytics.json.php';
    private const DEFAULT_CONFIG = <<<'JSON'
        <?php exit(); ?>
        {
            "analytics": {
                "enabled": false,
                "gtmId": ""
            }
        }
        JSON;

    public function index(): void
    {
        $isPost = $this->isPost();
        if ($isPost) $this->handleSubmit();

        $this->data['admin']['config']['analytics'] = (array) GoogleAnalyticsConfig::get();
        $this->data['admin']['settingsSaved'] = $_SESSION['google_analytics_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['google_analytics_settings_error'] ?? false;
        unset($_SESSION['google_analytics_settings_saved'], $_SESSION['google_analytics_settings_error']);

        $this->setView('google-analytics/index.phtml');
    }

    private function handleSubmit(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) {
            $_SESSION['google_analytics_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/google-analytics/');
        }

        $enabled = isset($_POST['enabled']);
        $gtmId = \trim($_POST['gtm_id'] ?? '');

        $needsValidId = $enabled || $gtmId !== '';
        if ($needsValidId && !GoogleAnalyticsConfig::isValidGtmId($gtmId)) {
            $_SESSION['google_analytics_settings_error'] = 'Container ID must look like GTM-ABC1234 (capital letters and digits).';
            $this->redirect('settings/google-analytics/');
        }

        $this->saveConfig($enabled, $gtmId);

        $this->admin->model->changelog->log(
            description: 'Updated analytics settings',
            recordType: 'settings',
        );

        GoogleAnalyticsConfig::clearCache();

        $_SESSION['google_analytics_settings_saved'] = true;
        $this->redirect('settings/google-analytics/');
    }

    private function saveConfig(bool $enabled, string $gtmId): void
    {
        $configExists = \file_exists(self::CONFIG_FILE_PATH);
        if (!$configExists) $this->ensureConfigDirectoryExists();

        $content = $configExists ? \file_get_contents(self::CONFIG_FILE_PATH) : self::DEFAULT_CONFIG;

        $jsonStart = \strpos(haystack: $content, needle: '{');
        $phpHeader = \substr(string: $content, offset: 0, length: $jsonStart);
        $data = \json_decode(\substr(string: $content, offset: $jsonStart), associative: true) ?? [];

        $data['analytics'] = ['enabled' => $enabled, 'gtmId' => $gtmId];

        $newJson = \json_encode(
            value: $data,
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        \file_put_contents(self::CONFIG_FILE_PATH, $phpHeader . $newJson . "\n");
    }

    private function ensureConfigDirectoryExists(): void
    {
        $dir = \dirname(self::CONFIG_FILE_PATH);
        $alreadyExists = \is_dir($dir);
        if (!$alreadyExists) \mkdir(directory: $dir, permissions: 0755, recursive: true);
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
