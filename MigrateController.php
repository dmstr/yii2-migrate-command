<?php
/**
 * @link http://www.diemeisterei.de/
 * @copyright Copyright (c) 2014 diemeisterei GmbH, Stuttgart
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\console\controllers;
use yii\helpers\Console;

/**
 * Manages application and extension migrations (dmstr/yii2-migrate-command).
 *
 * Spin-off from https://github.com/yiisoft/yii2/pull/3273/files
 *
 * A migration means a set of persistent changes to the application environment
 * that is shared among different developers. For example, in an application
 * backed by a database, a migration may refer to a set of changes to
 * the database, such as creating a new table, adding a new table column.
 *
 * This command provides support for tracking the migration history, upgrading
 * or downloading with migrations, and creating new migration skeletons.
 *
 * The migration history is stored in a database table named
 * as [[migrationTable]]. The table will be automatically created the first time
 * this command is executed, if it does not exist. You may also manually
 * create it as follows:
 *
 * ~~~
 * CREATE TABLE migration (
 *     version varchar(180) PRIMARY KEY,
 *     alias varchar(180),
 *     apply_time integer
 * )
 * ~~~
 *
 * Below are some common usages of this command:
 *
 * ~~~
 * # creates a new migration named 'create_user_table'
 * yii migrate/create create_user_table
 *
 * # applies ALL new migrations
 * yii migrate
 *
 * # reverts the last applied migration
 * yii migrate/down
 * ~~~
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Tobias Munk <schmunk@usrbin.de>
 * @author Jan Waś <janek.jan@gmail.com>
 * @since 2.0
 */
class MigrateController extends \yii\console\controllers\MigrateController
{
    /**
     * @var array additional aliases of migration directories
     */
    public $migrationLookup = [];
    /**
     * @var boolean lookup all application migration paths
     */
    public $disableLookup = false;

    /**
     * @inheritdoc
     */
    public function options($actionId)
    {
        return array_merge(
            parent::options($actionId),
            ['migrationLookup', 'disableLookup'] // global for all actions
        );
    }

    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return \yii\db\Migration the migration instance
     */
    protected function createMigration($class)
    {
        $class = json_decode($class);
        $alias = $class->alias;
        $class = $class->migration;
        $file = \Yii::getAlias($alias) . DIRECTORY_SEPARATOR . $class . '.php';
        require_once($file);

        return new $class(['db' => $this->db]);
    }

    /**
     * Returns the migrations that are not applied.
     * @return array list of new migrations
     */
    protected function getNewMigrations()
    {
        $applied = [];
        foreach ($this->getMigrationHistory(null) as $version => $time) {
            $migration = json_decode($version);
            $applied[substr($migration->migration, 1, 13)] = true;
        }

        if ($this->migrationPath && $this->disableLookup) {
            $directories = [$this->migrationPath];
        } else {
            $directories = \yii\helpers\ArrayHelper::merge([$this->migrationPath], $this->migrationLookup);
        }

        $migrations = [];


        $this->stdout("\nLookup:\n");

        foreach ($directories AS $alias) {
            $dir = \Yii::getAlias($alias);
            $handle = opendir($dir);

            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/', $file, $matches) && !isset($applied[$matches[2]])
                    && is_file($path)
                ) {
                    $migrations[$matches[1]] = json_encode(['migration' => $matches[1], 'alias' => $alias]);
                }
            }
            closedir($handle);
            $this->stdout("    " . $alias . " (" . \Yii::getAlias($alias) . ")\n");
        }
        ksort($migrations);

        return array_values($migrations);
    }

    /**
     * @inheritdoc
     */
    protected function getMigrationHistory($limit)
    {
        if ($this->db->schema->getTableSchema($this->migrationTable, true) === null) {
            $this->createMigrationHistoryTable();
        }
        $query = new \yii\db\Query;
        $rows = $query->select(['version', 'alias', 'apply_time'])
            ->from($this->migrationTable)
            ->orderBy('apply_time DESC, version DESC')
            ->limit($limit)
            ->createCommand($this->db)
            ->queryAll();
        $history = [];
        foreach ($rows as $row) {
            if ($row['version'] === self::BASE_MIGRATION) {
                continue;
            }
            $migration = ['alias' => $row['alias'], 'migration' => $row['version']];
            $history[json_encode($migration)] = $row['apply_time'];
        }

        return $history;
    }

    /**
     * Creates the migration history table.
     */
    protected function createMigrationHistoryTable()
    {
        $tableName = $this->db->schema->getRawTableName($this->migrationTable);
        $this->stdout("Creating migration history table \"$tableName\"...", Console::FG_YELLOW);
        $this->db->createCommand()->createTable($this->migrationTable, [
            'version' => 'varchar(180) NOT NULL PRIMARY KEY',
            'alias' => 'varchar(180) NOT NULL',
            'apply_time' => 'integer',
        ])->execute();
        $this->db->createCommand()->insert($this->migrationTable, [
            'version' => self::BASE_MIGRATION,
            'alias' => $this->migrationPath,
            'apply_time' => time(),
        ])->execute();
        $this->stdout("Done.\n", Console::FG_GREEN);
    }

    /**
     * @inheritdoc
     */
    protected function addMigrationHistory($version)
    {
        $migration = json_decode($version);
        $command = $this->db->createCommand();
        $command->insert($this->migrationTable, [
            'version' => $migration->migration,
            'alias' => $migration->alias,
            'apply_time' => time(),
        ])->execute();
    }

    /**
     * @inheritdoc
     */
    protected function removeMigrationHistory($version)
    {
        $migration = json_decode($version);
        $command = $this->db->createCommand();
        $command->delete($this->migrationTable, [
            'version' => $migration->migration,
            'alias' => $migration->alias,
        ])->execute();
    }
}
