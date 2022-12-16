<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/02/16
 * Time: 17:49
 */

namespace Keboola\DbExtractor\Extractor;

use Keboola\DbExtractor\Exception\ApplicationException;
use Keboola\DbExtractor\Exception\UserException;

class DB2 extends Extractor
{
    private $dbConfig;

    public function createConnection($dbParams)
    {
        $this->dbConfig = $dbParams;

        // check params
        foreach (['host', 'database', 'user', 'password'] as $r) {
            if (!isset($dbParams[$r])) {
                throw new UserException(sprintf("Parameter %s is missing.", $r));
            }
        }

        // convert errors to PDOExceptions
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ];

        $dsn = sprintf(
            "odbc:DRIVER={IBM i Access ODBC Driver};SYSTEM=%s;DATABASE=%s;PROTOCOL=TCPIP;",
            $dbParams['host'],
            $dbParams['database']
        );

        $pdo = new \PDO($dsn, $dbParams['user'], $dbParams['password'], $options);

        return $pdo;
    }

    /**
     * @param array $table
     * @return $outputTable output table name
     * @throws ApplicationException
     * @throws UserException
     * @throws \Keboola\Csv\Exception
     */
    public function export(array $table)
    {
        if (empty($table['outputTable'])) {
            throw new UserException("Missing attribute 'outputTable'");
        }
        $outputTable = $table['outputTable'];
        if (empty($table['query'])) {
            throw new UserException("Missing attribute 'query'");
        }
        $query = $table['query'];
        $encoding = isset($table['encoding']) ? $table['encoding'] : null;

        $this->logger->info("Exporting to " . $outputTable);
        $csv = $this->createOutputCsv($outputTable);

        // write header and first line
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $resultRow = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (is_array($resultRow) && !empty($resultRow)) {
                $csv->writeRow($this->encode(array_keys($resultRow), $encoding));
                $csv->writeRow($this->encode($resultRow, $encoding));

                // write the rest
                while ($resultRow = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ($encoding !== null) {
                        $resultRow = $this->encode($resultRow, $encoding);
                    }
                    $csv->writeRow($resultRow);
                }

                if ($this->createManifest($table) === false) {
                    throw new ApplicationException("Unable to create manifest", 0, null, [
                        'table' => $table
                    ]);
                }
            } else {
                $this->logger->warning("Query returned empty result. Nothing was imported.");
            }
        } catch (\PDOException $e) {
            throw new UserException("DB query failed: " . $e->getMessage(), 0, $e);
        }

        return $outputTable;
    }

    private function encode($row, $encoding)
    {
        return array_map(function ($item) use ($encoding) {
            if (is_numeric($item)) {
                return $item;
            }
            return ($encoding === null) ? $item : mb_convert_encoding($item, $encoding['to'], $encoding['from']);
        }, $row);
    }

    private function replaceNull($row, $value)
    {
        foreach ($row as $k => $v) {
            if ($v === null) {
                $row[$k] = $value;
            }
        }

        return $row;
    }

    public function testConnection()
    {
        $this->db->query('SELECT 1 FROM sysibm.sysdummy1');
    }

    protected function createManifest($table)
    {
        $outFilename = $this->dataDir . '/out/tables/' . $table['outputTable'] . '.csv.manifest';

        $manifestData = [
            'destination' => $table['outputTable'],
            'incremental' => $table['incremental']
        ];

        if (!empty($table['primaryKey'])) {
            $manifestData['primary_key'] = $table['primaryKey'];
        }

        return file_put_contents($outFilename, json_encode($manifestData));
    }
}
