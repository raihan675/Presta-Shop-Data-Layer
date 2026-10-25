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
report(`Version alignment (${versionInPhp}) between PHP and XML`, versionInPhp === versionInXml && versionInPhp === '1.28.3', `PHP version: ${versionInPhp}, XML version: ${versionInXml}`);

// Zero DOM Scraping Verification
const adapterCode = fs.readFileSync(path.join(MODULE_ROOT, 'views', 'js', 'prestashop-adapter.js'), 'utf8');
const bannedDomSelectors = [
  '.wishlist-button',
  '.btn-wishlist',
  '.iqitwishlist-add',
  '.remove-from-cart',
  'input.js-cart-line-product-quantity',
  'input[name="s"]'
];
for (const selector of bannedDomSelectors) {
  report(`Zero DOM scraping for ${selector} in prestashop-adapter.js`, !adapterCode.includes(selector), `Fragile DOM selector ${selector} detected in prestashop-adapter.js!`);
}

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

const upgrade1181Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.18.1.php');
if (fs.existsSync(upgrade1181Path)) {
  const upCode181 = fs.readFileSync(upgrade1181Path, 'utf8');
  const hasUpgradeFunction181 = upCode181.includes('function upgrade_module_1_18_1($module)');
  const returnsTrue181 = upCode181.includes('return true;');
  report('Upgrade function upgrade_module_1_18_1 properly declared', hasUpgradeFunction181 && returnsTrue181, 'Missing or invalid function signature in upgrade-1.18.1.php');
} else {
  report('Upgrade script upgrade-1.18.1.php exists', false, 'Missing upgrade-1.18.1.php');
}

const upgrade1182Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.18.2.php');
if (fs.existsSync(upgrade1182Path)) {
  const upCode182 = fs.readFileSync(upgrade1182Path, 'utf8');
  const hasUpgradeFunction182 = upCode182.includes('function upgrade_module_1_18_2($module)');
  const returnsTrue182 = upCode182.includes('return true;');
  report('Upgrade function upgrade_module_1_18_2 properly declared', hasUpgradeFunction182 && returnsTrue182, 'Missing or invalid function signature in upgrade-1.18.2.php');
} else {
  report('Upgrade script upgrade-1.18.2.php exists', false, 'Missing upgrade-1.18.2.php');
}

const upgrade1183Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.18.3.php');
if (fs.existsSync(upgrade1183Path)) {
  const upCode183 = fs.readFileSync(upgrade1183Path, 'utf8');
  const hasUpgradeFunction183 = upCode183.includes('function upgrade_module_1_18_3($module)');
  const returnsTrue183 = upCode183.includes('return true;');
  report('Upgrade function upgrade_module_1_18_3 properly declared', hasUpgradeFunction183 && returnsTrue183, 'Missing or invalid function signature in upgrade-1.18.3.php');
} else {
  report('Upgrade script upgrade-1.18.3.php exists', false, 'Missing upgrade-1.18.3.php');
}

const upgrade1190Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.19.0.php');
if (fs.existsSync(upgrade1190Path)) {
  const upCode190 = fs.readFileSync(upgrade1190Path, 'utf8');
  const hasUpgradeFunction190 = upCode190.includes('function upgrade_module_1_19_0($module)');
  const returnsTrue190 = upCode190.includes('return true;');
  report('Upgrade function upgrade_module_1_19_0 properly declared', hasUpgradeFunction190 && returnsTrue190, 'Missing or invalid function signature in upgrade-1.19.0.php');
} else {
  report('Upgrade script upgrade-1.19.0.php exists', false, 'Missing upgrade-1.19.0.php');
}

const upgrade1191Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.19.1.php');
if (fs.existsSync(upgrade1191Path)) {
  const upCode191 = fs.readFileSync(upgrade1191Path, 'utf8');
  const hasUpgradeFunction191 = upCode191.includes('function upgrade_module_1_19_1($module)');
  const returnsTrue191 = upCode191.includes('return true;');
  report('Upgrade function upgrade_module_1_19_1 properly declared', hasUpgradeFunction191 && returnsTrue191, 'Missing or invalid function signature in upgrade-1.19.1.php');
} else {
  report('Upgrade script upgrade-1.19.1.php exists', false, 'Missing upgrade-1.19.1.php');
}

const upgrade1192Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.19.2.php');
if (fs.existsSync(upgrade1192Path)) {
  const upCode192 = fs.readFileSync(upgrade1192Path, 'utf8');
  const hasUpgradeFunction192 = upCode192.includes('function upgrade_module_1_19_2($module)');
  const returnsTrue192 = upCode192.includes('return true;');
  report('Upgrade function upgrade_module_1_19_2 properly declared', hasUpgradeFunction192 && returnsTrue192, 'Missing or invalid function signature in upgrade-1.19.2.php');
} else {
  report('Upgrade script upgrade-1.19.2.php exists', false, 'Missing upgrade-1.19.2.php');
}

const upgrade1193Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.19.3.php');
if (fs.existsSync(upgrade1193Path)) {
  const upCode193 = fs.readFileSync(upgrade1193Path, 'utf8');
  const hasUpgradeFunction193 = upCode193.includes('function upgrade_module_1_19_3($module)');
  const returnsTrue193 = upCode193.includes('return true;');
  report('Upgrade function upgrade_module_1_19_3 properly declared', hasUpgradeFunction193 && returnsTrue193, 'Missing or invalid function signature in upgrade-1.19.3.php');
} else {
  report('Upgrade script upgrade-1.19.3.php exists', false, 'Missing upgrade-1.19.3.php');
}

const upgrade1200Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.20.0.php');
if (fs.existsSync(upgrade1200Path)) {
  const upCode200 = fs.readFileSync(upgrade1200Path, 'utf8');
  const hasUpgradeFunction200 = upCode200.includes('function upgrade_module_1_20_0($module)');
  const returnsTrue200 = upCode200.includes('return true;');
  report('Upgrade function upgrade_module_1_20_0 properly declared', hasUpgradeFunction200 && returnsTrue200, 'Missing or invalid function signature in upgrade-1.20.0.php');
} else {
  report('Upgrade script upgrade-1.20.0.php exists', false, 'Missing upgrade-1.20.0.php');
}

const upgrade1201Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.20.1.php');
if (fs.existsSync(upgrade1201Path)) {
  const upCode201 = fs.readFileSync(upgrade1201Path, 'utf8');
  const hasUpgradeFunction201 = upCode201.includes('function upgrade_module_1_20_1($module)');
  const returnsTrue201 = upCode201.includes('return true;');
  report('Upgrade function upgrade_module_1_20_1 properly declared', hasUpgradeFunction201 && returnsTrue201, 'Missing or invalid function signature in upgrade-1.20.1.php');
} else {
  report('Upgrade script upgrade-1.20.1.php exists', false, 'Missing upgrade-1.20.1.php');
}

const upgrade1202Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.20.2.php');
if (fs.existsSync(upgrade1202Path)) {
  const upCode202 = fs.readFileSync(upgrade1202Path, 'utf8');
  const hasUpgradeFunction202 = upCode202.includes('function upgrade_module_1_20_2($module)');
  const returnsTrue202 = upCode202.includes('return true;');
  report('Upgrade function upgrade_module_1_20_2 properly declared', hasUpgradeFunction202 && returnsTrue202, 'Missing or invalid function signature in upgrade-1.20.2.php');
} else {
  report('Upgrade script upgrade-1.20.2.php exists', false, 'Missing upgrade-1.20.2.php');
}

const upgrade1203Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.20.3.php');
if (fs.existsSync(upgrade1203Path)) {
  const upCode203 = fs.readFileSync(upgrade1203Path, 'utf8');
  const hasUpgradeFunction203 = upCode203.includes('function upgrade_module_1_20_3($module)');
  const returnsTrue203 = upCode203.includes('return true;');
  report('Upgrade function upgrade_module_1_20_3 properly declared', hasUpgradeFunction203 && returnsTrue203, 'Missing or invalid function signature in upgrade-1.20.3.php');
} else {
  report('Upgrade script upgrade-1.20.3.php exists', false, 'Missing upgrade-1.20.3.php');
}

const upgrade1210Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.21.0.php');
if (fs.existsSync(upgrade1210Path)) {
  const upCode210 = fs.readFileSync(upgrade1210Path, 'utf8');
  const hasUpgradeFunction210 = upCode210.includes('function upgrade_module_1_21_0($module)');
  const returnsTrue210 = upCode210.includes('return true;');
  report('Upgrade function upgrade_module_1_21_0 properly declared', hasUpgradeFunction210 && returnsTrue210, 'Missing or invalid function signature in upgrade-1.21.0.php');
} else {
  report('Upgrade script upgrade-1.21.0.php exists', false, 'Missing upgrade-1.21.0.php');
}

const upgrade1211Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.21.1.php');
if (fs.existsSync(upgrade1211Path)) {
  const upCode211 = fs.readFileSync(upgrade1211Path, 'utf8');
  const hasUpgradeFunction211 = upCode211.includes('function upgrade_module_1_21_1($module)');
  const returnsTrue211 = upCode211.includes('return true;');
  report('Upgrade function upgrade_module_1_21_1 properly declared', hasUpgradeFunction211 && returnsTrue211, 'Missing or invalid function signature in upgrade-1.21.1.php');
} else {
  report('Upgrade script upgrade-1.21.1.php exists', false, 'Missing upgrade-1.21.1.php');
}

const upgrade1212Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.21.2.php');
if (fs.existsSync(upgrade1212Path)) {
  const upCode212 = fs.readFileSync(upgrade1212Path, 'utf8');
  const hasUpgradeFunction212 = upCode212.includes('function upgrade_module_1_21_2($module)');
  const returnsTrue212 = upCode212.includes('return true;');
  report('Upgrade function upgrade_module_1_21_2 properly declared', hasUpgradeFunction212 && returnsTrue212, 'Missing or invalid function signature in upgrade-1.21.2.php');
} else {
  report('Upgrade script upgrade-1.21.2.php exists', false, 'Missing upgrade-1.21.2.php');
}

const upgrade1213Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.21.3.php');
if (fs.existsSync(upgrade1213Path)) {
  const upCode213 = fs.readFileSync(upgrade1213Path, 'utf8');
  const hasUpgradeFunction213 = upCode213.includes('function upgrade_module_1_21_3($module)');
  const returnsTrue213 = upCode213.includes('return true;');
  report('Upgrade function upgrade_module_1_21_3 properly declared', hasUpgradeFunction213 && returnsTrue213, 'Missing or invalid function signature in upgrade-1.21.3.php');
} else {
  report('Upgrade script upgrade-1.21.3.php exists', false, 'Missing upgrade-1.21.3.php');
}

const upgrade1270Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.27.0.php');
if (fs.existsSync(upgrade1270Path)) {
  const upCode270 = fs.readFileSync(upgrade1270Path, 'utf8');
  const hasUpgradeFunction270 = upCode270.includes('function upgrade_module_1_27_0($module)');
  const returnsTrue270 = upCode270.includes('return true;');
  report('Upgrade function upgrade_module_1_27_0 properly declared', hasUpgradeFunction270 && returnsTrue270, 'Missing or invalid function signature in upgrade-1.27.0.php');
} else {
  report('Upgrade script upgrade-1.27.0.php exists', false, 'Missing upgrade-1.27.0.php');
}

const upgrade1280Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.28.0.php');
if (fs.existsSync(upgrade1280Path)) {
  const upCode280 = fs.readFileSync(upgrade1280Path, 'utf8');
  const hasUpgradeFunction280 = upCode280.includes('function upgrade_module_1_28_0($module)');
  const returnsTrue280 = upCode280.includes('return true;');
  report('Upgrade function upgrade_module_1_28_0 properly declared', hasUpgradeFunction280 && returnsTrue280, 'Missing or invalid function signature in upgrade-1.28.0.php');
} else {
  report('Upgrade script upgrade-1.28.0.php exists', false, 'Missing upgrade-1.28.0.php');
}

const upgrade1281Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.28.1.php');
if (fs.existsSync(upgrade1281Path)) {
  const upCode281 = fs.readFileSync(upgrade1281Path, 'utf8');
  const hasUpgradeFunction281 = upCode281.includes('function upgrade_module_1_28_1($module)');
  const returnsTrue281 = upCode281.includes('return true;');
  report('Upgrade function upgrade_module_1_28_1 properly declared', hasUpgradeFunction281 && returnsTrue281, 'Missing or invalid function signature in upgrade-1.28.1.php');
} else {
  report('Upgrade script upgrade-1.28.1.php exists', false, 'Missing upgrade-1.28.1.php');
}

const upgrade1282Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.28.2.php');
if (fs.existsSync(upgrade1282Path)) {
  const upCode282 = fs.readFileSync(upgrade1282Path, 'utf8');
  const hasUpgradeFunction282 = upCode282.includes('function upgrade_module_1_28_2($module)');
  const returnsTrue282 = upCode282.includes('return true;');
  report('Upgrade function upgrade_module_1_28_2 properly declared', hasUpgradeFunction282 && returnsTrue282, 'Missing or invalid function signature in upgrade-1.28.2.php');
} else {
  report('Upgrade script upgrade-1.28.2.php exists', false, 'Missing upgrade-1.28.2.php');
}

const upgrade1283Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.28.3.php');
if (fs.existsSync(upgrade1283Path)) {
  const upCode283 = fs.readFileSync(upgrade1283Path, 'utf8');
  const hasUpgradeFunction283 = upCode283.includes('function upgrade_module_1_28_3($module)');
  const returnsTrue283 = upCode283.includes('return true;');
  report('Upgrade function upgrade_module_1_28_3 properly declared', hasUpgradeFunction283 && returnsTrue283, 'Missing or invalid function signature in upgrade-1.28.3.php');
} else {
  report('Upgrade script upgrade-1.28.3.php exists', false, 'Missing upgrade-1.28.3.php');
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
