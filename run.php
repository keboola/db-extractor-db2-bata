<?php

use Keboola\DbExtractor\Application;
use Keboola\DbExtractor\Configuration\DB2ConfigDefinition;
use Keboola\DbExtractor\Exception\ApplicationException;
use Keboola\DbExtractor\Exception\UserException;
use Symfony\Component\Yaml\Yaml;

define('APP_NAME', 'ex-db-db2');
define('ROOT_PATH', __DIR__);

require_once(dirname(__FILE__) . "/vendor/keboola/db-extractor-common/bootstrap.php");

try {
    $arguments = getopt("d::", ["data::"]);
    if (!isset($arguments["data"])) {
        throw new UserException('Data folder not set.');
    }

    $config = json_decode(file_get_contents($arguments["data"] . "/config.json"), true);
    $config['parameters']['data_dir'] = $arguments['data'];
    $config['parameters']['extractor_class'] = 'DB2';

    $app = new Application($config);
    $app->setConfigDefinition(new DB2ConfigDefinition());

    echo json_encode($app->run());
} catch(UserException $e) {
    $app['logger']->log('error', $e->getMessage(), (array) $e->getData());
    exit(1);
} catch(ApplicationException $e) {
    $app['logger']->log('error', $e->getMessage(), (array) $e->getData());
    exit($e->getCode() > 1 ? $e->getCode(): 2);
} catch(\Exception $e) {
    $app['logger']->log('error', $e->getMessage(), [
        'errFile' => $e->getFile(),
        'errLine' => $e->getLine()
    ]);
    exit(2);
}

exit(0);
