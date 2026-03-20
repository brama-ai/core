# Monitoring & Debugging

AI systems are non-deterministic, making reproducibility and debugging difficult. Neuron includes built-in observability features powered by Inspector.

## The Problem

Same input ≠ same output. Complex agents involve multiple steps, tool calls, and external memories. Inspecting what the agent is doing and _why_ is crucial.

## Getting Started

Set the `INSPECTOR_INGESTION_KEY` in your `.env` file to start monitoring:

```env
INSPECTOR_INGESTION_KEY=your_key_here
```

## Features

- **Real-time tracing**: See internal steps as they happen.
- **Tool execution flow**: Inspect input/output of every tool call.
- **LLM call details**: Monitor prompt sequences and model responses.

For more details, visit [Inspector.dev](https://inspector.dev/).
