// @ts-check
/* global process, fetch, setInterval, clearInterval, setTimeout, Date, JSON, Math, console */
"use strict";

/**
 * OpenClaw plugin: Platform Tools Bridge
 *
 * Fetches the tool catalog from Core's /api/v1/agents/discovery endpoint
 * and registers each tool so the LLM can invoke platform agents via A2A.
 *
 * Writes structured logs to OpenSearch so every request/response is visible
 * in the admin log viewer alongside PHP/Python application logs.
 */

const PLATFORM_CORE_URL = /** @type {string} */ (process.env.PLATFORM_CORE_URL) || "http://core";
const PLATFORM_TOKEN = /** @type {string} */ (process.env.OPENCLAW_GATEWAY_TOKEN) || "";
const OPENSEARCH_URL = /** @type {string} */ (process.env.OPENSEARCH_URL) || "";

const DISCOVERY_URL = `${PLATFORM_CORE_URL}/api/v1/agents/discovery`;
const INVOKE_URL = `${PLATFORM_CORE_URL}/api/v1/agents/invoke`;

const APP_NAME = "openclaw";
const INDEX_PREFIX = "platform_logs";

/** @typedef {{ info?: Function, warn?: Function, error?: Function, debug?: Function }} PluginLogger */

// ---------------------------------------------------------------------------
// OpenSearch direct logger
// ---------------------------------------------------------------------------

/** @type {Array<object>} */
let logBuffer = [];
const FLUSH_SIZE = 20;
const FLUSH_INTERVAL_MS = 5000;

/** @type {ReturnType<typeof setInterval> | null} */
let flushTimer = null;

/**
 * @param {'DEBUG'|'INFO'|'WARNING'|'ERROR'} levelName
 * @param {string} message
 * @param {object} [context]
 * @param {string} [traceId]
 * @param {string} [requestId]
 */
function osLog(levelName, message, context, traceId, requestId) {
  /** @type {Record<string, number>} */
  const levelValues = { DEBUG: 100, INFO: 200, WARNING: 300, ERROR: 400 };

  /** @type {Record<string, unknown>} */
  const doc = {
    "@timestamp": new Date().toISOString(),
    level: levelValues[levelName] || 200,
    level_name: levelName,
    message,
    channel: "openclaw",
    app_name: APP_NAME,
  };

  if (traceId) doc.trace_id = traceId;
  if (requestId) doc.request_id = requestId;
  if (context && Object.keys(context).length > 0) doc.context = context;

  logBuffer.push(doc);

  if (logBuffer.length >= FLUSH_SIZE) {
    flushLogs();
  }
}

function flushLogs() {
  if (logBuffer.length === 0 || !OPENSEARCH_URL) return;

  const indexName = `${INDEX_PREFIX}_${formatDate(new Date())}`;
  let body = "";

  for (const doc of logBuffer) {
    body += JSON.stringify({ index: { _index: indexName } }) + "\n";
    body += JSON.stringify(doc) + "\n";
  }

  logBuffer = [];

  const url = `${OPENSEARCH_URL.replace(/\/+$/, "")}/_bulk`;

  fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/x-ndjson" },
    body,
  }).catch(() => {
    // silent fail — same pattern as PHP OpenSearchHandler
  });
}

/** @param {Date} d */
function formatDate(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}_${m}_${day}`;
}

function startFlushTimer() {
  if (flushTimer) return;
  flushTimer = setInterval(flushLogs, FLUSH_INTERVAL_MS);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function generateId(prefix = "id") {
  return `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
}

/**
 * Truncate a value for logging (avoid giant payloads in logs).
 * @param {unknown} val
 * @param {number} [maxLen]
 * @returns {string}
 */
function truncate(val, maxLen = 500) {
  const str = typeof val === "string" ? val : JSON.stringify(val);
  if (!str) return "";
  return str.length > maxLen ? str.slice(0, maxLen) + "…" : str;
}

// ---------------------------------------------------------------------------
// Core API calls
// ---------------------------------------------------------------------------

/**
 * Fetch the tool catalog from Core.
 * @param {PluginLogger} log
 * @returns {Promise<Array<{name: string, agent: string, description: string, input_schema: object}>>}
 */
async function fetchDiscovery(log) {
  const requestId = generateId("req");

  osLog("INFO", "Discovery request started", { url: DISCOVERY_URL }, undefined, requestId);
  log.debug?.(`[platform-tools] Fetching discovery from ${DISCOVERY_URL}`);

  const start = Date.now();

  const res = await fetch(DISCOVERY_URL, {
    headers: { Authorization: `Bearer ${PLATFORM_TOKEN}` },
  });

  const durationMs = Date.now() - start;

  if (!res.ok) {
    osLog("ERROR", `Discovery request failed: ${res.status} ${res.statusText}`, {
      url: DISCOVERY_URL,
      http_status: res.status,
      duration_ms: durationMs,
    }, undefined, requestId);

    throw new Error(
      `Discovery request failed: ${res.status} ${res.statusText}`
    );
  }

  const data = await res.json();
  const tools = data.tools || [];

  osLog("INFO", `Discovery completed: ${tools.length} tool(s) found`, {
    url: DISCOVERY_URL,
    http_status: res.status,
    duration_ms: durationMs,
    tool_count: tools.length,
    tool_names: tools.map((/** @type {{name: string}} */ t) => t.name),
  }, undefined, requestId);

  log.debug?.(
    `[platform-tools] Discovery response parsed: ${tools.length} tool(s)`
  );
  return tools;
}

/**
 * Invoke a platform tool via Core's A2A bridge.
 * @param {string} tool
 * @param {object} input
 * @param {PluginLogger} log
 * @returns {Promise<object>}
 */
async function invokeTool(tool, input, log) {
  const traceId = generateId("trace");
  const requestId = generateId("req");

  osLog("INFO", `Invoke request: tool=${tool}`, {
    tool,
    url: INVOKE_URL,
    input_summary: truncate(input),
  }, traceId, requestId);

  log.debug?.(
    `[platform-tools] Sending invoke request: tool=${tool}, trace_id=${traceId}`
  );

  const start = Date.now();
  const res = await fetch(INVOKE_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${PLATFORM_TOKEN}`,
    },
    body: JSON.stringify({
      tool,
      input,
      trace_id: traceId,
      request_id: requestId,
    }),
  });

  const durationMs = Date.now() - start;

  if (!res.ok) {
    let responseBody = "";
    try {
      responseBody = await res.text();
    } catch (_e) {
      // ignore
    }

    osLog("ERROR", `Invoke failed: tool=${tool}, status=${res.status}`, {
      tool,
      url: INVOKE_URL,
      http_status: res.status,
      http_status_text: res.statusText,
      duration_ms: durationMs,
      response_body: truncate(responseBody, 1000),
    }, traceId, requestId);

    log.warn?.(
      `[platform-tools] Invoke failed: tool=${tool}, status=${res.status}, duration=${durationMs}ms`
    );
    throw new Error(`Invoke request failed: ${res.status} ${res.statusText}`);
  }

  const result = await res.json();
  const status = result.status || "unknown";

  osLog("INFO", `Invoke completed: tool=${tool}, status=${status}`, {
    tool,
    url: INVOKE_URL,
    http_status: res.status,
    duration_ms: durationMs,
    result_status: status,
    response_summary: truncate(result, 1000),
  }, traceId, requestId);

  log.info?.(
    `[platform-tools] Invoke completed: tool=${tool}, status=${status}, duration=${durationMs}ms`
  );
  return result;
}

/**
 * Convert a JSON Schema object to TypeBox-compatible parameter definition.
 * OpenClaw's registerTool expects a TypeBox schema, but we can pass
 * a raw JSON Schema object since TypeBox schemas are just plain objects.
 * @param {object} schema
 * @returns {object}
 */
function toToolParameters(schema) {
  if (!schema || typeof schema !== "object") {
    return { type: "object", properties: {} };
  }
  return schema;
}

// ---------------------------------------------------------------------------
// Plugin entry point
// ---------------------------------------------------------------------------

/** @param {import('./types').PluginAPI} api */
module.exports = function platformTools(api) {
  const log = api.log || console;

  startFlushTimer();

  osLog("INFO", "OpenClaw platform-tools plugin initializing", {
    core_url: PLATFORM_CORE_URL,
    opensearch_configured: !!OPENSEARCH_URL,
  });

  log.info?.("[platform-tools] Plugin initializing");

  fetchDiscovery(log)
    .then((tools) => {
      if (!tools.length) {
        osLog("WARNING", "No tools discovered from Core");
        log.info?.("[platform-tools] No tools discovered from Core");
        return;
      }

      log.info?.(
        `[platform-tools] Discovered ${tools.length} tool(s) from Core`
      );

      for (const tool of tools) {
        const toolName = tool.name.replace(/\./g, "_");

        api.registerTool({
          name: toolName,
          description: `[${tool.agent}] ${tool.description}`,
          parameters: toToolParameters(tool.input_schema),

          async execute(_id, params) {
            osLog("INFO", `Tool execute: ${tool.name} on agent ${tool.agent}`, {
              tool_name: tool.name,
              agent: tool.agent,
              params_summary: truncate(params),
            });

            log.info?.(
              `[platform-tools] Invoking ${tool.name} on ${tool.agent}`
            );
            try {
              const result = await invokeTool(tool.name, params, log);

              osLog("INFO", `Tool execute success: ${tool.name}`, {
                tool_name: tool.name,
                agent: tool.agent,
              });

              return {
                content: [
                  {
                    type: "text",
                    text: JSON.stringify(result, null, 2),
                  },
                ],
              };
            } catch (/** @type {any} */ err) {
              const errMsg = err instanceof Error ? err.message : String(err);

              osLog("ERROR", `Tool execute error: ${tool.name}: ${errMsg}`, {
                tool_name: tool.name,
                agent: tool.agent,
                error: errMsg,
                stack: err instanceof Error ? err.stack : undefined,
              });

              log.error?.(
                `[platform-tools] Invocation error: tool=${tool.name}, agent=${tool.agent}, error=${errMsg}`
              );
              return {
                content: [
                  {
                    type: "text",
                    text: `Error invoking ${tool.name}: ${errMsg}`,
                  },
                ],
                isError: true,
              };
            }
          },
        });

        log.info?.(
          `[platform-tools] Registered tool: ${toolName} (${tool.agent})`
        );
      }

      osLog("INFO", `Plugin ready: ${tools.length} tool(s) registered`, {
        tool_count: tools.length,
        tool_names: tools.map((/** @type {{name: string}} */ t) => t.name),
      });

      log.info?.(
        `[platform-tools] Plugin ready: ${tools.length} tool(s) registered`
      );

      // Flush immediately so init logs are visible right away
      flushLogs();
    })
    .catch((/** @type {any} */ err) => {
      const errMsg = err instanceof Error ? err.message : String(err);

      osLog("ERROR", `Failed to fetch discovery: ${errMsg}`, {
        error: errMsg,
        stack: err instanceof Error ? err.stack : undefined,
      });

      log.error?.(
        `[platform-tools] Failed to fetch discovery: ${errMsg}`
      );

      flushLogs();
    });
};
