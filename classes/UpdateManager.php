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
     * Check if a newer version exists on GitHub
     */
    public function checkForUpdates()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::MANIFEST_URL . '?t=' . time());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PrestaShop-Module-Updater/' . $this->module->version);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cache-Control: no-cache, no-store, must-revalidate',
            'Pragma: no-cache',
            'Expires: 0'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return ['has_update' => false, 'error' => 'Unable to connect to the update repository.'];
        }

        $manifest = json_decode($response, true);
        if (!$manifest || !isset($manifest['latest_version'])) {
            return ['has_update' => false, 'error' => 'Malformed release manifest.'];
        }

        $hasUpdate = version_compare($manifest['latest_version'], $this->module->version, '>');

        return [
            'has_update' => $hasUpdate,
            'current_version' => $this->module->version,
            'latest_version' => $manifest['latest_version'],
            'release_notes' => isset($manifest['release_notes']) ? $manifest['release_notes'] : '',
            'download_url' => isset($manifest['download_url']) ? $manifest['download_url'] : '',
            'checksum' => isset($manifest['checksum']) ? $manifest['checksum'] : '',
            'min_ps_version' => isset($manifest['min_ps_version']) ? $manifest['min_ps_version'] : '1.7.8.0',
        ];
    }

    /**
     * Execute atomic 1-click update
     */
    public function executeUpdate($downloadUrl, $expectedChecksum = '')
    {
        if (empty($downloadUrl)) {
            return ['success' => false, 'message' => 'Invalid download URL.'];
        }

        $moduleDir = _PS_MODULE_DIR_ . $this->module->name;
        $tempZip = _PS_CACHE_DIR_ . $this->module->name . '_latest.zip';
        $backupDir = _PS_CACHE_DIR_ . $this->module->name . '_backup_' . date('Ymd_His');

        // 1. Download Release Asset
        $fp = fopen($tempZip, 'w+');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PrestaShop-Module-Updater/' . $this->module->version);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode !== 200 || !file_exists($tempZip) || filesize($tempZip) === 0) {
            @unlink($tempZip);
            return ['success' => false, 'message' => 'Failed to download release archive from GitHub.'];
        }

        // 2. Validate Checksum (if provided)
        if (!empty($expectedChecksum)) {
            $actualChecksum = hash_file('sha256', $tempZip);
            if (strcasecmp($actualChecksum, $expectedChecksum) !== 0) {
                @unlink($tempZip);
                return ['success' => false, 'message' => 'Security validation failed: Checksum mismatch.'];
            }
        }

        // 3. Create Backup of current module
        $this->recursiveCopy($moduleDir, $backupDir);

        // 4. Extract update archive over existing module
        $zip = new ZipArchive();
        if ($zip->open($tempZip) === true) {
            $tempExtract = _PS_CACHE_DIR_ . 'ps_gtm_extract_' . time();
            @mkdir($tempExtract, 0755, true);
            $zip->extractTo($tempExtract);
            $zip->close();
            @unlink($tempZip);

            $sourceDir = file_exists($tempExtract . '/' . $this->module->name) 
                ? $tempExtract . '/' . $this->module->name 
                : $tempExtract;

            // Overwrite files safely
            $this->recursiveCopy($sourceDir, $moduleDir);
            $this->recursiveDelete($tempExtract);

            // 5. Trigger PrestaShop Module Upgrade routines if present
            if (method_exists('Module', 'initUpgradeModule')) {
                Module::initUpgradeModule($this->module);
            }
            if (method_exists($this->module, 'runUpgradeModule')) {
                $this->module->runUpgradeModule();
            }

            // Clear Smarty & PrestaShop Class Cache
            Tools::clearSmartyCache();
            Tools::clearXMLCache();

            return ['success' => true, 'message' => 'Module successfully updated to the latest version.'];
        } else {
            @unlink($tempZip);
            return ['success' => false, 'message' => 'Could not unpack the downloaded ZIP archive.'];
        }
    }

    private function recursiveCopy($src, $dst)
    {
        if (!is_dir($src)) return;
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..') && ($file != '.git')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function recursiveDelete($dir)
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveDelete("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
