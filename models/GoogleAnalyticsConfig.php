<?php

declare(strict_types=1);

namespace Osmium\Services\GoogleAnalytics\Models;

/**
 * Analytics (GTM/GA4) configuration helper.
 *
 * Loads this service's own settings file so theme blocks don't hardcode
 * them, matching the file-based config convention used by Xero/Stripe/
 * PayPal (app/config/services/{id}.json.php, not the services table's
 * config column - that's a schema for a future auto-generated settings UI,
 * unused here since this service ships a hand-written settings form).
 */
class GoogleAnalyticsConfig
{
    private static ?object $config = null;
    private static string $configPath = 'app/config/services/google-analytics.json.php';

    /**
     * Falls back to defaults (disabled, no GTM ID) if the config file is
     * missing, so installing this service stays inert until someone visits
     * its settings page and saves a GTM container ID.
     */
    public static function get(): object
    {
        $configLoaded = self::$config !== null;
        if ($configLoaded) return self::$config;

        $configFile = self::$configPath;

        $configExists = \file_exists($configFile);
        if (!$configExists) {
            self::$config = self::defaults();
            return self::$config;
        }

        $content = \file_get_contents($configFile);
        $jsonStart = \strpos(haystack: $content, needle: '{');

        $noJsonFound = $jsonStart === false;
        if ($noJsonFound) {
            self::$config = self::defaults();
            return self::$config;
        }

        $json = \substr(string: $content, offset: $jsonStart);
        $decoded = \json_decode($json);

        self::$config = $decoded->analytics ?? self::defaults();

        return self::$config;
    }

    public static function clearCache(): void
    {
        self::$config = null;
    }

    private static function defaults(): object
    {
        return (object) [
            'enabled' => false,
            'gtmId' => '',
        ];
    }
}
