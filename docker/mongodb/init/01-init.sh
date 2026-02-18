#!/bin/bash
# Kreiraj ovaj fajl: docker/mongodb/init/01-init.sh

mongosh -u root -p rootsecret <<EOF
use products
db.createCollection('products')
db.products.insertOne({
  "name": "Sample Product",
  "price": 99.99,
  "description": "Initial product for testing"
})

use analytics
db.createCollection('events')
db.events.insertOne({
  "event": "system.initialized",
  "timestamp": new Date()
})

print('MongoDB initialization completed')
EOF