# PHP Fiber HTTP Client

A native PHP experiment in concurrency and non-blocking I/O.

This repository demonstrates how to implement a fully asynchronous HTTP client using PHP Fibers, non-blocking sockets, and a manual event loop (scheduler) — all without external dependencies.

The goal is educational: to illustrate how PHP’s Fiber API can bring readable, coroutine-style concurrency to an otherwise synchronous environment.

## Disclaimer
🚨**This project is not production‑ready and is provided for educational and learning purposes only.**

## Features
- Non-blocking TCP/SSL socket communication using `stream_socket_client()`
- Simple event loop based on `stream_select()`
- HTTP/1.1 support (GET requests)
- Timeout management and request state machine
- Lightweight scheduler capable of running multiple concurrent requests
- Clean fiber-based architecture emulating async/await semantics

## Motivation
- The author wanted to practice with Fibers and understand how they work internally
- Demonstrate building an async runtime from primitives.


