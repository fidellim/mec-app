const { existsSync, readFileSync } = require('node:fs');
const { spawnSync } = require('node:child_process');

function loadEnvFile(file) {
  if (!existsSync(file)) {
    return;
  }

  readFileSync(file, 'utf8')
    .split(/\r?\n/)
    .forEach((line) => {
      const trimmed = line.trim();

      if (!trimmed || trimmed.startsWith('#')) {
        return;
      }

      const separator = trimmed.indexOf('=');

      if (separator === -1) {
        return;
      }

      const key = trimmed.slice(0, separator).trim();
      let value = trimmed.slice(separator + 1).trim();

      if (
        (value.startsWith('"') && value.endsWith('"')) ||
        (value.startsWith("'") && value.endsWith("'"))
      ) {
        value = value.slice(1, -1);
      }

      if (key && process.env[key] === undefined) {
        process.env[key] = value;
      }
    });
}

function run(command, args, options = {}) {
  const result = spawnSync(command, args, {
    cwd: process.cwd(),
    env: process.env,
    shell: false,
    stdio: 'inherit',
    ...options,
  });

  if (result.error) {
    console.error(result.error.message);
    process.exit(1);
  }

  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}

loadEnvFile('.env.e2e');

const php = process.env.PHP_BINARY || 'php';
const appEnv = process.env.E2E_APP_ENV || 'e2e';
const dbConnection = process.env.DB_CONNECTION || 'mysql';
const dbName = process.env.DB_DATABASE;

if (dbConnection === 'mysql') {
  if (!dbName) {
    console.error('DB_DATABASE must be set in .env.e2e before preparing the E2E database.');
    process.exit(1);
  }

  const createDatabaseCode = `
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $database = getenv('DB_DATABASE');
    $pdo = new PDO("mysql:host={$host};port={$port}", $username, $password);
    $quotedDatabase = str_replace(chr(96), chr(96).chr(96), $database);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS ".chr(96).$quotedDatabase.chr(96)." CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "E2E database ready: {$database}".PHP_EOL;
  `;

  run(php, ['-r', createDatabaseCode]);
}

run(php, ['artisan', 'migrate:fresh', '--seed', `--env=${appEnv}`]);
