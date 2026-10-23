# PrestaShop DataLayer: Autonomous Release & Safety Rules

## 1. Mandatory Pre-Publication Safety Gate: Prevent Back Office Errors

Critical Requirement: Before publishing, packaging, or deploying any code changes, thoroughly inspect and test whether the changes could cause errors in the PrestaShop Back Office.

### Pre-Release Safety Rules:
1. **Zero UTF-8 BOM**: Never introduce byte-order marks in any PHP, TPL, JS, or JSON file (prevents "Headers already sent").
2. **Balanced Syntax**: Every PHP file must have balanced delimiters `{}`, `()`, `[]` and pass PHP syntax validation.
3. **Security Guard**: Every `.php` file must start with `if (!defined('_PS_VERSION_')) { exit; }`.
4. **Defensive Exception Shielding**: All hook callbacks and administration controller methods must be wrapped in `try { ... } catch (\Throwable $e)` so an error never crashes the Back Office or storefront.
5. **Configuration Fallbacks**: Every `Configuration::get()` must have a default fallback value for new or unconfigured stores.
6. **Zero Frontend Scripts in Back Office**: Frontend tracking scripts (`datalayer-core.js`) must never load on administration pages.
7. **Safe State Isolation**: Test that module configuration loads and saves correctly when any platform (Meta, TikTok, Pinterest, Google Ads) is disabled, enabled, or partially configured. Missing credentials must show friendly alerts, never 500 fatal errors.
8. **Run All Automated Suites**:
   - `node tests/systemSafetyCrosscheck.js` (Must pass all 167+ checks with 0 failures)
   - `node --test tests/advertisingCatalogMatching.test.js`
   - `node --test tests/catalogIdentity.test.js`
   - `node tests/eventPrefix.test.js`

---

## 2. Autonomous Post-Update Release & Distribution Protocol

Whenever ANY change, fix, or feature is made to the module, autonomously complete the following release pipeline without requiring additional instructions:

1. **Version Alignment & Upgrade Migration**:
   - Increment semantic version (e.g. `1.13.1` -> `1.13.2`) in:
     - `ps_gtm_datalayer.php` (`$this->version = 'X.Y.Z';`)
     - `config.xml` (`<version><![CDATA[X.Y.Z]]></version>`)
     - `update-manifest.json` (`latest_version`, `download_url`, `release_notes`)
     - `CHANGELOG.md` (Add new release section)
     - `tests/systemSafetyCrosscheck.js` (Update version check and upgrade function check)
   - Create `upgrade/upgrade-X.Y.Z.php` to handle database migrations and Smarty/module cache flushing (`_clearCache`).

2. **Package Production Archive**:
   - Run `powershell -ExecutionPolicy Bypass -File scripts/package-release.ps1`.
   - Ensure 0 backslashes in `ps_gtm_datalayer.zip` (strictly forward slashes).
   - Automatically compute SHA-256 and update `update-manifest.json`.
   - Copy `ps_gtm_datalayer.zip` to `C:\Users\Webanalyticspro BD\Downloads\ps_gtm_datalayer.zip`.

3. **Git Commit, Tag & Push**:
   - Commit all changes (`git commit -m "..."`).
   - Create annotated tag (`git tag -a vX.Y.Z -m "..."`).
   - Push branch and tags (`git push origin main --tags`).

4. **GitHub Release Publication & Asset Upload (Crucial for 1-Click Update)**:
   - Call GitHub Releases API to create release `vX.Y.Z`.
   - Upload `ps_gtm_datalayer.zip` directly to the release assets.
   - Verify with `curl.exe -I` that the asset download URL returns `HTTP 302 Found / 200 OK`.
   - Confirm in the final report that the 1-Click Update in PrestaShop Back Office is live and working.
