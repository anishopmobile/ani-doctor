<?php

namespace App\Modules\Operations\Infrastructure;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

final class MysqlSnapshot
{
    public function dump(string $defaults, callable $write): void
    {
        $this->mysqlOnly();
        foreach (DB::select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ?', ['BASE TABLE']) as $table) {
            if (strtoupper($table->ENGINE) !== 'INNODB') {
                throw new RuntimeException('BACKUP_REQUIRES_INNODB');
            }
        }
        $command = [config('operations.mysqldump'), '--defaults-extra-file='.$defaults, '--single-transaction', '--quick', '--hex-blob', '--no-tablespaces', '--set-gtid-purged=OFF', '--skip-add-locks', '--skip-comments', config('database.connections.mysql.database')];
        $process = new Process($command);
        $process->setTimeout(config('operations.process_timeout'));
        $process->run(function (string $type, string $data) use ($write, $process): void {
            if ($type === Process::OUT) {
                $write($data);
                $process->clearOutput();
            } else {
                $process->clearErrorOutput(); // Never report SQL, credentials or raw client errors.
            }
        });
        if (!$process->isSuccessful()) {
            throw new RuntimeException('DATABASE_BACKUP_FAILED');
        }
    }

    public function assertEmpty(string $target): void
    {
        $this->mysqlOnly();
        if (!preg_match('/\Aani_doctor_restore_[a-z0-9_]{1,40}\z/', $target) || $target === config('database.connections.mysql.database')) {
            throw new RuntimeException('RESTORE_TARGET_INVALID');
        }
        $username = config('operations.restore_username');
        if (!$username || $username === config('database.connections.mysql.username')) {
            throw new RuntimeException('RESTORE_REQUIRES_SEPARATE_CREDENTIALS');
        }
        config(['database.connections.operations_restore' => [...config('database.connections.mysql'), 'database' => $target, 'username' => $username, 'password' => config('operations.restore_password')]]);
        DB::purge('operations_restore');
        $connection = DB::connection('operations_restore');
        $escapedTarget = str_replace('_', '\\_', $target);
        foreach ($connection->select('SHOW GRANTS') as $row) {
            $grant = array_values((array) $row)[0];
            if (str_contains($grant, 'WITH GRANT OPTION') || (!str_starts_with($grant, 'GRANT USAGE ON *.* TO ') && !str_contains($grant, ' ON `'.$escapedTarget.'`.* TO '))) {
                throw new RuntimeException('RESTORE_CREDENTIALS_NOT_ISOLATED');
            }
        }
        $exists = $connection->selectOne('SELECT COUNT(*) AS total FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$target]);
        $tables = $connection->selectOne('SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$target]);
        if ((int) $exists->total !== 1 || (int) $tables->total !== 0) {
            throw new RuntimeException('RESTORE_TARGET_NOT_EMPTY');
        }
    }

    public function restore(string $defaults, string $target, string $sql): void
    {
        $this->assertEmpty($target);
        $input = fopen($sql, 'rb');
        try {
            $process = new Process([config('operations.mysql'), '--defaults-extra-file='.$defaults, '--binary-mode', $target]);
            $process->setInput($input)->setTimeout(config('operations.process_timeout'));
            $process->run(function (string $type, string $data) use ($process): void {
                $process->clearOutput();
                $process->clearErrorOutput();
            });
            if (!$process->isSuccessful()) {
                throw new RuntimeException('DATABASE_RESTORE_FAILED');
            }
        } finally {
            fclose($input);
        }
    }

    public function defaults(string $directory, bool $restore = false): string
    {
        $this->mysqlOnly();
        $config = config('database.connections.mysql');
        $escape = fn ($value) => '"'.str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], (string) $value).'"';
        $options = ['host' => $config['host'], 'port' => $config['port'], 'user' => $config['username'], 'password' => $config['password'], 'default-character-set' => 'utf8mb4'];
        if ($restore) {
            $options['user'] = config('operations.restore_username');
            $options['password'] = config('operations.restore_password');
        }
        if ($config['unix_socket']) {
            $options['socket'] = $config['unix_socket'];
        }
        $sslCaOption = PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA;
        if ($ca = ($config['options'][$sslCaOption] ?? null)) {
            $options['ssl-ca'] = $ca;
            $options['ssl-mode'] = 'VERIFY_IDENTITY';
        }
        $file = $directory.'/client.cnf';
        $stream = fopen($file, 'xb');
        chmod($file, 0600);
        try {
            $body = "[client]\n";
            foreach ($options as $key => $value) {
                $body .= $key.'='.$escape($value)."\n";
            }
            app(EncryptedArchive::class)->put($stream, $body);
        } finally {
            fclose($stream);
        }
        return $file;
    }

    private function mysqlOnly(): void
    {
        if (DB::getDriverName() !== 'mysql' || config('database.connections.mysql.url')) {
            throw new RuntimeException('BACKUP_REQUIRES_EXPLICIT_MYSQL_CONFIGURATION');
        }
    }
}
