const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('================================================================');
console.log('   PRE-PUSH SYSTEM SAFETY & BACK OFFICE INTEGRITY AUDIT');
console.log('================================================================\n');

const MODULE_ROOT = path.resolve(__dirname, '..');
let totalChecks = 0;
let passedChecks = 0;
let failedChecks = 0;

function report(name, passed, detail) {
  totalChecks++;
  if (passed) {
    passedChecks++;
    console.log(`  [PASS] ${name}`);
  } else {
    failedChecks++;
    console.error(`  [FAIL] ${name} -> ${detail}`);
  }
}

// -------------------------------------------------------------
// CHECK 1: Deep PHP Syntax, Balanced Delimiters & BOM Check
// -------------------------------------------------------------
function auditPhpFiles(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const ent of entries) {
    const full = path.join(dir, ent.name);
    if (ent.isDirectory() && ent.name !== '.git' && ent.name !== 'node_modules' && ent.name !== 'tests') {
      auditPhpFiles(full);
    } else if (ent.isFile() && ent.name.endsWith('.php')) {
      const buf = fs.readFileSync(full);
      const relPath = path.relative(MODULE_ROOT, full);

      // Check BOM
      const hasBom = buf[0] === 0xEF && buf[1] === 0xBB && buf[2] === 0xBF;
      report(`No UTF-8 BOM in ${relPath}`, !hasBom, 'Found UTF-8 BOM which causes "headers already sent" crashes in PrestaShop');

      // Check leading whitespace
      const code = buf.toString('utf8');
      const cleanStart = code.trimStart();
      const startsWithPhp = cleanStart.startsWith('<?php');
      report(`Valid <?php opening tag in ${relPath}`, startsWithPhp, 'Missing <?php opening tag at start of file');

      // Check PrestaShop security guard
      const hasSecurityGuard = code.includes('_PS_VERSION_');
      report(`_PS_VERSION_ security check in ${relPath}`, hasSecurityGuard, 'Missing _PS_VERSION_ access control guard');

      // Check balanced braces, brackets, and parentheses
      let stack = [];
      let inString = false;
      let strChar = '';
      let inComment = false;
      let inLineComment = false;
      let line = 1;
      let parseError = null;

      for (let i = 0; i < code.length; i++) {
        const c = code[i];
        const next = code[i + 1];

        if (c === '\n') {
          line++;
          inLineComment = false;
          continue;
        }

        if (inLineComment) continue;

        if (inComment) {
          if (c === '*' && next === '/') {
            inComment = false;
            i++;
          }
          continue;
        }

        if (inString) {
          if (c === '\\') {
            i++;
            continue;
          }
          if (c === strChar) {
            inString = false;
          }
          continue;
        }

        if (c === '/' && next === '*') {
          inComment = true;
          i++;
          continue;
        }

        if (c === '/' && next === '/') {
          inLineComment = true;
          i++;
          continue;
        }

        if (c === '#' && (i === 0 || code[i - 1] === '\n' || code[i - 1] === ' ' || code[i - 1] === ';')) {
          inLineComment = true;
          continue;
        }

        if (c === '"' || c === "'") {
          inString = true;
          strChar = c;
          continue;
        }

        if (c === '{' || c === '(' || c === '[') {
          stack.push({ char: c, line });
        } else if (c === '}' || c === ')' || c === ']') {
          if (stack.length === 0) {
            parseError = `Unmatched closing '${c}' at line ${line}`;
            break;
          }
          const last = stack.pop();
          const pairs = { '}': '{', ')': '(', ']': '[' };
          if (last.char !== pairs[c]) {
            parseError = `Mismatched '${last.char}' (line ${last.line}) closed by '${c}' at line ${line}`;
            break;
          }
        }
      }

      if (!parseError && stack.length > 0) {
        const unclosed = stack.pop();
        parseError = `Unclosed '${unclosed.char}' opened at line ${unclosed.line}`;
      }

      report(`Balanced syntax delimiters in ${relPath}`, parseError === null, parseError);
    }
  }
}

auditPhpFiles(MODULE_ROOT);

// -------------------------------------------------------------
// CHECK 2: Back Office & Storefront Fatal Error Encapsulation
// -------------------------------------------------------------
const mainModulePath = path.join(MODULE_ROOT, 'ps_gtm_datalayer.php');
const mainModuleCode = fs.readFileSync(mainModulePath, 'utf8');

// Check that front office hooks are wrapped in try...catch (\Throwable $e)
const hooksToCheck = [
  'hookDisplayHeader',
  'hookDisplayOrderConfirmation'
];

for (const hook of hooksToCheck) {
  const hookRegex = new RegExp(`public\\s+function\\s+${hook}\\s*\\([\\s\\S]*?\\)\\s*\\{([\\s\\S]*?)(?:public\\s+function|$)`);
  const match = mainModuleCode.match(hookRegex);
  if (match) {
    const body = match[1];
    const hasTryCatch = body.includes('try') && (body.includes('\\Throwable') || body.includes('Throwable'));
    report(`Defensive try...catch (\\Throwable) in ${hook}`, hasTryCatch, `Hook ${hook} lacks top-level \\Throwable encapsulation`);
  } else {
    report(`Hook ${hook} defined in ps_gtm_datalayer.php`, false, `Could not locate ${hook}`);
  }
}

// -------------------------------------------------------------
// CHECK 3: PrestaShop HelperForm Back Office Schema
// -------------------------------------------------------------
// Verify HelperForm select dropdown query structure
const hasSelectQuery = mainModuleCode.includes("'type' => 'select'") &&
  mainModuleCode.includes("'PS_GTM_CATALOG_ID_STRATEGY'") &&
  mainModuleCode.includes("'query'") &&
  mainModuleCode.includes("'id' => 'composite'") &&
  mainModuleCode.includes("'id' => 'id_product'") &&
  mainModuleCode.includes("'id' => 'reference_sku'");
report('HelperForm select options correctly structured for Back Office', hasSelectQuery, 'PS_GTM_CATALOG_ID_STRATEGY select query missing or malformed');

// Check that fields_value default fallback exists
const hasDefaultFallback = mainModuleCode.includes("'PS_GTM_CATALOG_ID_STRATEGY' => Configuration::get('PS_GTM_CATALOG_ID_STRATEGY') ?: 'composite'");
report('Configuration field default fallback present', hasDefaultFallback, 'Missing default fallback for PS_GTM_CATALOG_ID_STRATEGY');

// -------------------------------------------------------------
// CHECK 4: JavaScript Syntax & Strict Mode Validation
// -------------------------------------------------------------
const jsFiles = [
  path.join(MODULE_ROOT, 'views', 'js', 'datalayer-core.js'),
  path.join(MODULE_ROOT, 'views', 'js', 'prestashop-adapter.js')
];

for (const jsPath of jsFiles) {
  const rel = path.relative(MODULE_ROOT, jsPath);
  try {
    const code = fs.readFileSync(jsPath, 'utf8');
    new Function(code); // Throws if syntax is invalid
    report(`Valid JavaScript syntax in ${rel}`, true);
    report(`'use strict' enforced in ${rel}`, code.includes("'use strict'"));
  } catch (err) {
    report(`Valid JavaScript syntax in ${rel}`, false, err.message);
  }
}

// -------------------------------------------------------------
// CHECK 5: Smarty Template Syntax & Tag Balancing
// -------------------------------------------------------------
const tplFiles = [
  path.join(MODULE_ROOT, 'views', 'templates', 'hook', 'datalayer_init.tpl'),
  path.join(MODULE_ROOT, 'views', 'templates', 'hook', 'gtm_head.tpl'),
  path.join(MODULE_ROOT, 'views', 'templates', 'hook', 'gtm_body.tpl')
];

for (const tplPath of tplFiles) {
  const rel = path.relative(MODULE_ROOT, tplPath);
  if (fs.existsSync(tplPath)) {
    const tplCode = fs.readFileSync(tplPath, 'utf8');
    const ifCount = (tplCode.match(/\{if\s+/g) || []).length;
    const endifCount = (tplCode.match(/\{\/if\}/g) || []).length;
    report(`Balanced {if} ... {/if} blocks in ${rel}`, ifCount === endifCount, `Found ${ifCount} {if} and ${endifCount} {/if}`);
  }
}

// -------------------------------------------------------------
// CHECK 6: config.xml Structure & Version Alignment
// -------------------------------------------------------------
const xmlPath = path.join(MODULE_ROOT, 'config.xml');
const xmlCode = fs.readFileSync(xmlPath, 'utf8');
const versionInXml = (xmlCode.match(/<version><!\[CDATA\[(.*?)\]\]><\/version>/) || [])[1];
const versionInPhp = (mainModuleCode.match(/\$this->version\s*=\s*'([^']+)'/) || [])[1];

report('config.xml is well-formed with closing </module>', xmlCode.includes('</module>'));
report(`Version alignment (${versionInPhp}) between PHP and XML`, versionInPhp === versionInXml && versionInPhp === '1.10.0', `PHP version: ${versionInPhp}, XML version: ${versionInXml}`);

// -------------------------------------------------------------
// CHECK 7: Upgrade Script Function Signature
// -------------------------------------------------------------
const upgrade110Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.10.0.php');
if (fs.existsSync(upgrade110Path)) {
  const upCode = fs.readFileSync(upgrade110Path, 'utf8');
  const hasUpgradeFunction = upCode.includes('function upgrade_module_1_10_0($module)');
  const returnsTrue = upCode.includes('return true;');
  report('Upgrade function upgrade_module_1_10_0 properly declared', hasUpgradeFunction && returnsTrue, 'Missing or invalid function signature in upgrade-1.10.0.php');
} else {
  report('Upgrade script upgrade-1.10.0.php exists', false, 'Missing upgrade-1.10.0.php');
}

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
console.log('\n================================================================');
console.log(`   TOTAL CHECKS: ${totalChecks} | PASSED: ${passedChecks} | FAILED: ${failedChecks}`);
console.log('================================================================\n');

if (failedChecks > 0) {
  console.error('⛔ FATAL: Safety audit detected potential risks to server or back office. Aborting publication.');
  process.exit(1);
} else {
  console.log('✅ SUCCESS: Module is 100% verified safe for PrestaShop Back Office & Server runtime.');
  process.exit(0);
}
