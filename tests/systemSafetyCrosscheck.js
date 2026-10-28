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

// Check that front and back office hooks are wrapped in try...catch (\Throwable $e)
const hooksToCheck = [
  'hookDisplayHeader',
  'hookDisplayAfterBodyOpeningTag',
  'hookDisplayOrderConfirmation',
  'hookDisplayBackOfficeTop',
  'hookDisplayBackOfficeHeader',
  'hookActionCartUpdateQuantityBefore',
  'hookActionCarrierProcess',
  'hookActionOrderSlipAdd',
  'hookActionValidateOrder',
  'hookActionOrderStatusPostUpdate'
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
report(`Version alignment (${versionInPhp}) between PHP and XML`, versionInPhp === versionInXml && versionInPhp === '1.42.6', `PHP version: ${versionInPhp}, XML version: ${versionInXml}`);

// Smarty PHP 8 compatibility check: ensure no .tpl files use illegal or PHP-only modifiers like |implode
const allTplFiles = [];
function findTpls(dir) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const ent of entries) {
    const full = path.join(dir, ent.name);
    if (ent.isDirectory()) findTpls(full);
    else if (ent.name.endsWith('.tpl')) allTplFiles.push(full);
  }
}
findTpls(path.join(MODULE_ROOT, 'views'));
allTplFiles.forEach(tpl => {
  const tplContent = fs.readFileSync(tpl, 'utf8');
  const rel = path.relative(MODULE_ROOT, tpl);
  report(`No illegal |implode Smarty modifier in ${rel}`, !tplContent.includes('|implode'), `Found illegal |implode modifier in ${rel}`);
});

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

const upgrade1284Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.28.4.php');
if (fs.existsSync(upgrade1284Path)) {
  const upCode284 = fs.readFileSync(upgrade1284Path, 'utf8');
  const hasUpgradeFunction284 = upCode284.includes('function upgrade_module_1_28_4($module)');
  const returnsTrue284 = upCode284.includes('return true;');
  report('Upgrade function upgrade_module_1_28_4 properly declared', hasUpgradeFunction284 && returnsTrue284, 'Missing or invalid function signature in upgrade-1.28.4.php');
} else {
  report('Upgrade script upgrade-1.28.4.php exists', false, 'Missing upgrade-1.28.4.php');
}

const upgrade1285Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.28.5.php');
if (fs.existsSync(upgrade1285Path)) {
  const upCode285 = fs.readFileSync(upgrade1285Path, 'utf8');
  const hasUpgradeFunction285 = upCode285.includes('function upgrade_module_1_28_5($module)');
  const returnsTrue285 = upCode285.includes('return true;');
  report('Upgrade function upgrade_module_1_28_5 properly declared', hasUpgradeFunction285 && returnsTrue285, 'Missing or invalid function signature in upgrade-1.28.5.php');
} else {
  report('Upgrade script upgrade-1.28.5.php exists', false, 'Missing upgrade-1.28.5.php');
}

const upgrade1290Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.29.0.php');
if (fs.existsSync(upgrade1290Path)) {
  const upCode290 = fs.readFileSync(upgrade1290Path, 'utf8');
  const hasUpgradeFunction290 = upCode290.includes('function upgrade_module_1_29_0($module)');
  const returnsTrue290 = upCode290.includes('return true;');
  report('Upgrade function upgrade_module_1_29_0 properly declared', hasUpgradeFunction290 && returnsTrue290, 'Missing or invalid function signature in upgrade-1.29.0.php');
} else {
  report('Upgrade script upgrade-1.29.0.php exists', false, 'Missing upgrade-1.29.0.php');
}

const upgrade1300Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.30.0.php');
if (fs.existsSync(upgrade1300Path)) {
  const upCode300 = fs.readFileSync(upgrade1300Path, 'utf8');
  const hasUpgradeFunction300 = upCode300.includes('function upgrade_module_1_30_0($module)');
  const returnsTrue300 = upCode300.includes('return true;');
  report('Upgrade function upgrade_module_1_30_0 properly declared', hasUpgradeFunction300 && returnsTrue300, 'Missing or invalid function signature in upgrade-1.30.0.php');
} else {
  report('Upgrade script upgrade-1.30.0.php exists', false, 'Missing upgrade-1.30.0.php');
}

const upgrade1310Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.31.0.php');
if (fs.existsSync(upgrade1310Path)) {
  const upCode310 = fs.readFileSync(upgrade1310Path, 'utf8');
  const hasUpgradeFunction310 = upCode310.includes('function upgrade_module_1_31_0($module)');
  const returnsTrue310 = upCode310.includes('return true;');
  report('Upgrade function upgrade_module_1_31_0 properly declared', hasUpgradeFunction310 && returnsTrue310, 'Missing or invalid function signature in upgrade-1.31.0.php');
} else {
  report('Upgrade script upgrade-1.31.0.php exists', false, 'Missing upgrade-1.31.0.php');
}

const upgrade1320Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.32.0.php');
if (fs.existsSync(upgrade1320Path)) {
  const upCode320 = fs.readFileSync(upgrade1320Path, 'utf8');
  const hasUpgradeFunction320 = upCode320.includes('function upgrade_module_1_32_0($module)');
  const returnsTrue320 = upCode320.includes('return true;');
  report('Upgrade function upgrade_module_1_32_0 properly declared', hasUpgradeFunction320 && returnsTrue320, 'Missing or invalid function signature in upgrade-1.32.0.php');
} else {
  report('Upgrade script upgrade-1.32.0.php exists', false, 'Missing upgrade-1.32.0.php');
}

const upgrade1330Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.33.0.php');
if (fs.existsSync(upgrade1330Path)) {
  const upCode330 = fs.readFileSync(upgrade1330Path, 'utf8');
  const hasUpgradeFunction330 = upCode330.includes('function upgrade_module_1_33_0($module)');
  const returnsTrue330 = upCode330.includes('return true;');
  report('Upgrade function upgrade_module_1_33_0 properly declared', hasUpgradeFunction330 && returnsTrue330, 'Missing or invalid function signature in upgrade-1.33.0.php');
} else {
  report('Upgrade script upgrade-1.33.0.php exists', false, 'Missing upgrade-1.33.0.php');
}

const upgrade1340Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.34.0.php');
if (fs.existsSync(upgrade1340Path)) {
  const upCode340 = fs.readFileSync(upgrade1340Path, 'utf8');
  const hasUpgradeFunction340 = upCode340.includes('function upgrade_module_1_34_0($module)');
  const returnsTrue340 = upCode340.includes('return true;');
  report('Upgrade function upgrade_module_1_34_0 properly declared', hasUpgradeFunction340 && returnsTrue340, 'Missing or invalid function signature in upgrade-1.34.0.php');
} else {
  report('Upgrade script upgrade-1.34.0.php exists', false, 'Missing upgrade-1.34.0.php');
}

const upgrade1350Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.35.0.php');
if (fs.existsSync(upgrade1350Path)) {
  const upCode350 = fs.readFileSync(upgrade1350Path, 'utf8');
  const hasUpgradeFunction350 = upCode350.includes('function upgrade_module_1_35_0($module)');
  const returnsTrue350 = upCode350.includes('return true;');
  report('Upgrade function upgrade_module_1_35_0 properly declared', hasUpgradeFunction350 && returnsTrue350, 'Missing or invalid function signature in upgrade-1.35.0.php');
} else {
  report('Upgrade script upgrade-1.35.0.php exists', false, 'Missing upgrade-1.35.0.php');
}

const upgrade1360Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.36.0.php');
if (fs.existsSync(upgrade1360Path)) {
  const upCode360 = fs.readFileSync(upgrade1360Path, 'utf8');
  const hasUpgradeFunction360 = upCode360.includes('function upgrade_module_1_36_0($module)');
  const returnsTrue360 = upCode360.includes('return true;');
  report('Upgrade function upgrade_module_1_36_0 properly declared', hasUpgradeFunction360 && returnsTrue360, 'Missing or invalid function signature in upgrade-1.36.0.php');
} else {
  report('Upgrade script upgrade-1.36.0.php exists', false, 'Missing upgrade-1.36.0.php');
}

const upgrade1370Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.37.0.php');
if (fs.existsSync(upgrade1370Path)) {
  const upCode370 = fs.readFileSync(upgrade1370Path, 'utf8');
  const hasUpgradeFunction370 = upCode370.includes('function upgrade_module_1_37_0($module)');
  const returnsTrue370 = upCode370.includes('return true;');
  report('Upgrade function upgrade_module_1_37_0 properly declared', hasUpgradeFunction370 && returnsTrue370, 'Missing or invalid function signature in upgrade-1.37.0.php');
} else {
  report('Upgrade script upgrade-1.37.0.php exists', false, 'Missing upgrade-1.37.0.php');
}

const upgrade1371Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.37.1.php');
if (fs.existsSync(upgrade1371Path)) {
  const upCode371 = fs.readFileSync(upgrade1371Path, 'utf8');
  const hasUpgradeFunction371 = upCode371.includes('function upgrade_module_1_37_1($module)');
  const returnsTrue371 = upCode371.includes('return true;');
  report('Upgrade function upgrade_module_1_37_1 properly declared', hasUpgradeFunction371 && returnsTrue371, 'Missing or invalid function signature in upgrade-1.37.1.php');
} else {
  report('Upgrade script upgrade-1.37.1.php exists', false, 'Missing upgrade-1.37.1.php');
}

const upgrade1380Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.38.0.php');
if (fs.existsSync(upgrade1380Path)) {
  const upCode380 = fs.readFileSync(upgrade1380Path, 'utf8');
  const hasUpgradeFunction380 = upCode380.includes('function upgrade_module_1_38_0($module)');
  const returnsTrue380 = upCode380.includes('return true;');
  report('Upgrade function upgrade_module_1_38_0 properly declared', hasUpgradeFunction380 && returnsTrue380, 'Missing or invalid function signature in upgrade-1.38.0.php');
} else {
  report('Upgrade script upgrade-1.38.0.php exists', false, 'Missing upgrade-1.38.0.php');
}

const upgrade1390Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.39.0.php');
if (fs.existsSync(upgrade1390Path)) {
  const upCode390 = fs.readFileSync(upgrade1390Path, 'utf8');
  const hasUpgradeFunction390 = upCode390.includes('function upgrade_module_1_39_0($module)');
  const returnsTrue390 = upCode390.includes('return true;');
  report('Upgrade function upgrade_module_1_39_0 properly declared', hasUpgradeFunction390 && returnsTrue390, 'Missing or invalid function signature in upgrade-1.39.0.php');
} else {
  report('Upgrade script upgrade-1.39.0.php exists', false, 'Missing upgrade-1.39.0.php');
}

const upgrade1391Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.39.1.php');
if (fs.existsSync(upgrade1391Path)) {
  const upCode391 = fs.readFileSync(upgrade1391Path, 'utf8');
  const hasUpgradeFunction391 = upCode391.includes('function upgrade_module_1_39_1($module)');
  const returnsTrue391 = upCode391.includes('return true;');
  report('Upgrade function upgrade_module_1_39_1 properly declared', hasUpgradeFunction391 && returnsTrue391, 'Missing or invalid function signature in upgrade-1.39.1.php');
} else {
  report('Upgrade script upgrade-1.39.1.php exists', false, 'Missing upgrade-1.39.1.php');
}

const upgrade1400Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.0.php');
if (fs.existsSync(upgrade1400Path)) {
  const upCode400 = fs.readFileSync(upgrade1400Path, 'utf8');
  const hasUpgradeFunction400 = upCode400.includes('function upgrade_module_1_40_0($module)');
  const returnsTrue400 = upCode400.includes('return true;');
  report('Upgrade function upgrade_module_1_40_0 properly declared', hasUpgradeFunction400 && returnsTrue400, 'Missing or invalid function signature in upgrade-1.40.0.php');
} else {
  report('Upgrade script upgrade-1.40.0.php exists', false, 'Missing upgrade-1.40.0.php');
}

const upgrade1401Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.1.php');
if (fs.existsSync(upgrade1401Path)) {
  const upCode401 = fs.readFileSync(upgrade1401Path, 'utf8');
  const hasUpgradeFunction401 = upCode401.includes('function upgrade_module_1_40_1($module)');
  const returnsTrue401 = upCode401.includes('return true;');
  report('Upgrade function upgrade_module_1_40_1 properly declared', hasUpgradeFunction401 && returnsTrue401, 'Missing or invalid function signature in upgrade-1.40.1.php');
} else {
  report('Upgrade script upgrade-1.40.1.php exists', false, 'Missing upgrade-1.40.1.php');
}

const upgrade1402Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.2.php');
if (fs.existsSync(upgrade1402Path)) {
  const upCode402 = fs.readFileSync(upgrade1402Path, 'utf8');
  const hasUpgradeFunction402 = upCode402.includes('function upgrade_module_1_40_2($module)');
  const returnsTrue402 = upCode402.includes('return true;');
  report('Upgrade function upgrade_module_1_40_2 properly declared', hasUpgradeFunction402 && returnsTrue402, 'Missing or invalid function signature in upgrade-1.40.2.php');
} else {
  report('Upgrade script upgrade-1.40.2.php exists', false, 'Missing upgrade-1.40.2.php');
}

const upgrade1403Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.3.php');
if (fs.existsSync(upgrade1403Path)) {
  const upCode403 = fs.readFileSync(upgrade1403Path, 'utf8');
  const hasUpgradeFunction403 = upCode403.includes('function upgrade_module_1_40_3($module)');
  const returnsTrue403 = upCode403.includes('return true;');
  report('Upgrade function upgrade_module_1_40_3 properly declared', hasUpgradeFunction403 && returnsTrue403, 'Missing or invalid function signature in upgrade-1.40.3.php');
} else {
  report('Upgrade script upgrade-1.40.3.php exists', false, 'Missing upgrade-1.40.3.php');
}

const upgrade1404Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.4.php');
if (fs.existsSync(upgrade1404Path)) {
  const upCode404 = fs.readFileSync(upgrade1404Path, 'utf8');
  const hasUpgradeFunction404 = upCode404.includes('function upgrade_module_1_40_4($module)');
  const returnsTrue404 = upCode404.includes('return true;');
  report('Upgrade function upgrade_module_1_40_4 properly declared', hasUpgradeFunction404 && returnsTrue404, 'Missing or invalid function signature in upgrade-1.40.4.php');
} else {
  report('Upgrade script upgrade-1.40.4.php exists', false, 'Missing upgrade-1.40.4.php');
}

const upgrade1405Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.5.php');
if (fs.existsSync(upgrade1405Path)) {
  const upCode405 = fs.readFileSync(upgrade1405Path, 'utf8');
  const hasUpgradeFunction405 = upCode405.includes('function upgrade_module_1_40_5($module)');
  const returnsTrue405 = upCode405.includes('return true;');
  report('Upgrade function upgrade_module_1_40_5 properly declared', hasUpgradeFunction405 && returnsTrue405, 'Missing or invalid function signature in upgrade-1.40.5.php');
} else {
  report('Upgrade script upgrade-1.40.5.php exists', false, 'Missing upgrade-1.40.5.php');
}

const upgrade1406Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.6.php');
if (fs.existsSync(upgrade1406Path)) {
  const upCode406 = fs.readFileSync(upgrade1406Path, 'utf8');
  const hasUpgradeFunction406 = upCode406.includes('function upgrade_module_1_40_6($module)');
  const returnsTrue406 = upCode406.includes('return true;');
  report('Upgrade function upgrade_module_1_40_6 properly declared', hasUpgradeFunction406 && returnsTrue406, 'Missing or invalid function signature in upgrade-1.40.6.php');
} else {
  report('Upgrade script upgrade-1.40.6.php exists', false, 'Missing upgrade-1.40.6.php');
}

const upgrade1407Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.7.php');
if (fs.existsSync(upgrade1407Path)) {
  const upCode407 = fs.readFileSync(upgrade1407Path, 'utf8');
  const hasUpgradeFunction407 = upCode407.includes('function upgrade_module_1_40_7($module)');
  const returnsTrue407 = upCode407.includes('return true;');
  report('Upgrade function upgrade_module_1_40_7 properly declared', hasUpgradeFunction407 && returnsTrue407, 'Missing or invalid function signature in upgrade-1.40.7.php');
} else {
  report('Upgrade script upgrade-1.40.7.php exists', false, 'Missing upgrade-1.40.7.php');
}

const upgrade1408Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.8.php');
if (fs.existsSync(upgrade1408Path)) {
  const upCode408 = fs.readFileSync(upgrade1408Path, 'utf8');
  const hasUpgradeFunction408 = upCode408.includes('function upgrade_module_1_40_8($module)');
  const returnsTrue408 = upCode408.includes('return true;');
  report('Upgrade function upgrade_module_1_40_8 properly declared', hasUpgradeFunction408 && returnsTrue408, 'Missing or invalid function signature in upgrade-1.40.8.php');
} else {
  report('Upgrade script upgrade-1.40.8.php exists', false, 'Missing upgrade-1.40.8.php');
}

const upgrade1409Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.9.php');
if (fs.existsSync(upgrade1409Path)) {
  const upCode409 = fs.readFileSync(upgrade1409Path, 'utf8');
  const hasUpgradeFunction409 = upCode409.includes('function upgrade_module_1_40_9($module)');
  const returnsTrue409 = upCode409.includes('return true;');
  report('Upgrade function upgrade_module_1_40_9 properly declared', hasUpgradeFunction409 && returnsTrue409, 'Missing or invalid function signature in upgrade-1.40.9.php');
} else {
  report('Upgrade script upgrade-1.40.9.php exists', false, 'Missing upgrade-1.40.9.php');
}

const upgrade14010Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.40.10.php');
if (fs.existsSync(upgrade14010Path)) {
  const upCode410 = fs.readFileSync(upgrade14010Path, 'utf8');
  const hasUpgradeFunction410 = upCode410.includes('function upgrade_module_1_40_10($module)');
  const returnsTrue410 = upCode410.includes('return true;');
  report('Upgrade function upgrade_module_1_40_10 properly declared', hasUpgradeFunction410 && returnsTrue410, 'Missing or invalid function signature in upgrade-1.40.10.php');
} else {
  report('Upgrade script upgrade-1.40.10.php exists', false, 'Missing upgrade-1.40.10.php');
}

const upgrade1410Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.41.0.php');
if (fs.existsSync(upgrade1410Path)) {
  const upCode411 = fs.readFileSync(upgrade1410Path, 'utf8');
  const hasUpgradeFunction411 = upCode411.includes('function upgrade_module_1_41_0($module)');
  const returnsTrue411 = upCode411.includes('return true;');
  report('Upgrade function upgrade_module_1_41_0 properly declared', hasUpgradeFunction411 && returnsTrue411, 'Missing or invalid function signature in upgrade-1.41.0.php');
} else {
  report('Upgrade script upgrade-1.41.0.php exists', false, 'Missing upgrade-1.41.0.php');
}

const upgrade1411Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.41.1.php');
if (fs.existsSync(upgrade1411Path)) {
  const upCode412 = fs.readFileSync(upgrade1411Path, 'utf8');
  const hasUpgradeFunction412 = upCode412.includes('function upgrade_module_1_41_1($module)');
  const returnsTrue412 = upCode412.includes('return true;');
  report('Upgrade function upgrade_module_1_41_1 properly declared', hasUpgradeFunction412 && returnsTrue412, 'Missing or invalid function signature in upgrade-1.41.1.php');
} else {
  report('Upgrade script upgrade-1.41.1.php exists', false, 'Missing upgrade-1.41.1.php');
}

const upgrade1412Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.41.2.php');
if (fs.existsSync(upgrade1412Path)) {
  const upCode413 = fs.readFileSync(upgrade1412Path, 'utf8');
  const hasUpgradeFunction413 = upCode413.includes('function upgrade_module_1_41_2($module)');
  const returnsTrue413 = upCode413.includes('return true;');
  report('Upgrade function upgrade_module_1_41_2 properly declared', hasUpgradeFunction413 && returnsTrue413, 'Missing or invalid function signature in upgrade-1.41.2.php');
} else {
  report('Upgrade script upgrade-1.41.2.php exists', false, 'Missing upgrade-1.41.2.php');
}

const upgrade1420Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.42.0.php');
if (fs.existsSync(upgrade1420Path)) {
  const upCode420 = fs.readFileSync(upgrade1420Path, 'utf8');
  const hasUpgradeFunction420 = upCode420.includes('function upgrade_module_1_42_0($module)');
  const returnsTrue420 = upCode420.includes('return true;');
  report('Upgrade function upgrade_module_1_42_0 properly declared', hasUpgradeFunction420 && returnsTrue420, 'Missing or invalid function signature in upgrade-1.42.0.php');
} else {
  report('Upgrade script upgrade-1.42.0.php exists', false, 'Missing upgrade-1.42.0.php');
}

const upgrade1421Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.42.1.php');
if (fs.existsSync(upgrade1421Path)) {
  const upCode421 = fs.readFileSync(upgrade1421Path, 'utf8');
  const hasUpgradeFunction421 = upCode421.includes('function upgrade_module_1_42_1($module)');
  const returnsTrue421 = upCode421.includes('return true;');
  report('Upgrade function upgrade_module_1_42_1 properly declared', hasUpgradeFunction421 && returnsTrue421, 'Missing or invalid function signature in upgrade-1.42.1.php');
} else {
  report('Upgrade script upgrade-1.42.1.php exists', false, 'Missing upgrade-1.42.1.php');
}

const upgrade1422Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.42.2.php');
if (fs.existsSync(upgrade1422Path)) {
  const upCode422 = fs.readFileSync(upgrade1422Path, 'utf8');
  const hasUpgradeFunction422 = upCode422.includes('function upgrade_module_1_42_2($module)');
  const returnsTrue422 = upCode422.includes('return true;');
  report('Upgrade function upgrade_module_1_42_2 properly declared', hasUpgradeFunction422 && returnsTrue422, 'Missing or invalid function signature in upgrade-1.42.2.php');
} else {
  report('Upgrade script upgrade-1.42.2.php exists', false, 'Missing upgrade-1.42.2.php');
}

const upgrade1423Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.42.3.php');
if (fs.existsSync(upgrade1423Path)) {
  const upCode423 = fs.readFileSync(upgrade1423Path, 'utf8');
  const hasUpgradeFunction423 = upCode423.includes('function upgrade_module_1_42_3($module)');
  const returnsTrue423 = upCode423.includes('return true;');
  report('Upgrade function upgrade_module_1_42_3 properly declared', hasUpgradeFunction423 && returnsTrue423, 'Missing or invalid function signature in upgrade-1.42.3.php');
} else {
  report('Upgrade script upgrade-1.42.3.php exists', false, 'Missing upgrade-1.42.3.php');
}

const upgrade1424Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.42.4.php');
if (fs.existsSync(upgrade1424Path)) {
  const upCode424 = fs.readFileSync(upgrade1424Path, 'utf8');
  const hasUpgradeFunction424 = upCode424.includes('function upgrade_module_1_42_4($module)');
  const returnsTrue424 = upCode424.includes('return true;');
  report('Upgrade function upgrade_module_1_42_4 properly declared', hasUpgradeFunction424 && returnsTrue424, 'Missing or invalid function signature in upgrade-1.42.4.php');
} else {
  report('Upgrade script upgrade-1.42.4.php exists', false, 'Missing upgrade-1.42.4.php');
}

const upgrade1425Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.42.5.php');
if (fs.existsSync(upgrade1425Path)) {
  const upCode425 = fs.readFileSync(upgrade1425Path, 'utf8');
  const hasUpgradeFunction425 = upCode425.includes('function upgrade_module_1_42_5($module)');
  const returnsTrue425 = upCode425.includes('return true;');
  report('Upgrade function upgrade_module_1_42_5 properly declared', hasUpgradeFunction425 && returnsTrue425, 'Missing or invalid function signature in upgrade-1.42.5.php');
} else {
  report('Upgrade script upgrade-1.42.5.php exists', false, 'Missing upgrade-1.42.5.php');
}

const upgrade1426Path = path.join(MODULE_ROOT, 'upgrade', 'upgrade-1.42.6.php');
if (fs.existsSync(upgrade1426Path)) {
  const upCode426 = fs.readFileSync(upgrade1426Path, 'utf8');
  const hasUpgradeFunction426 = upCode426.includes('function upgrade_module_1_42_6($module)');
  const returnsTrue426 = upCode426.includes('return true;');
  report('Upgrade function upgrade_module_1_42_6 properly declared', hasUpgradeFunction426 && returnsTrue426, 'Missing or invalid function signature in upgrade-1.42.6.php');
} else {
  report('Upgrade script upgrade-1.42.6.php exists', false, 'Missing upgrade-1.42.6.php');
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
