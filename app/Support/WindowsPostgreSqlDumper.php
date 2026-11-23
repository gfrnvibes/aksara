<?php

namespace App\Support;

use Spatie\DbDumper\Databases\PostgreSql;

class WindowsPostgreSqlDumper extends PostgreSql
{
    public function getDumpCommand(string $dumpFile): string
    {
        if ($this->compressor !== null) {
            return parent::getDumpCommand($dumpFile);
        }

        $dir = dirname($dumpFile);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $quote = $this->determineQuote();
        $command = [
            "{$quote}{$this->dumpBinaryPath}pg_dump{$quote}",
            "-U \"{$this->userName}\"",
            '-h '.($this->socket === '' ? $this->host : $this->socket),
            "-p {$this->port}",
        ];

        if ($this->useInserts) {
            $command[] = '--inserts';
        }

        if (! $this->createTables) {
            $command[] = '--data-only';
        }

        if (! $this->includeData) {
            $command[] = '--schema-only';
        }

        foreach ($this->extraOptions as $extraOption) {
            $command[] = $extraOption;
        }

        if (! empty($this->includeTables)) {
            $command[] = '-t '.implode(' -t ', $this->includeTables);
        }

        if (! empty($this->excludeTables)) {
            $command[] = '-T '.implode(' -T ', $this->excludeTables);
        }

        $cleanFile = str_replace('/', DIRECTORY_SEPARATOR, $dumpFile);
        $command[] = "-f {$quote}{$cleanFile}{$quote}";

        return implode(' ', $command);
    }

    /** @return array<string, string> */
    protected function getEnvironmentVariablesForDumpCommand(string $temporaryCredentialsFile): array
    {
        $vars = parent::getEnvironmentVariablesForDumpCommand($temporaryCredentialsFile);
        $serverUpper = array_change_key_case($_SERVER, CASE_UPPER);

        foreach (['SystemRoot', 'WINDIR', 'TEMP', 'TMP', 'PATH'] as $key) {
            $value = getenv($key);
            if (! is_string($value) || $value === '') {
                $value = $_ENV[$key] ?? $_SERVER[$key] ?? $serverUpper[strtoupper($key)] ?? null;
            }
            if (is_string($value) && $value !== '') {
                $vars[$key] = $value;
            }
        }

        $vars['SystemRoot'] ??= 'C:\\Windows';
        $vars['WINDIR'] ??= $vars['SystemRoot'];
        $vars['TEMP'] ??= sys_get_temp_dir();
        $vars['TMP'] ??= $vars['TEMP'];

        return $vars;
    }
}
