# Workflow

Workflows are event-driven, node-based systems to control the execution flow of an application. They allow for arbitrarily complex flows by combining nodes and events.

## What is a Workflow

A workflow is a collection of **Nodes** triggered by **Events**. Each node can return another event, triggering the next node in the chain.

## Core Concepts

- **Nodes**: invokable classes that handle an incoming event and return another.
- **Events**: Objects that trigger nodes and carry data.
- **State**: A shared object available to all nodes to read and write data.

## Why Use Workflows?

- **Maintainability**: Encapsulates logic into reusable nodes.
- **Human-in-the-loop**: Pause execution for human input.
- **Streaming**: Send real-time updates to clients.
- **Observability**: Built-in monitoring with Inspector.

## Comparison with Agent/RAG

`Agent` and `RAG` classes are high-level implementations of common patterns. `Workflow` is the foundational component they are built on, allowing you to create custom agentic systems from scratch.
