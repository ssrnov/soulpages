const fs = require('fs');
const path = require('path');

const srcAdmin = path.join(__dirname, 'soulsync-admin');
const srcBackend = path.join(__dirname, 'soulsync-backend');
const destRoot = path.join(__dirname, 'soulsync-system');

// Directories to create
const dirsToCreate = [
  'apps/admin',
  'apps/mobile-api',
  'shared',
  'sockets',
  'workers',
  'queues',
  'analytics',
  'monitoring',
  'notifications',
  'feature-engine',
  'permissions-engine',
  'device-engine',
  'event-bus',
  'security',
  'backup-engine',
  'database',
  'storage/uploads',
  'storage/temp',
  'storage/cache',
  'storage/logs',
  'storage/backups',
  'routes',
  'controllers',
  'middleware',
  'models',
  'services',
  'config',
  'scripts',
  'nginx',
  'docker',
  'utils'
];

console.log('Creating folders inside soulsync-system...');
dirsToCreate.forEach(dir => {
  const fullPath = path.join(destRoot, dir);
  if (!fs.existsSync(fullPath)) {
    fs.mkdirSync(fullPath, { recursive: true });
  }
});

// Recursive copy helper with skip list
function copyRecursiveSync(src, dest) {
  const exists = fs.existsSync(src);
  const stats = exists && fs.statSync(src);
  const isDirectory = exists && stats.isDirectory();

  if (isDirectory) {
    const base = path.basename(src);
    if (base === 'node_modules' || base === '.next' || base === '.git') {
      return;
    }
    if (!fs.existsSync(dest)) {
      fs.mkdirSync(dest, { recursive: true });
    }
    fs.readdirSync(src).forEach(child => {
      copyRecursiveSync(path.join(src, child), path.join(dest, child));
    });
  } else {
    // Check if destination directory exists, if not create it
    const destDir = path.dirname(dest);
    if (!fs.existsSync(destDir)) {
      fs.mkdirSync(destDir, { recursive: true });
    }
    fs.copyFileSync(src, dest);
  }
}

console.log('Copying Next.js admin frontend to apps/admin (skipping node_modules)...');
if (fs.existsSync(srcAdmin)) {
  copyRecursiveSync(srcAdmin, path.join(destRoot, 'apps/admin'));
}

console.log('Copying backend modules...');
const copyMappings = [
  { from: 'controllers', to: 'controllers' },
  { from: 'routes', to: 'routes' },
  { from: 'middleware', to: 'middleware' },
  { from: 'models', to: 'models' },
  { from: 'services', to: 'services' },
  { from: 'config', to: 'config' },
  { from: 'sockets', to: 'sockets' },
  { from: 'utils', to: 'utils' }
];

copyMappings.forEach(mapping => {
  const fromPath = path.join(srcBackend, mapping.from);
  const toPath = path.join(destRoot, mapping.to);
  if (fs.existsSync(fromPath)) {
    console.log(`Copying soulsync-backend/${mapping.from} -> soulsync-system/${mapping.to}...`);
    copyRecursiveSync(fromPath, toPath);
  }
});

// Copy core server files
console.log('Copying backend app.js and server.js to apps/mobile-api...');
const filesToCopy = [
  { from: 'app.js', to: 'apps/mobile-api/app.js' },
  { from: 'server.js', to: 'apps/mobile-api/server.js' },
  { from: 'package.json', to: 'apps/mobile-api/package.json' }
];

filesToCopy.forEach(mapping => {
  const fromPath = path.join(srcBackend, mapping.from);
  const toPath = path.join(destRoot, mapping.to);
  if (fs.existsSync(fromPath)) {
    fs.copyFileSync(fromPath, toPath);
  }
});

console.log('Successfully completed restructuring and copying.');
