___TERMS_OF_SERVICE___

By creating or modifying this file you agree to Google Tag Manager's Community
Template Gallery Developer Terms of Service available at
https://developers.google.com/tag-manager/gallery-tos (or such other URL as
Google may provide), as modified from time to time.


___INFO___

{
  "type": "CLIENT",
  "id": "prestashop_webhook_client",
  "version": 1,
  "securityGroups": [],
  "displayName": "PrestaShop Webhook Client",
  "brand": {
    "id": "prestashop_brand",
    "displayName": "PrestaShop DataLayer"
  },
  "description": "Enterprise Server-to-Server Webhook Client for PrestaShop. Natively maps order status updates, offline purchases, and refunds directly into Common Event Data for GA4, Meta CAPI, and Google Ads.",
  "containerContexts": [
    "SERVER"
  ]
}


___TEMPLATE_PARAMETERS___

[
  {
    "type": "TEXT",
    "name": "path",
    "displayName": "Default Claimed Path",
    "simpleValueType": true,
    "defaultValue": "/ps-webhook",
    "help": "The primary HTTP path that this client claims for incoming PrestaShop webhooks. Default is /ps-webhook."
  },
  {
    "type": "SIMPLE_TABLE",
    "name": "additionalPaths",
    "displayName": "Additional Claimed Paths",
    "columns": [
      {
        "name": "path",
        "displayName": "Path",
        "type": "TEXT"
      }
    ],
    "help": "Optional additional paths that will be claimed by this client (e.g. /prestashop/webhook, /data)."
  },
  {
    "type": "CHECKBOX",
    "name": "enableCors",
    "checkboxText": "Enable CORS Headers",
    "simpleValueType": true,
    "defaultValue": true,
    "help": "Automatically responds to OPTIONS preflight requests and adds CORS response headers."
  }
]


___SANDBOXED_JS_FOR_SERVER___

const claimRequest = require('claimRequest');
const getRequestMethod = require('getRequestMethod');
const getRequestPath = require('getRequestPath');
const getRequestHeader = require('getRequestHeader');
const getRequestBody = require('getRequestBody');
const getRemoteAddress = require('getRemoteAddress');
const JSON = require('JSON');
const runContainer = require('runContainer');
const setResponseStatus = require('setResponseStatus');
const setResponseBody = require('setResponseBody');
const setResponseHeader = require('setResponseHeader');
const returnResponse = require('returnResponse');
const getTimestampMillis = require('getTimestampMillis');
const generateRandom = require('generateRandom');

const method = getRequestMethod();
const path = getRequestPath();

// Check if request path matches claimed paths
const defaultPath = data.path || '/ps-webhook';
let isClaimed = (path === defaultPath || path === '/ps-webhook' || path === '/prestashop/webhook');

if (!isClaimed && data.additionalPaths) {
  for (let i = 0; i < data.additionalPaths.length; i++) {
    if (path === data.additionalPaths[i].path) {
      isClaimed = true;
      break;
    }
  }
}

if (!isClaimed) {
  return;
}

// Claim incoming request
claimRequest();

// Handle CORS Preflight
if (method === 'OPTIONS') {
  setResponseStatus(200);
  setResponseHeader('Access-Control-Allow-Origin', '*');
  setResponseHeader('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  setResponseHeader('Access-Control-Allow-Headers', 'content-type,x-gtm-server-preview,x-prestashop-source,x-event-type');
  returnResponse();
  return;
}

// Parse request body
const rawBody = getRequestBody();
let payload = {};

if (rawBody) {
  const parsed = JSON.parse(rawBody);
  if (parsed) {
    payload = parsed;
  }
}

// Extract parameters and event name
let eventName = 'order_status_update';
let params = payload;

if (payload.events && payload.events.length > 0) {
  const firstEvent = payload.events[0];
  eventName = firstEvent.name || eventName;
  params = firstEvent.params || {};
} else if (payload.event_name) {
  eventName = payload.event_name;
} else if (payload.event) {
  eventName = payload.event;
} else {
  const headerEvent = getRequestHeader('x-event-type');
  if (headerEvent) {
    eventName = headerEvent;
  }
}

// Canonical GA4 Event Mapping: Map refund transitions to official 'refund' event name
const originalEventName = eventName;
const isRefundTransition = (params.status_transition_type === 'refund_issued' || params.is_refunded === true || eventName === 'refund');
if (isRefundTransition) {
  eventName = 'refund';
}

const timestamp = getTimestampMillis();
const uniqueEventId = '' + timestamp + '_' + generateRandom(100000000, 999999999);
const clientId = payload.client_id || params.client_id || ('ps_' + timestamp + '.' + generateRandom(1000, 9999));

// Initialize Common Event Data
const eventData = {
  event_name: eventName,
  original_event_name: originalEventName,
  client_id: clientId,
  unique_event_id: uniqueEventId,
  timestamp: timestamp,
  ip_override: getRemoteAddress(),
  user_agent: getRequestHeader('user-agent') || 'PrestaShop Server',
  page_location: params.page_location || 'https://' + (getRequestHeader('host') || 'prestashop.local') + '/backoffice',
};

// Map transaction and financials
if (params.transaction_id !== undefined) eventData.transaction_id = '' + params.transaction_id;
if (params.order_reference !== undefined) eventData.order_reference = '' + params.order_reference;
if (params.currency !== undefined) eventData.currency = '' + params.currency;
if (params.value !== undefined) eventData.value = params.value;
if (params.tax !== undefined) eventData.tax = params.tax;
if (params.shipping !== undefined) eventData.shipping = params.shipping;
if (params.coupon !== undefined) eventData.coupon = '' + params.coupon;
if (params.payment_method !== undefined) eventData.payment_method = '' + params.payment_method;

// Map PrestaShop status transitions
if (params.status_transition_type !== undefined) eventData.status_transition_type = params.status_transition_type;
if (params.conversion_action !== undefined) eventData.conversion_action = params.conversion_action;
if (params.new_status_id !== undefined) eventData.new_status_id = params.new_status_id;
if (params.new_status_name !== undefined) eventData.new_status_name = params.new_status_name;
if (params.previous_status_id !== undefined) eventData.previous_status_id = params.previous_status_id;
if (params.previous_status_name !== undefined) eventData.previous_status_name = params.previous_status_name;
if (params.is_paid !== undefined) eventData.is_paid = params.is_paid;
if (params.previous_is_paid !== undefined) eventData.previous_is_paid = params.previous_is_paid;
if (params.is_shipped !== undefined) eventData.is_shipped = params.is_shipped;
if (params.is_canceled !== undefined) eventData.is_canceled = params.is_canceled;
if (params.is_refunded !== undefined) eventData.is_refunded = params.is_refunded;

// Map Items array
if (params.items && params.items.length) {
  eventData.items = [];
  for (let i = 0; i < params.items.length; i++) {
    const it = params.items[i];
    const itemObj = {
      item_id: '' + (it.item_id || ''),
      item_name: '' + (it.item_name || ''),
      price: it.price !== undefined ? it.price : 0,
      quantity: it.quantity !== undefined ? it.quantity : 1,
    };
    if (it.item_brand) itemObj.item_brand = '' + it.item_brand;
    if (it.item_category) itemObj.item_category = '' + it.item_category;
    if (it.item_variant) itemObj.item_variant = '' + it.item_variant;
    eventData.items.push(itemObj);
  }
}

// Map User Data
const userDataSrc = params.user_data || payload.user_data;
if (userDataSrc) {
  const userData = {};
  if (userDataSrc.email_address) userData.email_address = userDataSrc.email_address;
  if (userDataSrc.email) userData.email = userDataSrc.email;
  if (userDataSrc.phone_number) userData.phone_number = userDataSrc.phone_number;
  if (userDataSrc.phone) userData.phone = userDataSrc.phone;
  if (userDataSrc.address) {
    userData.address = {};
    if (userDataSrc.address.first_name) userData.address.first_name = userDataSrc.address.first_name;
    if (userDataSrc.address.last_name) userData.address.last_name = userDataSrc.address.last_name;
    if (userDataSrc.address.street) userData.address.street = userDataSrc.address.street;
    if (userDataSrc.address.city) userData.address.city = userDataSrc.address.city;
    if (userDataSrc.address.region) userData.address.region = userDataSrc.address.region;
    if (userDataSrc.address.postal_code) userData.address.postal_code = userDataSrc.address.postal_code;
    if (userDataSrc.address.country) userData.address.country = userDataSrc.address.country;
  }
  eventData.user_data = userData;
}

// Map Universal Advertising Attribution & Ad Click IDs
const attrSource = params.attribution || (params.marketing_attribution && params.marketing_attribution.click_ids) || {};
const clickIdKeys = ['gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'sccid', 'msclkid', 'oppref', 'epik', 'li_fat_id', 'rdt_cid', 'twclid', 'dclid'];
for (let i = 0; i < clickIdKeys.length; i++) {
  const ck = clickIdKeys[i];
  const cval = params[ck] || attrSource[ck];
  if (cval !== undefined && cval !== null && cval !== '') {
    eventData[ck] = '' + cval;
  }
}

// Map First-Party Browser IDs & Cookies
const fbpVal = params.fbp || attrSource.fbp || (params.marketing_attribution && params.marketing_attribution.browser_cookies && params.marketing_attribution.browser_cookies.fbp);
if (fbpVal) {
  eventData.fbp = '' + fbpVal;
  eventData['x-fb-ck-fbp'] = '' + fbpVal;
}
const fbcVal = params.fbc || attrSource.fbc || (params.marketing_attribution && params.marketing_attribution.browser_cookies && params.marketing_attribution.browser_cookies.fbc);
if (fbcVal) {
  eventData.fbc = '' + fbcVal;
  eventData['x-fb-ck-fbc'] = '' + fbcVal;
}
const ttpVal = params.ttp || attrSource.ttp || (params.marketing_attribution && params.marketing_attribution.browser_cookies && params.marketing_attribution.browser_cookies.ttp);
if (ttpVal) eventData.ttp = '' + ttpVal;
const scidVal = params.scid || attrSource.scid || (params.marketing_attribution && params.marketing_attribution.browser_cookies && params.marketing_attribution.browser_cookies.scid);
if (scidVal) eventData.scid = '' + scidVal;
const uetvidVal = params.uetvid || attrSource.uetvid || (params.marketing_attribution && params.marketing_attribution.browser_cookies && params.marketing_attribution.browser_cookies.uetvid);
if (uetvidVal) eventData.uetvid = '' + uetvidVal;
const uetsidVal = params.uetsid || attrSource.uetsid || (params.marketing_attribution && params.marketing_attribution.browser_cookies && params.marketing_attribution.browser_cookies.uetsid);
if (uetsidVal) eventData.uetsid = '' + uetsidVal;

// Map Analytics Identifiers
const gaClientId = params.ga_client_id || attrSource.ga_client_id || (params.marketing_attribution && params.marketing_attribution.analytics_identifiers && params.marketing_attribution.analytics_identifiers.client_id);
if (gaClientId) {
  eventData.ga_client_id = '' + gaClientId;
  eventData['x-ga-client_id'] = '' + gaClientId;
}
const gaSessionId = params.session_id || params.ga_session_id || attrSource.ga_session_id || (params.marketing_attribution && params.marketing_attribution.analytics_identifiers && params.marketing_attribution.analytics_identifiers.session_id);
if (gaSessionId) {
  eventData.session_id = '' + gaSessionId;
  eventData['x-ga-session_id'] = '' + gaSessionId;
}

// Map UTM Campaign parameters
const utmSource = params.attribution || (params.marketing_attribution && params.marketing_attribution.utm_parameters) || {};
const utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
for (let i = 0; i < utmKeys.length; i++) {
  const uk = utmKeys[i];
  const uval = params[uk] || utmSource[uk] || attrSource[uk];
  if (uval !== undefined && uval !== null && uval !== '') {
    eventData[uk] = '' + uval;
  }
}
if (eventData.utm_source) eventData.campaign_source = eventData.utm_source;
if (eventData.utm_medium) eventData.campaign_medium = eventData.utm_medium;
if (eventData.utm_campaign) eventData.campaign_name = eventData.utm_campaign;
if (eventData.utm_content) eventData.campaign_content = eventData.utm_content;
if (eventData.utm_term) eventData.campaign_term = eventData.utm_term;

// Map Status Change Context
if (params.status_change_context && typeof params.status_change_context === 'object') {
  eventData.status_change_context = params.status_change_context;
}

// Set response headers and execute container tags
setResponseHeader('Content-Type', 'application/json');
setResponseHeader('Access-Control-Allow-Origin', '*');
setResponseHeader('Access-Control-Allow-Credentials', 'true');

runContainer(eventData, () => {
  setResponseStatus(200);
  setResponseBody(JSON.stringify({
    success: true,
    event: eventName,
    unique_event_id: uniqueEventId
  }));
  returnResponse();
});


___SERVER_PERMISSIONS___

[
  {
    "instance": {
      "key": {
        "publicId": "return_response",
        "versionId": "1"
      },
      "param": []
    },
    "isRequired": true
  },
  {
    "instance": {
      "key": {
        "publicId": "access_response",
        "versionId": "1"
      },
      "param": [
        {
          "key": "writeResponseAccess",
          "value": {
            "type": 1,
            "string": "any"
          }
        },
        {
          "key": "writeHeaderAccess",
          "value": {
            "type": 1,
            "string": "any"
          }
        }
      ]
    },
    "isRequired": true
  },
  {
    "instance": {
      "key": {
        "publicId": "run_container",
        "versionId": "1"
      },
      "param": []
    },
    "isRequired": true
  },
  {
    "instance": {
      "key": {
        "publicId": "read_request",
        "versionId": "1"
      },
      "param": [
        {
          "key": "requestAccess",
          "value": {
            "type": 1,
            "string": "any"
          }
        },
        {
          "key": "headerAccess",
          "value": {
            "type": 1,
            "string": "any"
          }
        },
        {
          "key": "queryParameterAccess",
          "value": {
            "type": 1,
            "string": "any"
          }
        }
      ]
    },
    "isRequired": true
  }
]


___TESTS___

scenarios: []
