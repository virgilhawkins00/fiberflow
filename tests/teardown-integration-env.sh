#!/bin/bash

set -e

echo "🛑 Stopping Docker containers..."

docker-compose -f docker-compose.test.yml down -v

echo "✅ All containers stopped and removed"

