<?php
declare(strict_types=1);

namespace AdminTools\Test\TestCase\Command;

use AdminTools\Command\BackupDbCommand;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\ConsoleOutput;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * AdminTools\Command\BackupDbCommand Test Case
 */
class BackupDbCommandTest extends TestCase
{
    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('AdminTools.backupDb', [
            'enabled' => true,
            'name' => 'sample',
            'datasources' => 'test',
            'compress' => 'none',
            'path' => TMP,
            'fileTemplate' => ':name_:datasource_:datetime',
            'fileExtension' => 'sql',
            'removeFile' => false,
            'dumpScript' => null,
        ]);
        Configure::write('AdminTools.backupEmail', ['enabled' => false]);
    }

    /**
     * Test defaultName.
     *
     * @return void
     */
    public function testDefaultName(): void
    {
        $this->assertSame('backup_db', BackupDbCommand::defaultName());
    }

    /**
     * Test buildOptionParser defines the expected options.
     *
     * @return void
     */
    public function testBuildOptionParser(): void
    {
        $command = new BackupDbCommand();
        $parser = $command->buildOptionParser(new ConsoleOptionParser('backup_db'));
        $help = $parser->help();

        foreach (['datasources', 'compress', 'name', 'path', 'disableRemoveFile', 'emailList', 'emailDisabled', 'emailConfig'] as $option) {
            $this->assertStringContainsString('--' . $option, $help, "Missing option: $option");
        }
    }

    /**
     * Test execute returns success and warns when the backup is disabled.
     *
     * @return void
     */
    public function testExecuteDisabled(): void
    {
        Configure::write('AdminTools.backupDb.enabled', false);

        $file = tempnam(sys_get_temp_dir(), 'admintools');
        $stream = fopen($file, 'r+');
        $io = new ConsoleIo(new ConsoleOutput($stream), new ConsoleOutput($stream));

        $exit = (new BackupDbCommand())->run([], $io);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);
        unlink($file);

        $this->assertSame(Command::CODE_SUCCESS, $exit);
        $this->assertStringContainsString('disabled', $output);
    }
}
