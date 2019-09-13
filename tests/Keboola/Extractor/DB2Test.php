<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/12/15
 * Time: 14:25
 */

namespace Keboola\DbExtractor;

use Keboola\DbExtractor\Exception\UserException;
use Keboola\DbExtractor\Test\ExtractorTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class DB2Test extends TestCase
{
    /** @var Application */
    protected $app;

    protected $dataDir = ROOT_PATH . "/tests/data";

    public function setUp(): void
    {
        $this->app = new Application($this->getConfig());
    }

    public function getConfig($driver = 'db2')
    {
        $config = Yaml::parse(file_get_contents($this->dataDir . '/' .$driver . '/config.yml'));
        $config['parameters']['data_dir'] = $this->dataDir;

        $config['parameters']['db']['user'] = $this->getEnv($driver, 'DB_USER', true);
        $config['parameters']['db']['#password'] = $this->getEnv($driver, 'DB_PASSWORD', true);
        $config['parameters']['db']['host'] = $this->getEnv($driver, 'DB_HOST');
        $config['parameters']['db']['port'] = $this->getEnv($driver, 'DB_PORT');
        $config['parameters']['db']['database'] = $this->getEnv($driver, 'DB_DATABASE');
        $config['parameters']['extractor_class'] = 'DB2';
        return $config;
    }

    public function testRun()
    {
        $result = $this->app->run();
        $expectedCsvFile = ROOT_PATH . '/tests/data/projact.csv';
        $outputCsvFile = $this->dataDir . '/out/tables/' . $result['imported'][0] . '.csv';

        $outputManifestFile = $this->dataDir . '/out/tables/' . $result['imported'][0] . '.csv.manifest';

        $this->assertEquals('ok', $result['status']);
        $this->assertFileExists($outputCsvFile);
        $this->assertFileExists($outputManifestFile);
        $this->assertEquals(file_get_contents($expectedCsvFile), file_get_contents($outputCsvFile));
    }

    public function testEscaping()
    {
        $expectedCsvFile = ROOT_PATH . '/tests/data/escaping.csv';

        // load data to DB
        $db = $this->getConnection();
        $db->exec("DROP TABLE escaping");
        $db->exec("CREATE TABLE escaping (col1 VARCHAR(255) NOT NULL, col2 VARCHAR(255) NOT NULL)");

        $fh = fopen($expectedCsvFile, 'r+');
        $i = 0;
        while ($row = fgetcsv($fh, null, ",", '"', '\\')) {
            if ($i != 0) {
                $res = $db->exec(sprintf("INSERT INTO escaping VALUES ('%s', '%s')", $row[0], $row[1]));
            }
            $i++;
        }

        $config = $this->getConfig();
        $config['parameters']['tables'][0] = [
            'name' => 'escaping',
            'query' => 'SELECT * FROM DB2INST1.ESCAPING',
            'outputTable' => 'in.c-main.db2escaping',
            'incremental' => false,
            'primaryKey' => null,
            'enabled' => true
        ];

        $this->app = new Application($config);

        $result = $this->app->run();
        $outputCsvFile = $this->dataDir . '/out/tables/' . $result['imported'][0] . '.csv';
        $outputManifestFile = $this->dataDir . '/out/tables/' . $result['imported'][0] . '.csv.manifest';

        $this->assertEquals('ok', $result['status']);
        $this->assertFileExists($outputCsvFile);
        $this->assertFileExists($outputManifestFile);
        $this->assertEquals(file_get_contents($expectedCsvFile), file_get_contents($outputCsvFile));
    }

    private function getConnection()
    {
        $config = $this->getConfig()['parameters']['db'];
        $database = $config['database'];
        $user = $config['user'];
        $password = $config['password'];
        $hostname = $config['host'];
        $port = $config['port'];

        $dsn = sprintf(
            "odbc:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=%s;HOSTNAME=%s;PORT=%s;PROTOCOL=TCPIP;",
            $database,
            $hostname,
            $port
        );

        return new \PDO($dsn, $user, $password);
    }

    public function testTestConnection()
    {
        $config = $this->getConfig();
        $config['action'] = 'testConnection';
        $app = new Application($config);

        $result = $app->run();
        $this->assertEquals('success', $result['status']);
    }

    public function testTestConnectionFailed()
    {
        $config = $this->getConfig();
        $config['parameters']['db']['user'] = 'thisUserDoesNotExist';
        $config['parameters']['db']['password'] = 'wrongPasswordObviously';
        $config['action'] = 'testConnection';
        $app = new Application($config);

        $exception = null;
        try {
            $result = $app->run();
        } catch(UserException $e) {
            $exception = $e;
        }

        $this->assertContains('Connection failed', $exception->getMessage());
    }

    protected function getEnv($driver, $suffix, $required = false)
    {
        $env = strtoupper($driver) . '_' . $suffix;
        if ($required) {
            if (false === getenv($env)) {
                throw new \Exception($env . " environment variable must be set.");
            }
        }
        return getenv($env);
    }
}
