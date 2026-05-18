const { spawnSync } = require('node:child_process');

const args = ['playwright', 'test', '--headed', ...process.argv.slice(2)];

const result = spawnSync('npx', args, {
  env: {
    ...process.env,
    E2E_SLOW_MO: process.env.E2E_SLOW_MO || '500',
  },
  shell: true,
  stdio: 'inherit',
});

process.exit(result.status ?? 1);
