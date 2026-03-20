#!/bin/bash
# Deployment Configuration Validation Script
# This script validates that the deployment configuration is properly set up

set -e

echo "🔍 Validating AI Community Platform Deployment Configuration..."
echo

# Check if required files exist
echo "📁 Checking configuration files..."

if [ -f ".env.deployment.example" ]; then
    echo "✅ .env.deployment.example exists"
else
    echo "❌ .env.deployment.example is missing"
    exit 1
fi

if [ -f "docs/deployment-configuration.md" ]; then
    echo "✅ docs/deployment-configuration.md exists"
else
    echo "❌ docs/deployment-configuration.md is missing"
    exit 1
fi

echo

# Check compose files use environment variables
echo "🐳 Checking Docker Compose files use environment variables..."

compose_files=(
    "compose.core.yaml"
    "compose.agent-hello.yaml"
    "compose.agent-knowledge.yaml"
    "compose.agent-news-maker.yaml"
)

for file in "${compose_files[@]}"; do
    if [ -f "$file" ]; then
        if grep -q '\${.*:-' "$file"; then
            echo "✅ $file uses environment variable defaults"
        else
            echo "❌ $file does not use environment variable defaults"
            exit 1
        fi
    else
        echo "⚠️  $file not found (optional)"
    fi
done

echo

# Check health endpoints exist in applications
echo "🏥 Checking health endpoint implementations..."

# Core health controller
if [ -f "apps/core/src/Controller/HealthController.php" ]; then
    if grep -q "health/ready" "apps/core/src/Controller/HealthController.php"; then
        echo "✅ Core platform has enhanced health endpoints"
    else
        echo "❌ Core platform missing enhanced health endpoints"
        exit 1
    fi
else
    echo "❌ Core HealthController not found"
    exit 1
fi

# Hello agent health controller
if [ -f "apps/hello-agent/src/Controller/HealthController.php" ]; then
    if grep -q "health/ready" "apps/hello-agent/src/Controller/HealthController.php"; then
        echo "✅ Hello agent has enhanced health endpoints"
    else
        echo "❌ Hello agent missing enhanced health endpoints"
        exit 1
    fi
else
    echo "⚠️  Hello agent HealthController not found (optional)"
fi

# Knowledge agent health controller
if [ -f "apps/knowledge-agent/src/Controller/HealthController.php" ]; then
    if grep -q "health/ready" "apps/knowledge-agent/src/Controller/HealthController.php"; then
        echo "✅ Knowledge agent has enhanced health endpoints"
    else
        echo "❌ Knowledge agent missing enhanced health endpoints"
        exit 1
    fi
else
    echo "⚠️  Knowledge agent HealthController not found (optional)"
fi

# News maker agent health controller
if [ -f "apps/news-maker-agent/app/routers/health.py" ]; then
    if grep -q "health/ready" "apps/news-maker-agent/app/routers/health.py"; then
        echo "✅ News maker agent has enhanced health endpoints"
    else
        echo "❌ News maker agent missing enhanced health endpoints"
        exit 1
    fi
else
    echo "⚠️  News maker agent health router not found (optional)"
fi

echo

# Check signal handling in long-running commands
echo "🔄 Checking signal handling in long-running commands..."

signal_commands=(
    "apps/core/src/Command/CoderWorkerStartCommand.php"
    "apps/core/src/Command/SchedulerRunCommand.php"
    "apps/core/src/Command/TelegramPollCommand.php"
)

for cmd in "${signal_commands[@]}"; do
    if [ -f "$cmd" ]; then
        if grep -q "SignalableCommandInterface" "$cmd"; then
            echo "✅ $(basename "$cmd") implements signal handling"
        else
            echo "❌ $(basename "$cmd") missing signal handling"
            exit 1
        fi
    else
        echo "⚠️  $(basename "$cmd") not found (optional)"
    fi
done

echo

# Check E2E tests exist
echo "🧪 Checking E2E test coverage..."

if [ -f "tests/e2e/tests/smoke/health_test.js" ]; then
    if grep -q "health/ready" "tests/e2e/tests/smoke/health_test.js"; then
        echo "✅ E2E health tests include readiness checks"
    else
        echo "❌ E2E health tests missing readiness checks"
        exit 1
    fi
else
    echo "❌ E2E health tests not found"
    exit 1
fi

if [ -f "tests/e2e/tests/smoke/deployment_config_test.js" ]; then
    echo "✅ Deployment configuration E2E tests exist"
else
    echo "❌ Deployment configuration E2E tests missing"
    exit 1
fi

echo

# Summary
echo "🎉 All deployment configuration validations passed!"
echo
echo "Next steps:"
echo "1. Copy .env.deployment.example to .env.deployment"
echo "2. Customize environment variables for your deployment"
echo "3. Run E2E tests to validate the configuration"
echo "4. Deploy using Docker Compose or Kubernetes"
echo

exit 0