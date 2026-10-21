<?php
/**
 * UpdateManager: Handles remote update checks, package download,
 * integrity verification, and in-place upgrade for PrestaShop.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class UpdateManager
{
    const GITHUB_REPO = 'raihan675/Presta-Shop-Data-Layer';
    const MANIFEST_URL = 'https://raw.githubusercontent.com/raihan675/Presta-Shop-Data-Layer/main/update-manifest.json';

    private $module;

    public function __construct(Module $module)
    {
        $this->module = $module;
    }

    /**
     * Check if a newer version exists on GitHub.
     * Uses a transient cache to avoid slowing down back office page loads.
     */
    public function checkForUpdates($force = false)
    {
        $cacheTime = (int)Configuration::get('PS_GTM_UPDATE_CACHE_TIME');
        $cacheData = Configuration::get('PS_GTM_UPDATE_CACHE_DATA');

        if (!$force && ($cacheTime > 0) && (time() - $cacheTime < 900) && !empty($cacheData)) {
            $cached = json_decode($cacheData, true);
            if (is_array($cached) && isset($cached['latest_version'])) {
                $cached['has_update'] = version_compare($cached['latest_version'], $this->module->version, '>');
                $cached['current_version'] = $this->module->version;
                return $cached;
            }
        }

        $urls = [
            'https://api.github.com/repos/raihan675/Presta-Shop-Data-Layer/contents/update-manifest.json',
            self::MANIFEST_URL . '?t=' . time()
        ];

        $response = null;
        $httpCode = 0;

        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PrestaShop-Module-Updater/' . $this->module->version);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/vnd.github.v3.raw',
                'Cache-Control: no-cache, no-store, must-revalidate',
                'Pragma: no-cache',
                'Expires: 0'
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200 && !empty($res)) {
                $response = $res;
                $httpCode = $code;
                break;
            }
        }

        if ($httpCode !== 200 || empty($response)) {
            return ['has_update' => false, 'error' => 'Unable to connect to the update repository.'];
        }

        $manifest = json_decode($response, true);
        if (!$manifest || !isset($manifest['latest_version'])) {
            return ['has_update' => false, 'error' => 'Malformed release manifest.'];
        }

        $hasUpdate = version_compare($manifest['latest_version'], $this->module->version, '>');

        $result = [
            'has_update' => $hasUpdate,
            'current_version' => $this->module->version,
            'latest_version' => $manifest['latest_version'],
            'release_notes' => isset($manifest['release_notes']) ? $manifest['release_notes'] : '',
            'download_url' => isset($manifest['download_url']) ? $manifest['download_url'] : '',
            'checksum' => isset($manifest['checksum']) ? $manifest['checksum'] : '',
            'min_ps_version' => isset($manifest['min_ps_version']) ? $manifest['min_ps_version'] : '1.7.8.0',
        ];

        Configuration::updateValue('PS_GTM_UPDATE_CACHE_TIME', time());
        Configuration::updateValue('PS_GTM_UPDATE_CACHE_DATA', json_encode($result));

        return $result;
    }

    public static function clearUpdateCache()
    {
        Configuration::deleteByName('PS_GTM_UPDATE_CACHE_TIME');
        Configuration::deleteByName('PS_GTM_UPDATE_CACHE_DATA');
    }

    /**
     * Execute atomic 1-click update
     */
    public function executeUpdate($downloadUrl, $expectedChecksum = '')
    {
        try {
            if (empty($downloadUrl)) {
                return ['success' => false, 'message' => 'Invalid download URL.'];
            }

            $moduleDir = _PS_MODULE_DIR_ . $this->module->name;
            $baseTempDir = (defined('_PS_CACHE_DIR_') && is_dir(_PS_CACHE_DIR_) && is_writable(_PS_CACHE_DIR_))
                ? _PS_CACHE_DIR_
                : sys_get_temp_dir() . DIRECTORY_SEPARATOR;

            $tempZip = $baseTempDir . $this->module->name . '_latest.zip';
            $backupDir = $baseTempDir . $this->module->name . '_backup_' . date('Ymd_His');

            // 1. Download Release Asset
            $fp = @fopen($tempZip, 'w+');
            if (!$fp) {
                return ['success' => false, 'message' => 'Cannot create temporary download file in ' . htmlspecialchars($baseTempDir)];
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $downloadUrl);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PrestaShop-Module-Updater/' . $this->module->version);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            @fclose($fp);

            if ($httpCode !== 200 || !file_exists($tempZip) || filesize($tempZip) === 0) {
                @unlink($tempZip);
                return ['success' => false, 'message' => 'Failed to download release archive from GitHub (HTTP ' . (int)$httpCode . ').'];
            }

            // 2. Validate Checksum (if provided)
            if (!empty($expectedChecksum)) {
                $actualChecksum = hash_file('sha256', $tempZip);
                if (strcasecmp($actualChecksum, $expectedChecksum) !== 0) {
                    @unlink($tempZip);
                    return ['success' => false, 'message' => 'Security validation failed: Checksum mismatch.'];
                }
            }

            // 3. Backup Current Module Files
            $this->recursiveCopy($moduleDir, $backupDir);

            // 4. Extract and In-Place Overwrite
            if (!class_exists('ZipArchive')) {
                @unlink($tempZip);
                return ['success' => false, 'message' => 'PHP ZipArchive extension is required for automated update. Please update via module ZIP upload.'];
            }

            $zip = new ZipArchive();
            if ($zip->open($tempZip) === true) {
                $extractPath = $baseTempDir . $this->module->name . '_extracted_' . time();
                @mkdir($extractPath, 0755, true);
                $zip->extractTo($extractPath);
                $zip->close();
                @unlink($tempZip);

                $sourceContentDir = $extractPath;
                if (is_dir($extractPath . '/' . $this->module->name)) {
                    $sourceContentDir = $extractPath . '/' . $this->module->name;
                }

                $this->recursiveCopy($sourceContentDir, $moduleDir);
                $this->recursiveDelete($extractPath);

                // 5. Trigger Native PrestaShop Module Upgrade Routines
                if (method_exists($this->module, 'runUpgradeModule')) {
                    try {
                        $this->module->runUpgradeModule();
                    } catch (\Throwable $ue) {}
                }

                // Synchronize database registry and clear all cache layers
                if (class_exists('PlatformAdapter')) {
                    PlatformAdapter::syncModuleDatabaseVersion($this->module->name, $this->module->version);
                    PlatformAdapter::clearAllCaches();
                } else {
                    if (class_exists('Tools')) {
                        @Tools::clearSmartyCache();
                        @Tools::clearXMLCache();
                    }
                }

                self::clearUpdateCache();

                return ['success' => true, 'message' => 'Module successfully updated to the latest version.'];
            } else {
                @unlink($tempZip);
                return ['success' => false, 'message' => 'Could not unpack the downloaded ZIP archive.'];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Update exception: ' . $e->getMessage()];
        }
    }

    private function recursiveCopy($src, $dst)
    {
        if (!is_dir($src)) return;
        $dir = @opendir($src);
        if (!$dir) return;
        @mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..') && ($file != '.git')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    @copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        @closedir($dir);
    }

    private function recursiveDelete($dir)
    {
        if (!is_dir($dir)) return;
        $files = @scandir($dir);
        if (!$files) return;
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : @unlink("$dir/$file");
        }
        @rmdir($dir);
    }
}
