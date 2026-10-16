<?php
declare(strict_types=1);

namespace AdminTools\Command;

use AdminTools\Utility\BackupDb;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Mailer\Mailer;
use Cake\Utility\Hash;
use Exception;

/**
 * BackupDb command.
 */
class BackupDbCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $settingsBackupDb = Configure::read('AdminTools.backupDb');
        $settingsBackupEmail = Configure::read('AdminTools.backupEmail');

        $parser->addOptions([
            'datasources' => [
                'help' => 'Datasources to backup',
                'default' => Hash::get($settingsBackupDb, 'datasources', 'default'),
                'short' => 'd',
            ],
            'compress' => [
                'help' => 'Compress the backup',
                'default' => Hash::get($settingsBackupDb, 'compress', 'gzip'),
                'choices' => ['gzip', 'zip', 'rar', 'none'],
                'short' => 'c',
            ],
            'name' => [
                'help' => 'backup name identifier',
                'default' => Hash::get($settingsBackupDb, 'name', 'backup-name'),
                'short' => 'n',
            ],
            'path' => [
                'help' => 'Path to save the backup',
                'default' => Hash::get($settingsBackupDb, 'path', TMP),
            ],
            'disableRemoveFile' => [
                'help' => 'Disable the remove file',
                'boolean' => true,
            ],
            'emailList' => [
                'help' => 'List of emails to send the backup, separated by comma',
            ],
            'emailDisabled' => [
                'help' => 'Disable the email',
                'boolean' => true,
            ],
            'emailConfig' => [
                'help' => 'Email configuration to send the backup',
                'default' => Hash::get($settingsBackupEmail, 'config', 'default'),
            ],
        ]);

        $parser->setDescription([
            'Backup a database',
            'This command will backup a database and send an email with the backup file',
        ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|void|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        try {
            if (!Configure::read('AdminTools.backupDb.enabled')) {
                $io->warning(__('Backup DB is disabled, enable it in config/admin_tools.php'));

                return self::CODE_SUCCESS;
            }

            $options = $args->getOptions();

            $datasources = $options['datasources'] ?? Configure::read('AdminTools.backupDb.datasources') ?? 'default';
            $datasources = explode(',', $datasources);

            $backupDbList = [];
            foreach ($datasources as $datasource) {
                $backupDb = new BackupDb([
                    'datasource' => $datasource,
                    'compress' => $options['compress'],
                    'name' => $options['name'],
                    'path' => $options['path'],
                    'removeFile' => !$options['disableRemoveFile'],
                ], $io);

                $backupDb->runScript();

                $io->success('Backup saved: ' . $backupDb->label(), 1, ConsoleIo::VERBOSE);

                $backupDbList[] = $backupDb;
            }

            $emailOptions = [
                'name' => $options['name'],
                'enabled' => !$options['emailDisabled'],
                'config' => $options['emailConfig'],
                'emailList' => $options['emailList'] ?? null,
            ];

            if ($this->sendEmail($emailOptions, $backupDbList)) {
                $io->success('Email sent', 1, ConsoleIo::VERBOSE);
            }

            return self::CODE_SUCCESS;
        } catch (Exception $e) {
            $io->error($e->getMessage());

            return self::CODE_ERROR;
        } finally {
            foreach ($backupDbList ?? [] as $backupDb) {
                if ($backupDb->removeFile()) {
                    $io->success('File removed: ' . $backupDb->label(), 1, ConsoleIo::VERBOSE);
                }
            }
        }
    }

    /**
     * @param array $options
     * @param array $backupDbList
     * @return bool
     */
    protected function sendEmail(array $options, array $backupDbList): bool
    {
        $emailSettings = Configure::read('AdminTools.backupEmail');
        $options = Hash::merge($emailSettings, $options);

        if (!$options['enabled']) {
            return false;
        }

        $options['emailList'] = $options['emailList'] ?? $emailSettings['emailList'];
        if (is_string($options['emailList'])) {
            $options['emailList'] = explode(',', $options['emailList']);
        }

        if (empty($options['emailList'])) {
            throw new Exception('Email list not defined');
        }

        $content = array_reduce($backupDbList, function ($carry, $backupDb) {
            $carry['files'][] = $backupDb->getConfig('pathfile');
            $carry['options'][$backupDb->getConfig('datasource')] = $backupDb->toArray();

            return $carry;
        }, []);

        $mailer = new Mailer($options['config'] ?? 'default');
        $mailer
            ->setTo($options['emailList'])
            ->setSubject($options['subject'] ?? __('Backup: {0}', $options['name']))
            ->setEmailFormat($options['format'] ?? 'both')
            ->setAttachments($content['files'])
            ->setViewVars([
                'name' => $options['name'],
                'backups' => $content['options'],
            ])
            ->viewBuilder()
            ->setTemplate($options['template'] ?? 'AdminTools.backup')
            ->setLayout($options['layout'] ?? 'default');
        $mailer->deliver();

        return true;
    }
}
