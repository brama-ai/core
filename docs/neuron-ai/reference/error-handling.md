# Error Handling

Managing errors within an agentic system.

## Overview

Neuron provides mechanisms to catch and handle errors during workflow execution.

### Tool Errors

If a tool execution fails, the agent can be informed of the error to decide whether to retry or try a different approach.

### Workflow Interruptions

Exceptions like `WorkflowInterrupt` (used for human-in-the-loop) need to be handled to resume execution later.

For detailed monitoring and debugging of errors, refer to the [Monitoring & Debugging](monitoring-and-debugging.md) section.
