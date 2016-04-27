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

        $this->logger->info("Exporting to " . $outputTable);
        $csv = $this->createOutputCsv($outputTable);

        // write header and first line
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $resultRow = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (is_array($resultRow) && !empty($resultRow)) {
                $csv->writeRow(array_keys($resultRow));
                $csv->writeRow($resultRow);

                // write the rest
                while ($resultRow = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $csv->writeRow($resultRow);
                }
            } else {
                $this->logger->warning("Query returned empty result. Nothing was imported.");
            }
        } catch (\PDOException $e) {
            throw new UserException("DB query failed: " . $e->getMessage(), 0, $e);
        }

        if ($this->createManifest($table) === false) {
            throw new ApplicationException("Unable to create manifest", 0, null, [
                'table' => $table
            ]);
        }

        return $outputTable;
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

}
