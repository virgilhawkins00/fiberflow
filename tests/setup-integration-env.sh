#!/bin/bash

set -e

echo "🐳 Starting Docker containers for integration tests..."

# Start containers
docker-compose -f docker-compose.test.yml up -d

echo "⏳ Waiting for services to be healthy..."

# Wait for MySQL
echo "  - Waiting for MySQL..."
for i in {1..60}; do
    if docker-compose -f docker-compose.test.yml exec -T mysql mysqladmin ping -h localhost -u root -proot --silent 2>/dev/null; then
        break
    fi
    sleep 1
done
echo "  ✅ MySQL is ready"

# Wait for RabbitMQ
echo "  - Waiting for RabbitMQ..."
for i in {1..60}; do
    if docker-compose -f docker-compose.test.yml exec -T rabbitmq rabbitmq-diagnostics ping --silent 2>/dev/null; then
        break
    fi
    sleep 1
done
echo "  ✅ RabbitMQ is ready"

# Wait for HTTPBin
echo "  - Waiting for HTTPBin..."
for i in {1..60}; do
    if curl -sf http://localhost:8080/status/200 > /dev/null 2>&1; then
        break
    fi
    sleep 1
done
echo "  ✅ HTTPBin is ready"

echo ""
echo "✅ All services are ready!"
echo ""
echo "Service URLs:"
echo "  - MySQL:     localhost:3307 (user: fiberflow, pass: fiberflow, db: fiberflow_test)"
echo "  - RabbitMQ:  localhost:5673 (user: fiberflow, pass: fiberflow)"
echo "  - RabbitMQ Management: http://localhost:15673"
echo "  - HTTPBin:   http://localhost:8080"
echo ""

