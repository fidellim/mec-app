const { existsSync, readFileSync } = require('node:fs');
const { spawnSync } = require('node:child_process');

function parseEnvFile(file) {
  if (!existsSync(file)) {
    return {};
  }

  return readFileSync(file, 'utf8')
    .split(/\r?\n/)
    .reduce((values, line) => {
      const trimmed = line.trim();

      if (!trimmed || trimmed.startsWith('#')) {
        return values;
      }

      const separator = trimmed.indexOf('=');

      if (separator === -1) {
        return values;
      }

      const key = trimmed.slice(0, separator).trim();
      let value = trimmed.slice(separator + 1).trim();

      if (
        (value.startsWith('"') && value.endsWith('"')) ||
        (value.startsWith("'") && value.endsWith("'"))
      ) {
        value = value.slice(1, -1);
      }

      if (key) {
        values[key] = value;
      }

      return values;
    }, {});
}

function loadEnvValues(values) {
  Object.entries(values).forEach(([key, value]) => {
    if (process.env[key] === undefined) {
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

const localEnv = parseEnvFile('.env');
const e2eEnv = parseEnvFile('.env.e2e');
loadEnvValues(e2eEnv);

const php = process.env.PHP_BINARY || 'php';
const appEnv = process.env.E2E_APP_ENV || 'e2e';
const dbConnection = process.env.DB_CONNECTION || 'mysql';
const dbName = process.env.DB_DATABASE;
const configuredAppEnv = process.env.APP_ENV;
const localDbName = localEnv.DB_DATABASE;

if (configuredAppEnv !== 'e2e' || appEnv !== 'e2e') {
  console.error('Refusing to prepare E2E data unless APP_ENV and E2E_APP_ENV are both set to e2e.');
  process.exit(1);
}

if (!dbName || !dbName.endsWith('_e2e')) {
  console.error('Refusing to prepare E2E data unless DB_DATABASE ends with _e2e.');
  process.exit(1);
}

if (localDbName && dbName === localDbName) {
  console.error(`Refusing to prepare E2E data because DB_DATABASE matches the local database (${dbName}).`);
  process.exit(1);
}

if (dbConnection === 'mysql') {
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
