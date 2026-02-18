// Switch to admin database
db = db.getSiblingDB('admin');

// Authenticate as root
db.auth('root', 'rootsecret');

// Create databases
db = db.getSiblingDB('products');
db.createCollection('products');

db = db.getSiblingDB('analytics');
db.createCollection('events');

print('MongoDB databases initialized successfully');