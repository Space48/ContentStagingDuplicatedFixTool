<?php

use Magento\Framework\App\Bootstrap;
require 'app/bootstrap.php';
new ClearContentStaging;

class ClearContentStaging
{
    /**
     * @var
     */
    private $connection;

    /**
     * @var array
     */
    private $dataTables;

    /**
     * @var bool|resource
     */
    private $sqlBackup;

    /**
     * ClearContentStaging constructor.
     */
    public function __construct() {
        $this->dataTables = $this->getDataTables();
        $this->connection = $this->getConnection();
        $this->sqlBackup = $this->getSqlBackup();
        $this->execute();
    }

    /**
     * Execute script
     */
    public function execute() {

        foreach ($this->dataTables as $data) {

            $duplicates = $this->getDuplicates($data);

            foreach ($duplicates as $duplicate) {

                $entities = $this->getEntities($duplicate, $data);

                foreach ($entities as $entity) {
                    $this->backupEntity($entity, $data['table']);
                }

                $this->deleteDuplicateEntities($data, $duplicate, $entities[0]['row_id']);
            }
        }
    }

    /**
     * @param $entity
     * @param $dataTable
     *
     * Add Insert SQL row into sql file
     */
    private function backupEntity($entity, $dataTable) {
        $string = $this->generateEntityString($entity, $dataTable);

        fwrite($this->sqlBackup, $string . PHP_EOL);
    }

    /**
     * @param $data
     * @param $duplicate
     * @param $correctRowId
     *
     * Delete all duplicate entries (leaving the row id passed in as $correctRowId)
     */
    private function deleteDuplicateEntities($data, $duplicate, $correctRowId) {
        $sql = 'DELETE FROM ' . $data['table'] . '
        WHERE updated_in = ' . $duplicate['updated_in'] . ' AND ' . $data['column_id'] . ' = ' . $duplicate[$data['column_id']] . ' AND row_id != ' . $correctRowId;

        $this->connection->query($sql);
    }

    /**
     * @return mixed
     *
     * Get MySQL connection from Magento
     */
    private function getConnection() {

        $bootstrap = Bootstrap::create(BP, $_SERVER);

        $objectManager = $bootstrap->getObjectManager();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        return $resource->getConnection();
    }

    /**
     * @return array
     *
     * Returns all tables associated with content staging, and column names
     */
    private function getDataTables() {

        return [
            [
                'table' => 'catalog_category_entity',
                'column_id' => 'entity_id',
                'order_by' => 'updated_at'
            ], [
                'table' => 'catalog_product_entity',
                'column_id' => 'entity_id',
                'order_by' => 'updated_at'
            ], [
                'table' => 'salesrule',
                'column_id' => 'rule_id',
                'row_id' => 'updated_at'
            ], [
                'table' => 'cms_block',
                'column_id' => 'block_id',
                'order_by' => 'update_time'
            ], [
                'table' => 'cms_page',
                'column_id' => 'page_id',
                'order_by' => 'update_time'
            ]
        ];
    }

    /**
     * @param $data
     * @return mixed
     *
     * Returns entries from a table where updated_in and the id column are both duplicated
     */
    private function getDuplicates($table) {
        $sql = 'SELECT updated_in, ' . $table['column_id'] . ', count(*)
            FROM ' . $table['table'] . '
            GROUP BY updated_in, ' . $table['column_id'] . ' 
            HAVING count(*) > 1;';

        return $this->connection->fetchAll($sql);
    }

    /**
     * @param $duplicate
     * @param $data
     * @return mixed
     *
     * Gets all the records in the DB associated to the duplicate, the most recently updated one will be first
     */
    private function getEntities($duplicate, $data) {
        $sql = 'SELECT * 
        FROM ' . $data['table'] . '
        WHERE updated_in = ' . $duplicate['updated_in'] . ' AND ' . $data['column_id'] . ' = ' . $duplicate[$data['column_id']] . '
        ORDER BY ' . $data['order_by'] . ' DESC;';

        return $this->connection->fetchAll($sql);
    }

    /**
     * @return bool|resource
     *
     * Open SQL file to write backup to
     */
    private function getSqlBackup() {
        return fopen('content-staging-duplicates-' . date('mdy') . '.sql', 'w+');
    }

    /**
     * @param $entity
     * @param $dataTable
     * @return string
     *
     * Generates a string to go in the SQL backup for row to be deleted
     */
    private function generateEntityString($entity, $dataTable) {
        $string = 'INSERT INTO ' . $dataTable . ' (';

        foreach ($entity as $key => $value) {
            $string .= '\'$key\', ';
        }
        $string = trim($string, ', ');

        $string .= ') VALUES (';

        foreach ($entity as $key => $value) {
            $string .= '\'$value\', ';
        }
        $string = trim($string, ', ');
        $string .= ');';

        return $string;
    }
}