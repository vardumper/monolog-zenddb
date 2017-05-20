<?php

namespace ZendDbHandler;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Zend\Db\Adapter\Adapter;
use Zend\Stdlib\ArrayUtils;
/**
 * This class is a handler for Monolog, which can be used
 * to write records in a MySQL table
 *
 * Class MySQLHandler
 * @package wazaari\MysqlHandler
 */
class ZendDbHandler extends AbstractProcessingHandler {
    
    /**
     * @var bool defines whether the MySQL connection is been initialized
     */
    private $initialized = false;
    
    /**
     * @var Zend\Db\Adapter\Adapter $adapter
     */
    protected $adapter;
    
    /**
     * @var PDOStatement statement to insert a new record
     */
    private $statement;
    
    /**
     * @var string the table to store the logs in
     */
    private $table = 'logs';
    
    /**
     * @var string[] additional fields to be stored in the database
     *
     * For each field $field, an additional context field with the name $field
     * is expected along the message, and further the database needs to have these fields
     * as the values are stored in the column name $field.
     */
    private $additionalFields = array();
    
    /**
     * Constructor of this class, sets the PDO and calls parent constructor
     *
     * @param PDO $pdo                  PDO Connector for the database
     * @param bool $table               Table in the database to store the logs in
     * @param array $additionalFields   Additional Context Parameters to store in database
     * @param bool|int $level           Debug level which this handler should store
     * @param bool $bubble
     */
    public function __construct(Adapter $adapter = null, $table, $additionalFields = array(), $level = Logger::DEBUG, $bubble = true) {
        if(!is_null($adapter)) {
            $this->adapter = $adapter;
        }
        $this->table = $table;
        $this->additionalFields = $additionalFields;
        parent::__construct($level, $bubble);
    }
    
    /**
     * Initializes this handler by creating the table if it not exists
     */
    private function initialize() {
        $this->adapter->query(
            'CREATE TABLE IF NOT EXISTS `'.$this->table.'` '
            .'(channel VARCHAR(255), level INTEGER, message LONGTEXT, time INTEGER UNSIGNED)', Adapter::QUERY_MODE_EXECUTE
            );
        
        //Read out actual columns
        $actualFields = array();
        $rs = $this->adapter->query('DESCRIBE `'.$this->table.'`;', Adapter::QUERY_MODE_EXECUTE);
        
        //         file_put_contents($this->table.'.log', json_encode(ArrayUtils::iteratorToArray($rs)));
        $fields = ArrayUtils::iteratorToArray($rs);
        foreach($fields as $field) {
            $actualFields[] = $field['Field'];
        }
        
        //Calculate changed entries
        $removedColumns = array_diff($actualFields, $this->additionalFields, array('channel', 'level', 'message', 'time'));
        $addedColumns = array_diff($this->additionalFields, $actualFields);
        //Remove columns
        if (!empty($removedColumns)) foreach ($removedColumns as $c) {
            $this->adapter->query('ALTER TABLE `'.$this->table.'` DROP `'.$c.'`;', Adapter::QUERY_MODE_EXECUTE);
        }
        
        //Add columns
        if (!empty($addedColumns)) foreach ($addedColumns as $c) {
            $this->adapter->query('ALTER TABLE `'.$this->table.'` add `'.$c.'` TEXT NULL DEFAULT NULL;', Adapter::QUERY_MODE_EXECUTE);
        }
        
        //Prepare statement
        $columns = "";
        $fields = "";
        foreach ($this->additionalFields as $f) {
            $columns.= ", $f";
            $fields.= ", :$f";
        }
        
        $this->statement = $this->adapter->createStatement(
            'INSERT INTO `'.$this->table.'` (channel, level, message, time'.$columns.') VALUES (:channel, :level, :message, :time'.$fields.')'
            );
        $this->initialized = true;
    }
    
    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  $record[]
     * @return void
     */
    protected function write(array $record) {
        if (!$this->initialized) {
            $this->initialize();
        }
        
        //'context' contains the array
        $contentArray = array_merge(array(
            'channel' => $record['channel'],
            'level' => $record['level'],
            'message' => $record['message'],
            'time' => $record['datetime']->format('U')
        ), $record['context']);
        
        $this->statement->execute($contentArray);
    }
}