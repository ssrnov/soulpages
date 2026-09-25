module.exports = {
  apps: [
    {
      name: 'soulsync-backend',
      script: './server.js',
      cwd: './soulsync-backend',
      instances: 1,
      exec_mode: 'fork',
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production',
        PORT: 5000
      },
      env_development: {
        NODE_ENV: 'development',
        PORT: 5000
      }
    },
    {
      name: 'soulsync-admin',
      script: 'node_modules/next/dist/bin/next',
      args: 'start -p 3001',
      cwd: './soulsync-admin',
      instances: 1,
      exec_mode: 'fork',
      watch: false,
      max_memory_restart: '1G',
      env: {
        NODE_ENV: 'production',
        PORT: 3001
      },
      env_development: {
        NODE_ENV: 'development',
        PORT: 3001
      }
    }
  ]
};
