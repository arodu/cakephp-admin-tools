<?php

declare(strict_types=1);

namespace AdminTools\Utility;

use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\Number;
use Cake\Utility\Hash;
use Cake\Utility\Text;
use Cake\I18n\FrozenTime;

class BackupDb
{
    use InstanceConfigTrait;

    protected ConsoleIo $io;

    protected $_defaultConfig = [
        'name' => 'backup-name',
    ];

    public function __construct(array $options, ConsoleIo $io)
    {
        $this->io = $io;
        $this->setConfig($this->prepareOptions($options));
        $this->generate($this->getConfig());
    }

    /**
     * @param array $options The options for the database backup.
     * @return array The prepared options for the database backup.
     */
    public function prepareOptions(array $options = []): array
    {
        $datasource = $options['datasource'] ?? null;

        if (empty($datasource)) {
            throw new \InvalidArgumentException('Datasource not defined');
        }

        $settings = Configure::read('AdminTools.backupDb');
        $datasourceOptions = $settings['datasourceOptions'][$datasource] ?? [];
        unset($settings['datasourceOptions'], $settings['datasources']);

        $options = Hash::merge($settings ?? [], $datasourceOptions ?? [], $options ?? []);
        $options['datetime'] = FrozenTime::now();

        return $options;
    }

    public function generate(array $options = []): void
    {
        $options = Hash::merge($this->getConfig(), $options);

        $this->setConfig('databaseConfig', $this->getDbConfig($options['datasource']));

        $this->setConfig('pathfile', $this->pathfile($options));

        $this->setConfig('label', $this->getConfig('pathfile'));
    }

    public function pathfile(array $options = []): string
    {
        $options = Hash::merge($this->getConfig(), $options);

        $compress = match ($options['compress']) {
            'gzip' => '.gz',
            'zip' => '.zip',
            'rar' => '.rar',
            default => '',
        };

        return $options['path'] . $this->filename($options) . $compress;
    }

    public function toArray(): array
    {
        $options = $this->getConfig();
        $database = array_intersect_key($options['databaseConfig'], array_flip(['scheme', 'host', 'port', 'database', 'timezone', 'name']));

        return [
            'name' => $options['name'] ?? null,
            'datasource' => $options['datasource'] ?? null,
            'compress' => $options['compress'] ?? null,
            'path' => $options['pathfile'] ?? null,
            'datetime' => $options['datetime'] ?? null,
            'datasource' => $database ?? null,
            'fileinfo' => $options['fileinfo'] ?? null,
        ];
    }

    public function getDbConfig($datasource): array
    {
        return ConnectionManager::get($datasource)?->config() ?? [];
    }

    /**
     * @param array $options
     * @return string
     */
    public function filename(array $options = []): string
    {
        $options = Hash::merge($this->getConfig(), $options);

        return Text::insert(
            $options['fileTemplate'] . '.:ext',
            [
                'name' => $options['name'] ?? null,
                'datasource' => $options['datasource'] ?? null,
                'date' => $options['datetime']->format('Ymd') ?? null,
                'datetime' => $options['datetime']->format('YmdHis') ?? null,
                'ext' => $options['fileExtension'] ?? 'sql' ?? null,
                'port' => $options['databaseConfig']['port'] ?? null,
                'host' => $options['databaseConfig']['host'] ?? null,
                'scheme' => $options['databaseConfig']['scheme'] ?? null,
                'database' => $options['databaseConfig']['database'] ?? null,
            ]
        );
    }

    public function runScript(array $options = []): array
    {
        $options = Hash::merge($this->getConfig(), $options);

        $script = $this->getScript($options);
        shell_exec($script);
        $fileinfo = $this->updateFileinfo($options);

        $afterExecScript = $options['afterExecScript'] ?? null;
        if ($afterExecScript && is_callable($afterExecScript)) {
            $options = $afterExecScript($options, $this->io);
            $this->setConfig($options);
        }

        return $fileinfo;
    }


    public function getScript(array $options = []): string
    {
        $options = Hash::merge($this->getConfig(), $options);

        if ($options['dumpScript'] && is_callable($options['dumpScript'])) {
            return $options['dumpScript']($options, $this->io);
        }

        $script = $this->dumpScript($options);
        $script .= $this->compressScript($options);

        return $script;
    }

    public function dumpScript(array $options = []): string
    {
        $options = Hash::merge($this->getConfig(), $options);
        $config = $options['databaseConfig'];

        if ($config['scheme'] === 'mysql') {
            $config['host'] = $config['host'] != 'localhost' ? '--host=' . $config['host'] : '';
            $config['port'] = $config['port'] != '3306' ? '--port=' . $config['port'] : '';

            return Text::insert(
                'mysqldump --user=":username" --password=":password" :port :host :database',
                $config
            );
        }

        if ($config['scheme'] === 'pgsql') {
            self::checkCommand('pg_dump');
            $config = array_merge([
                'port' => 5432,
                'host' => 'localhost',
            ], $config);

            return Text::insert(
                'pg_dump --username=:username --port=:port --host=:host :database',
                $config
            );
        }

        if ($config['scheme'] === 'sqlite') {
            self::checkCommand('sqlite3');
            return Text::insert(
                'sqlite3 :database .dump',
                $config
            );
        }

        if ($config['scheme'] === 'sqlserver') {
            self::checkCommand('sqlcmd');
            return Text::insert(
                'sqlcmd -S :host -U :username -P :password -d :database -Q "BACKUP DATABASE :database TO DISK = \':file\'"',
                $config
            );
        }

        throw new \RuntimeException('Scheme ' . $config['scheme'] . ' not supported');
    }

    /**
     * @param array $options
     * @return string
     */
    public function compressScript(array $options = []): string
    {
        $options = Hash::merge($this->getConfig(), $options);
        $file = $options['pathfile'];

        $compress = match ($options['compress']) {
            'gzip' => ' | gzip -c > ',
            'zip' => ' | zip > ',
            'rar' => ' | rar > ',
            default => ' > ',
        };

        return $compress . $file;
    }

    public function checkCommand(string $command): bool
    {
        $check = Text::insert('command -v :command >/dev/null 2>&1 || { echo >&2 "I require :command but it\'s not installed.  Aborting."; exit 1;}', [
            'command' => $command,
        ]);

        return (bool) shell_exec($check);
    }

    public function updateFileinfo(array $options = []): array
    {
        $options = Hash::merge($this->getConfig(), $options);

        $fileinfo = pathinfo($options['pathfile']);
        $fileinfo['size'] = Number::toReadableSize(filesize($options['pathfile']));

        $this->setConfig('fileinfo', $fileinfo);

        $this->setConfig('label', $this->getConfig('pathfile') . ' (' . $fileinfo['size'] . ')');

        return $fileinfo;
    }

    public function removeFile(array $options = []): bool
    {
        $options = Hash::merge($this->getConfig(), $options);

        if ($options['removeFile'] && file_exists($options['pathfile'])) {
            return unlink($options['pathfile']);
        }

        return false;
    }

    public function label(): string
    {
        return $this->getConfig('label');
    }
}
