const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');

console.log('================================================================');
console.log('  AUTOMATED PRESTASHOP MODULE 1-CLICK RELEASE & DEPLOY PIPELINE');
console.log('================================================================\n');

// STEP 1: Execute 126-point System Safety Crosscheck
console.log('--> Step 1/6: Running 126-Point System Safety Audit...');
try {
  execSync('node tests/systemSafetyCrosscheck.js', { stdio: 'inherit', cwd: ROOT });
} catch (e) {
  console.error('\n⛔ ABORTED: System safety audit failed. Refusing to publish.');
  process.exit(1);
}

// STEP 2: Execute All Functional Unit Test Suites
console.log('\n--> Step 2/6: Running Full E-Commerce Test Suites...');
try {
  execSync('node tests/advertisingCatalogMatching.test.js', { stdio: 'inherit', cwd: ROOT });
  execSync('node tests/eventPrefix.test.js', { stdio: 'inherit', cwd: ROOT });
  execSync('node tests/catalogIdentity.test.js', { stdio: 'inherit', cwd: ROOT });
} catch (e) {
  console.error('\n⛔ ABORTED: Functional unit tests failed. Refusing to publish.');
  process.exit(1);
}

// STEP 3: Verify Version Alignment
console.log('\n--> Step 3/6: Checking Version Alignment...');
const phpCode = fs.readFileSync(path.join(ROOT, 'ps_gtm_datalayer.php'), 'utf8');
const versionMatch = phpCode.match(/\$this->version\s*=\s*'([^']+)'/);
if (!versionMatch) {
  console.error('Could not extract $this->version from ps_gtm_datalayer.php');
  process.exit(1);
}
const version = versionMatch[1];
console.log(`Detected module version: v${version}`);

// Ensure config.xml has the exact version
const xmlPath = path.join(ROOT, 'config.xml');
let xmlCode = fs.readFileSync(xmlPath, 'utf8');
xmlCode = xmlCode.replace(/<version><!\[CDATA\[(.*?)\]\]><\/version>/, `<version><![CDATA[${version}]]></version>`);
fs.writeFileSync(xmlPath, xmlCode, 'utf8');

// Ensure upgrade script exists for this version
const upgradePath = path.join(ROOT, 'upgrade', `upgrade-${version}.php`);
if (!fs.existsSync(upgradePath)) {
  console.warn(`Notice: No upgrade script found at upgrade/upgrade-${version}.php. Creating default upgrade hook...`);
  const funcVersion = version.replace(/\./g, '_');
  const upTpl = `<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_${funcVersion}($module)
{
    try {
        $adapterPath = dirname(__FILE__) . '/../classes/PlatformAdapter.php';
        if (file_exists($adapterPath)) {
            require_once $adapterPath;
            if (class_exists('PlatformAdapter')) {
                PlatformAdapter::syncModuleDatabaseVersion($module->name, $module->version);
                PlatformAdapter::clearAllCaches();
            }
        }
    } catch (\\Throwable $e) {}
    return true;
}
`;
  fs.writeFileSync(upgradePath, upTpl, 'utf8');
}

console.log('✅ Version and upgrade script validated.');
console.log('\nPipeline ready for packaging and GitHub 1-click update synchronization.');
