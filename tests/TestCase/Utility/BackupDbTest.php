<?php
declare(strict_types=1);

namespace AdminTools\Test\TestCase\Utility;

use AdminTools\Utility\BackupDb;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOutput;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * AdminTools\Utility\BackupDb Test Case
 */
class BackupDbTest extends TestCase
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
            'compress' => 'none',
            'path' => TMP,
            'fileTemplate' => ':name_:datasource_:datetime',
            'fileExtension' => 'sql',
            'removeFile' => false,
            'dumpScript' => null,
        ]);
    }

    /**
     * Build a BackupDb instance for the "test" datasource.
     *
     * @param array $options Extra options.
     * @return \AdminTools\Utility\BackupDb
     */
    protected function backup(array $options = []): BackupDb
    {
        $io = new ConsoleIo(new ConsoleOutput(), new ConsoleOutput());

        return new BackupDb(['datasource' => 'test'] + $options, $io);
    }

    /**
     * A mysql database config, used to test script generation regardless of
     * the actual test connection (sqlite in-memory).
     *
     * @return array
     */
    protected function mysqlConfig(): array
    {
        return [
            'scheme' => 'mysql',
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'app',
            'username' => 'user',
            'password' => 'pass',
        ];
    }

    /**
     * Test prepareOptions throws without a datasource.
     *
     * @return void
     */
    public function testPrepareOptionsRequiresDatasource(): void
    {
        $backup = $this->backup();

        $this->expectException(InvalidArgumentException::class);
        $backup->prepareOptions([]);
    }

    /**
     * Test filename uses the configured template.
     *
     * @return void
     */
    public function testFilename(): void
    {
        $filename = $this->backup()->filename();

        $this->assertStringStartsWith('sample_test_', $filename);
        $this->assertStringEndsWith('.sql', $filename);
    }

    /**
     * Test pathfile combines path, filename and compress extension.
     *
     * @return void
     */
    public function testPathfile(): void
    {
        $backup = $this->backup();

        $this->assertSame(TMP . $backup->filename(), $backup->pathfile());
    }

    /**
     * Test pathfile appends the gzip extension.
     *
     * @return void
     */
    public function testPathfileGzip(): void
    {
        $this->assertStringEndsWith('.sql.gz', $this->backup(['compress' => 'gzip'])->pathfile());
    }

    /**
     * Test compressScript for the supported methods.
     *
     * @return void
     */
    public function testCompressScript(): void
    {
        $this->assertStringStartsWith(' | gzip -c > ', $this->backup(['compress' => 'gzip'])->compressScript());
        $this->assertStringStartsWith(' | zip > ', $this->backup(['compress' => 'zip'])->compressScript());
        $this->assertStringStartsWith(' > ', $this->backup(['compress' => 'none'])->compressScript());
    }

    /**
     * Test dumpScript builds a mysqldump command for the mysql scheme.
     *
     * @return void
     */
    public function testDumpScriptMysql(): void
    {
        $script = $this->backup()->dumpScript(['databaseConfig' => $this->mysqlConfig()]);

        $this->assertStringContainsString('mysqldump', $script);
        $this->assertStringContainsString('app', $script);
    }

    /**
     * Test dumpScript throws for an unsupported scheme.
     *
     * @return void
     */
    public function testDumpScriptUnsupportedScheme(): void
    {
        $this->expectException(RuntimeException::class);
        $this->backup()->dumpScript(['databaseConfig' => ['scheme' => 'oracle']]);
    }

    /**
     * Test getScript combines dump and compress scripts.
     *
     * @return void
     */
    public function testGetScript(): void
    {
        $script = $this->backup(['compress' => 'none'])->getScript(['databaseConfig' => $this->mysqlConfig()]);

        $this->assertStringContainsString('mysqldump', $script);
        $this->assertStringContainsString(' > ', $script);
    }

    /**
     * Test removeFile returns false when disabled.
     *
     * @return void
     */
    public function testRemoveFileDisabled(): void
    {
        $this->assertFalse($this->backup(['removeFile' => false])->removeFile());
    }

    /**
     * Test toArray exposes the main fields.
     *
     * @return void
     */
    public function testToArray(): void
    {
        $array = $this->backup()->toArray();

        $this->assertSame('sample', $array['name']);
        $this->assertSame('none', $array['compress']);
        $this->assertArrayHasKey('scheme', $array['datasource']);
    }
}
