# Tenant Management

## Overview

The platform supports multitenancy — each community operates as a separate tenant with isolated agents, scheduled jobs, and configuration.

## Core Concepts

### Tenant
A tenant is an isolated unit of the platform owned by one or more users. Each tenant has:
- A unique slug (URL identifier)
- A name
- An enabled/disabled status

### Tenant Roles
Each user has a role within a tenant:
- **owner** — full control: create, edit, delete the tenant
- **admin** — manage agents and settings
- **member** — view tenant data

### Global Roles
- **ROLE_SUPER_ADMIN** — access to all tenants and platform management
- **ROLE_ADMIN** — access to the admin panel
- **ROLE_USER** — basic access

## Tenant Switching

When a user belongs to multiple tenants, a switcher appears in the admin panel header. The selected tenant is stored in the session.

## Agents and Tenants

- Each installed agent belongs to a specific tenant
- **Dedicated agent** (`shared: false`) — can only be installed in one tenant
- **Shared agent** (`shared: true`) — can operate in multiple tenants simultaneously

## Tenant Deletion

A tenant cannot be deleted if it has:
- Active (enabled) agents
- Enabled scheduled jobs

All resources must be disabled or uninstalled first.
