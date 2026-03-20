# Async

AI Agents are I/O heavy due to long LLM inference times. Neuron provides ways to run multiple agents efficiently.

## Concurrency

Run multiple agents in parallel using PHP process management:

- [spatie/fork](https://github.com/spatie/fork)
- Laravel Concurrency
- Symfony Process

## Async

Neuron supports async event loops like Amp and ReactPHP.

### HttpClientInterface

To run in an async environment, inject an async-compatible HTTP client:

```php
use NeuronAI\Http\HttpClientInterface;

// Inject custom async client implementation
```

By default, Guzzle is used, but you can swap it for Amphp or ReactPHP clients.
