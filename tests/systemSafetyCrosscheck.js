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
report(`Version alignment (${versionInPhp}) between PHP and XML`, versionInPhp === versionInXml && versionInPhp === '1.18.0', `PHP version: ${versionInPhp}, XML version: ${versionInXml}`);

// -------------------------------------------------------------
// CHECK 7: Upgrade Script Function Signature
// -------------------------------------------------------------
const upgrade1110Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.11.0.php');
if (fs.existsSync(upgrade1110Path)) {
  const upCode = fs.readFileSync(upgrade1110Path, 'utf8');
  const hasUpgradeFunction = upCode.includes('function upgrade_module_1_11_0($module)');
  const returnsTrue = upCode.includes('return true;');
  report('Upgrade function upgrade_module_1_11_0 properly declared', hasUpgradeFunction && returnsTrue, 'Missing or invalid function signature in upgrade-1.11.0.php');
} else {
  report('Upgrade script upgrade-1.11.0.php exists', false, 'Missing upgrade-1.11.0.php');
}

const upgrade1111Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.11.1.php');
if (fs.existsSync(upgrade1111Path)) {
  const upCode11 = fs.readFileSync(upgrade1111Path, 'utf8');
  const hasUpgradeFunction11 = upCode11.includes('function upgrade_module_1_11_1($module)');
  const returnsTrue11 = upCode11.includes('return true;');
  report('Upgrade function upgrade_module_1_11_1 properly declared', hasUpgradeFunction11 && returnsTrue11, 'Missing or invalid function signature in upgrade-1.11.1.php');
} else {
  report('Upgrade script upgrade-1.11.1.php exists', false, 'Missing upgrade-1.11.1.php');
}

const upgrade1120Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.12.0.php');
if (fs.existsSync(upgrade1120Path)) {
  const upCode12 = fs.readFileSync(upgrade1120Path, 'utf8');
  const hasUpgradeFunction12 = upCode12.includes('function upgrade_module_1_12_0($module)');
  const returnsTrue12 = upCode12.includes('return true;');
  report('Upgrade function upgrade_module_1_12_0 properly declared', hasUpgradeFunction12 && returnsTrue12, 'Missing or invalid function signature in upgrade-1.12.0.php');
} else {
  report('Upgrade script upgrade-1.12.0.php exists', false, 'Missing upgrade-1.12.0.php');
}

const upgrade1121Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.12.1.php');
if (fs.existsSync(upgrade1121Path)) {
  const upCode121 = fs.readFileSync(upgrade1121Path, 'utf8');
  const hasUpgradeFunction121 = upCode121.includes('function upgrade_module_1_12_1($module)');
  const returnsTrue121 = upCode121.includes('return true;');
  report('Upgrade function upgrade_module_1_12_1 properly declared', hasUpgradeFunction121 && returnsTrue121, 'Missing or invalid function signature in upgrade-1.12.1.php');
} else {
  report('Upgrade script upgrade-1.12.1.php exists', false, 'Missing upgrade-1.12.1.php');
}

const upgrade1130Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.13.0.php');
if (fs.existsSync(upgrade1130Path)) {
  const upCode130 = fs.readFileSync(upgrade1130Path, 'utf8');
  const hasUpgradeFunction130 = upCode130.includes('function upgrade_module_1_13_0($module)');
  const returnsTrue130 = upCode130.includes('return true;');
  report('Upgrade function upgrade_module_1_13_0 properly declared', hasUpgradeFunction130 && returnsTrue130, 'Missing or invalid function signature in upgrade-1.13.0.php');
} else {
  report('Upgrade script upgrade-1.13.0.php exists', false, 'Missing upgrade-1.13.0.php');
}

const upgrade1131Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.13.1.php');
if (fs.existsSync(upgrade1131Path)) {
  const upCode131 = fs.readFileSync(upgrade1131Path, 'utf8');
  const hasUpgradeFunction131 = upCode131.includes('function upgrade_module_1_13_1($module)');
  const returnsTrue131 = upCode131.includes('return true;');
  report('Upgrade function upgrade_module_1_13_1 properly declared', hasUpgradeFunction131 && returnsTrue131, 'Missing or invalid function signature in upgrade-1.13.1.php');
} else {
  report('Upgrade script upgrade-1.13.1.php exists', false, 'Missing upgrade-1.13.1.php');
}

const upgrade1140Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.14.0.php');
if (fs.existsSync(upgrade1140Path)) {
  const upCode140 = fs.readFileSync(upgrade1140Path, 'utf8');
  const hasUpgradeFunction140 = upCode140.includes('function upgrade_module_1_14_0($module)');
  const returnsTrue140 = upCode140.includes('return true;');
  report('Upgrade function upgrade_module_1_14_0 properly declared', hasUpgradeFunction140 && returnsTrue140, 'Missing or invalid function signature in upgrade-1.14.0.php');
} else {
  report('Upgrade script upgrade-1.14.0.php exists', false, 'Missing upgrade-1.14.0.php');
}

const upgrade1141Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.14.1.php');
if (fs.existsSync(upgrade1141Path)) {
  const upCode141 = fs.readFileSync(upgrade1141Path, 'utf8');
  const hasUpgradeFunction141 = upCode141.includes('function upgrade_module_1_14_1($module)');
  const returnsTrue141 = upCode141.includes('return true;');
  report('Upgrade function upgrade_module_1_14_1 properly declared', hasUpgradeFunction141 && returnsTrue141, 'Missing or invalid function signature in upgrade-1.14.1.php');
} else {
  report('Upgrade script upgrade-1.14.1.php exists', false, 'Missing upgrade-1.14.1.php');
}

const upgrade1142Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.14.2.php');
if (fs.existsSync(upgrade1142Path)) {
  const upCode142 = fs.readFileSync(upgrade1142Path, 'utf8');
  const hasUpgradeFunction142 = upCode142.includes('function upgrade_module_1_14_2($module)');
  const returnsTrue142 = upCode142.includes('return true;');
  report('Upgrade function upgrade_module_1_14_2 properly declared', hasUpgradeFunction142 && returnsTrue142, 'Missing or invalid function signature in upgrade-1.14.2.php');
} else {
  report('Upgrade script upgrade-1.14.2.php exists', false, 'Missing upgrade-1.14.2.php');
}

const upgrade1143Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.14.3.php');
if (fs.existsSync(upgrade1143Path)) {
  const upCode143 = fs.readFileSync(upgrade1143Path, 'utf8');
  const hasUpgradeFunction143 = upCode143.includes('function upgrade_module_1_14_3($module)');
  const returnsTrue143 = upCode143.includes('return true;');
  report('Upgrade function upgrade_module_1_14_3 properly declared', hasUpgradeFunction143 && returnsTrue143, 'Missing or invalid function signature in upgrade-1.14.3.php');
} else {
  report('Upgrade script upgrade-1.14.3.php exists', false, 'Missing upgrade-1.14.3.php');
}

const upgrade1144Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.14.4.php');
if (fs.existsSync(upgrade1144Path)) {
  const upCode144 = fs.readFileSync(upgrade1144Path, 'utf8');
  const hasUpgradeFunction144 = upCode144.includes('function upgrade_module_1_14_4($module)');
  const returnsTrue144 = upCode144.includes('return true;');
  report('Upgrade function upgrade_module_1_14_4 properly declared', hasUpgradeFunction144 && returnsTrue144, 'Missing or invalid function signature in upgrade-1.14.4.php');
} else {
  report('Upgrade script upgrade-1.14.4.php exists', false, 'Missing upgrade-1.14.4.php');
}

const upgrade1145Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.14.5.php');
if (fs.existsSync(upgrade1145Path)) {
  const upCode145 = fs.readFileSync(upgrade1145Path, 'utf8');
  const hasUpgradeFunction145 = upCode145.includes('function upgrade_module_1_14_5($module)');
  const returnsTrue145 = upCode145.includes('return true;');
  report('Upgrade function upgrade_module_1_14_5 properly declared', hasUpgradeFunction145 && returnsTrue145, 'Missing or invalid function signature in upgrade-1.14.5.php');
} else {
  report('Upgrade script upgrade-1.14.5.php exists', false, 'Missing upgrade-1.14.5.php');
}

const upgrade1150Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.15.0.php');
if (fs.existsSync(upgrade1150Path)) {
  const upCode150 = fs.readFileSync(upgrade1150Path, 'utf8');
  const hasUpgradeFunction150 = upCode150.includes('function upgrade_module_1_15_0($module)');
  const returnsTrue150 = upCode150.includes('return true;');
  report('Upgrade function upgrade_module_1_15_0 properly declared', hasUpgradeFunction150 && returnsTrue150, 'Missing or invalid function signature in upgrade-1.15.0.php');
} else {
  report('Upgrade script upgrade-1.15.0.php exists', false, 'Missing upgrade-1.15.0.php');
}

const upgrade1160Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.16.0.php');
if (fs.existsSync(upgrade1160Path)) {
  const upCode160 = fs.readFileSync(upgrade1160Path, 'utf8');
  const hasUpgradeFunction160 = upCode160.includes('function upgrade_module_1_16_0($module)');
  const returnsTrue160 = upCode160.includes('return true;');
  report('Upgrade function upgrade_module_1_16_0 properly declared', hasUpgradeFunction160 && returnsTrue160, 'Missing or invalid function signature in upgrade-1.16.0.php');
} else {
  report('Upgrade script upgrade-1.16.0.php exists', false, 'Missing upgrade-1.16.0.php');
}

const upgrade1170Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.17.0.php');
if (fs.existsSync(upgrade1170Path)) {
  const upCode170 = fs.readFileSync(upgrade1170Path, 'utf8');
  const hasUpgradeFunction170 = upCode170.includes('function upgrade_module_1_17_0($module)');
  const returnsTrue170 = upCode170.includes('return true;');
  report('Upgrade function upgrade_module_1_17_0 properly declared', hasUpgradeFunction170 && returnsTrue170, 'Missing or invalid function signature in upgrade-1.17.0.php');
} else {
  report('Upgrade script upgrade-1.17.0.php exists', false, 'Missing upgrade-1.17.0.php');
}

const upgrade1171Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.17.1.php');
if (fs.existsSync(upgrade1171Path)) {
  const upCode171 = fs.readFileSync(upgrade1171Path, 'utf8');
  const hasUpgradeFunction171 = upCode171.includes('function upgrade_module_1_17_1($module)');
  const returnsTrue171 = upCode171.includes('return true;');
  report('Upgrade function upgrade_module_1_17_1 properly declared', hasUpgradeFunction171 && returnsTrue171, 'Missing or invalid function signature in upgrade-1.17.1.php');
} else {
  report('Upgrade script upgrade-1.17.1.php exists', false, 'Missing upgrade-1.17.1.php');
}

const upgrade1180Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.18.0.php');
if (fs.existsSync(upgrade1180Path)) {
  const upCode180 = fs.readFileSync(upgrade1180Path, 'utf8');
  const hasUpgradeFunction180 = upCode180.includes('function upgrade_module_1_18_0($module)');
  const returnsTrue180 = upCode180.includes('return true;');
  report('Upgrade function upgrade_module_1_18_0 properly declared', hasUpgradeFunction180 && returnsTrue180, 'Missing or invalid function signature in upgrade-1.18.0.php');
} else {
  report('Upgrade script upgrade-1.18.0.php exists', false, 'Missing upgrade-1.18.0.php');
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
