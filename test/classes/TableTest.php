<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests;

use PhpMyAdmin\ConfigStorage\RelationParameters;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Dbal\Connection;
use PhpMyAdmin\Index;
use PhpMyAdmin\ListDatabase;
use PhpMyAdmin\Query\Cache;
use PhpMyAdmin\Table;
use PhpMyAdmin\Tests\Stubs\DbiDummy;
use PhpMyAdmin\Tests\Stubs\DummyResult;

/**
 * @covers \PhpMyAdmin\Table
 */
class TableTest extends AbstractTestCase
{
    /**
     * Configures environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        /**
         * SET these to avoid undefined index error
         */
        $GLOBALS['server'] = 0;
        $GLOBALS['cfg']['Server']['DisableIS'] = false;
        $GLOBALS['cfg']['MaxExactCount'] = 100;
        $GLOBALS['cfg']['MaxExactCountViews'] = 100;
        $GLOBALS['cfg']['Server']['pmadb'] = 'pmadb';
        $GLOBALS['sql_auto_increment'] = true;
        $GLOBALS['sql_if_not_exists'] = true;
        $GLOBALS['sql_drop_table'] = true;
        $GLOBALS['cfg']['Server']['table_uiprefs'] = 'pma__table_uiprefs';

        $sql_isView_true = 'SELECT 1'
            . ' FROM information_schema.VIEWS'
            . ' WHERE TABLE_SCHEMA = \'PMA\''
            . ' AND TABLE_NAME = \'PMA_BookMark\'';

        $sql_isView_false = 'SELECT 1'
            . ' FROM information_schema.VIEWS'
            . ' WHERE TABLE_SCHEMA = \'PMA\''
            . ' AND TABLE_NAME = \'PMA_BookMark_2\'';

        $sql_isUpdatableView_true = 'SELECT 1'
            . ' FROM information_schema.VIEWS'
            . ' WHERE TABLE_SCHEMA = \'PMA\''
            . ' AND TABLE_NAME = \'PMA_BookMark\''
            . ' AND IS_UPDATABLE = \'YES\'';

        $sql_isUpdatableView_false = 'SELECT 1'
            . ' FROM information_schema.VIEWS'
            . ' WHERE TABLE_SCHEMA = \'PMA\''
            . ' AND TABLE_NAME = \'PMA_BookMark_2\''
            . ' AND IS_UPDATABLE = \'YES\'';

        $sql_analyzeStructure_true = 'SELECT COLUMN_NAME, DATA_TYPE'
            . ' FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = \'PMA\''
            . ' AND TABLE_NAME = \'PMA_BookMark\'';

        $sql_copy_data = 'SELECT 1'
            . ' FROM information_schema.VIEWS'
            . ' WHERE TABLE_SCHEMA = \'PMA_new\''
            . ' AND TABLE_NAME = \'PMA_BookMark_new\'';

        $getUniqueColumns_sql = 'SHOW INDEXES FROM `PMA`.`PMA_BookMark`';

        $fetchResult = [
            [
                $sql_analyzeStructure_true,
                null,
                null,
                Connection::TYPE_USER,
                [
                    [
                        'COLUMN_NAME' => 'COLUMN_NAME',
                        'DATA_TYPE' => 'DATA_TYPE',
                    ],
                ],
            ],
            [
                $getUniqueColumns_sql . ' WHERE (Non_unique = 0)',
                [
                    'Key_name',
                    null,
                ],
                'Column_name',
                Connection::TYPE_USER,
                [
                    ['index1'],
                    ['index3'],
                    ['index5'],
                ],
            ],
            [
                $getUniqueColumns_sql,
                'Column_name',
                'Column_name',
                Connection::TYPE_USER,
                [
                    'column1',
                    'column3',
                    'column5',
                    'ACCESSIBLE',
                    'ADD',
                    'ALL',
                ],
            ],
            [
                'SHOW COLUMNS FROM `PMA`.`PMA_BookMark`',
                'Field',
                'Field',
                Connection::TYPE_USER,
                [
                    'column1',
                    'column3',
                    'column5',
                    'ACCESSIBLE',
                    'ADD',
                    'ALL',
                ],
            ],
            [
                'SHOW COLUMNS FROM `PMA`.`PMA_BookMark`',
                null,
                null,
                Connection::TYPE_USER,
                [
                    [
                        'Field' => 'COLUMN_NAME1',
                        'Type' => 'INT(10)',
                        'Null' => 'NO',
                        'Key' => '',
                        'Default' => null,
                        'Extra' => '',
                    ],
                    [
                        'Field' => 'COLUMN_NAME2',
                        'Type' => 'INT(10)',
                        'Null' => 'YES',
                        'Key' => '',
                        'Default' => null,
                        'Extra' => 'STORED GENERATED',
                    ],
                ],
            ],
            [
                'SHOW TRIGGERS FROM `PMA` LIKE \'PMA_BookMark\';',
                null,
                null,
                Connection::TYPE_USER,
                [
                    [
                        'Trigger' => 'name1',
                        'Event' => 'INSERT',
                        'Table' => 'PMA_BookMark',
                        'Timing' => 'AFTER',
                        'Statement' => 'BEGIN END',
                        'Definer' => 'test_user@localhost',
                    ],
                    [
                        'Trigger' => 'name2',
                        'Event' => 'INSERT',
                        'Table' => 'PMA_BookMark',
                        'Timing' => 'AFTER',
                        'Statement' => 'BEGIN END',
                        'Definer' => 'test_user@localhost',
                    ],
                    [
                        'Trigger' => 'name3',
                        'Event' => 'INSERT',
                        'Table' => 'PMA_BookMark',
                        'Timing' => 'AFTER',
                        'Statement' => 'BEGIN END',
                        'Definer' => 'test_user@localhost',
                    ],
                ],
            ],
            [
                'SHOW TRIGGERS FROM `PMA` LIKE \'PMA_.BookMark\';',
                null,
                null,
                Connection::TYPE_USER,
                [
                    [
                        'Trigger' => 'name1',
                        'Event' => 'INSERT',
                        'Table' => 'PMA_.BookMark',
                        'Timing' => 'AFTER',
                        'Statement' => 'BEGIN END',
                        'Definer' => 'test_user@localhost',
                    ],
                    [
                        'Trigger' => 'name2',
                        'Event' => 'INSERT',
                        'Table' => 'PMA_.BookMark',
                        'Timing' => 'AFTER',
                        'Statement' => 'BEGIN END',
                        'Definer' => 'test_user@localhost',
                    ],
                    [
                        'Trigger' => 'name3',
                        'Event' => 'INSERT',
                        'Table' => 'PMA_.BookMark',
                        'Timing' => 'AFTER',
                        'Statement' => 'BEGIN END',
                        'Definer' => 'test_user@localhost',
                    ],
                ],
            ],
        ];

        $fetchValue = [
            [
                $sql_isView_true,
                0,
                Connection::TYPE_USER,
                ['PMA_BookMark'],
            ],
            [
                $sql_copy_data,
                0,
                Connection::TYPE_USER,
                [],
            ],
            [
                $sql_isView_false,
                0,
                Connection::TYPE_USER,
                [],
            ],
            [
                $sql_isUpdatableView_true,
                0,
                Connection::TYPE_USER,
                ['PMA_BookMark'],
            ],
            [
                $sql_isUpdatableView_false,
                0,
                Connection::TYPE_USER,
                [],
            ],
        ];

        $resultStub = $this->createMock(DummyResult::class);

        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $databaseList = $this->createStub(ListDatabase::class);
        $databaseList->method('exists')->willReturn(true);
        $dbi->expects($this->any())->method('getDatabaseList')->willReturn($databaseList);

        $dbi->expects($this->any())->method('fetchResult')
            ->will($this->returnValueMap($fetchResult));

        $dbi->expects($this->any())->method('fetchValue')
            ->will($this->returnValueMap($fetchValue));

        $cache = new Cache();
        $dbi->expects($this->any())->method('getCache')
            ->will($this->returnValue($cache));

        $databases = [];
        $database_name = 'PMA';
        $databases[$database_name]['SCHEMA_TABLES'] = 1;
        $databases[$database_name]['SCHEMA_TABLE_ROWS'] = 3;
        $databases[$database_name]['SCHEMA_DATA_LENGTH'] = 5;
        $databases[$database_name]['SCHEMA_MAX_DATA_LENGTH'] = 10;
        $databases[$database_name]['SCHEMA_INDEX_LENGTH'] = 10;
        $databases[$database_name]['SCHEMA_LENGTH'] = 10;

        $dbi->expects($this->any())->method('getTablesFull')
            ->will($this->returnValue($databases));

        $resultStub->expects($this->any())
            ->method('numRows')
            ->will($this->returnValue(20));

        $dbi->expects($this->any())->method('tryQuery')
            ->will($this->returnValue($resultStub));

        $dbi->expects($this->any())->method('query')
            ->will($this->returnValue($resultStub));

        $dbi->expects($this->any())->method('insertId')
            ->will($this->returnValue(10));

        $resultStub->expects($this->any())->method('fetchAssoc')
            ->will($this->returnValue([]));

        $value = ['Auto_increment' => 'Auto_increment'];
        $dbi->expects($this->any())->method('fetchSingleRow')
            ->will($this->returnValue($value));

        $resultStub->expects($this->any())->method('fetchRow')
            ->will($this->returnValue([]));

        $dbi->expects($this->any())->method('escapeString')
            ->will($this->returnArgument(0));

        $dbi->expects($this->any())->method('quoteString')
            ->will($this->returnCallback(static function (string $string) {
                return "'" . $string . "'";
            }));

        $GLOBALS['dbi'] = $dbi;
    }

    /**
     * Test object creating
     */
    public function testCreate(): void
    {
        $table = new Table('table1', 'pma_test', $GLOBALS['dbi']);
        $this->assertInstanceOf(Table::class, $table);
    }

    /**
     * Test for constructor
     */
    public function testConstruct(): void
    {
        $table = new Table('PMA_BookMark', 'PMA', $GLOBALS['dbi']);
        $this->assertEquals(
            'PMA_BookMark',
            $table->__toString()
        );
        $this->assertEquals(
            'PMA_BookMark',
            $table->getName()
        );
        $this->assertEquals(
            'PMA',
            $table->getDbName()
        );
        $this->assertEquals(
            'PMA.PMA_BookMark',
            $table->getFullName()
        );
    }

    /**
     * Test getName & getDbName
     */
    public function testGetName(): void
    {
        $table = new Table('table1', 'pma_test', $GLOBALS['dbi']);
        $this->assertEquals(
            'table1',
            $table->getName()
        );
        $this->assertEquals(
            '`table1`',
            $table->getName(true)
        );
        $this->assertEquals(
            'pma_test',
            $table->getDbName()
        );
        $this->assertEquals(
            '`pma_test`',
            $table->getDbName(true)
        );
    }

    /**
     * Test getLastError & getLastMessage
     */
    public function testGetLastErrorAndMessage(): void
    {
        $table = new Table('table1', 'pma_test', $GLOBALS['dbi']);
        $table->errors[] = 'error1';
        $table->errors[] = 'error2';
        $table->errors[] = 'error3';

        $table->messages[] = 'messages1';
        $table->messages[] = 'messages2';
        $table->messages[] = 'messages3';

        $this->assertEquals(
            'error3',
            $table->getLastError()
        );
        $this->assertEquals(
            'messages3',
            $table->getLastMessage()
        );
    }

    /**
     * Test name validation
     *
     * @param string $name          name to test
     * @param bool   $result        expected result
     * @param bool   $is_backquoted is backquoted
     *
     * @dataProvider dataValidateName
     */
    public function testValidateName(string $name, bool $result, bool $is_backquoted = false): void
    {
        $this->assertEquals(
            $result,
            Table::isValidName($name, $is_backquoted)
        );
    }

    /**
     * Data provider for name validation
     */
    public function dataValidateName(): array
    {
        return [
            [
                'test',
                true,
            ],
            [
                'te/st',
                false,
            ],
            [
                'te.st',
                false,
            ],
            [
                'te\\st',
                false,
            ],
            [
                'te st',
                false,
            ],
            [
                '  te st',
                true,
                true,
            ],
            [
                'test ',
                false,
            ],
            [
                'te.st',
                false,
            ],
            [
                'test ',
                false,
                true,
            ],
            [
                'te.st ',
                false,
                true,
            ],
        ];
    }

    /**
     * Test for isView
     */
    public function testIsView(): void
    {
        $table = new Table('', '', $GLOBALS['dbi']);
        $this->assertFalse(
            $table->isView()
        );

        //validate that it is the same as DBI fetchResult
        $table = new Table('PMA_BookMark', 'PMA', $GLOBALS['dbi']);
        $this->assertTrue(
            $table->isView()
        );

        $table = new Table('PMA_BookMark_2', 'PMA', $GLOBALS['dbi']);
        $this->assertFalse(
            $table->isView()
        );
    }

    /**
     * Test for generateFieldSpec
     */
    public function testGenerateFieldSpec(): void
    {
        //type is BIT
        $name = 'PMA_name';
        $type = 'BIT';
        $length = '12';
        $attribute = 'PMA_attribute';
        $collation = 'PMA_collation';
        $null = 'YES';
        $default_type = 'USER_DEFINED';
        $default_value = '12';
        $extra = 'AUTO_INCREMENT';
        $comment = 'PMA_comment';
        $virtuality = '';
        $expression = '';
        $move_to = '-first';

        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` BIT(12) PMA_attribute NULL DEFAULT b\'10\' AUTO_INCREMENT COMMENT \'PMA_comment\' FIRST',
            $query
        );

        //type is DOUBLE
        $type = 'DOUBLE';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` DOUBLE(12) PMA_attribute NULL DEFAULT \'12\' AUTO_INCREMENT COMMENT \'PMA_comment\' FIRST',
            $query
        );

        //type is BOOLEAN
        $type = 'BOOLEAN';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` BOOLEAN PMA_attribute NULL DEFAULT TRUE AUTO_INCREMENT COMMENT \'PMA_comment\' FIRST',
            $query
        );

        //$default_type is NULL
        $default_type = 'NULL';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` BOOLEAN PMA_attribute NULL DEFAULT NULL AUTO_INCREMENT COMMENT \'PMA_comment\' FIRST',
            $query
        );

        //$default_type is CURRENT_TIMESTAMP
        $default_type = 'CURRENT_TIMESTAMP';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` BOOLEAN PMA_attribute NULL DEFAULT CURRENT_TIMESTAMP '
            . "AUTO_INCREMENT COMMENT 'PMA_comment' FIRST",
            $query
        );

        //$default_type is current_timestamp()
        $default_type = 'current_timestamp()';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` BOOLEAN PMA_attribute NULL DEFAULT current_timestamp() '
            . "AUTO_INCREMENT COMMENT 'PMA_comment' FIRST",
            $query
        );

        // $type is 'TIMESTAMP(3), $default_type is CURRENT_TIMESTAMP(3)
        $type = 'TIMESTAMP';
        $length = '3';
        $extra = '';
        $default_type = 'CURRENT_TIMESTAMP';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` TIMESTAMP(3) PMA_attribute NULL DEFAULT CURRENT_TIMESTAMP(3) COMMENT \'PMA_comment\' FIRST',
            $query
        );

        $type = 'TIMESTAMP';
        $length = '';
        $extra = '';
        $default_type = 'USER_DEFINED';
        $default_value = '\'0000-00-00 00:00:00\'';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` TIMESTAMP PMA_attribute NULL DEFAULT \'0000-00-00 00:00:00\' COMMENT \'PMA_comment\' FIRST',
            $query
        );

        $type = 'TIMESTAMP';
        $length = '';
        $extra = '';
        $default_type = 'USER_DEFINED';
        $default_value = '\'0000-00-00 00:00:00.0\'';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` TIMESTAMP PMA_attribute NULL DEFAULT \'0000-00-00 00:00:00.0\' COMMENT \'PMA_comment\' FIRST',
            $query
        );

        $type = 'TIMESTAMP';
        $length = '';
        $extra = '';
        $default_type = 'USER_DEFINED';
        $default_value = '\'0000-00-00 00:00:00.000000\'';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` TIMESTAMP PMA_attribute NULL DEFAULT \'0000-00-00 00:00:00.000000\' '
            . "COMMENT 'PMA_comment' FIRST",
            $query
        );

        //$default_type is UUID
        $type = 'UUID';
        $default_type = 'UUID';
        $move_to = '';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            '',
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` UUID PMA_attribute NULL DEFAULT uuid()',
            $query
        );

        //$default_type is uuid()
        $type = 'UUID';
        $default_type = 'uuid()';
        $move_to = '';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            '',
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals(
            '`PMA_name` UUID PMA_attribute NULL DEFAULT uuid()',
            $query
        );

        //$default_type is NONE
        $type = 'BOOLEAN';
        $default_type = 'NONE';
        $extra = 'INCREMENT';
        $move_to = '-first';
        $query = Table::generateFieldSpec(
            $name,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );
        $this->assertEquals('`PMA_name` BOOLEAN PMA_attribute NULL INCREMENT COMMENT \'PMA_comment\' FIRST', $query);

        $default_type = 'NONE';
        $move_to = '-first';
        $query = Table::generateFieldSpec(
            'ids',
            'INT',
            '11',
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            'AUTO_INCREMENT',
            $comment,
            $virtuality,
            $expression,
            $move_to,
            ['id'],
            'id'
        );
        $this->assertEquals('`ids` INT(11) PMA_attribute NULL AUTO_INCREMENT COMMENT \'PMA_comment\' FIRST', $query);

        $default_type = 'NONE';
        $move_to = '-first';
        $query = Table::generateFieldSpec(
            'ids',
            'INT',
            '11',
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            'AUTO_INCREMENT',
            $comment,
            $virtuality,
            $expression,
            $move_to,
            ['othercol'],
            'id'
        );
        // Add primary key for AUTO_INCREMENT if missing
        $this->assertEquals(
            '`ids` INT(11) PMA_attribute NULL AUTO_INCREMENT '
            . "COMMENT 'PMA_comment' FIRST, add PRIMARY KEY (`ids`)",
            $query
        );

        $default_type = 'NONE';
        $move_to = '-first';
        $query = Table::generateFieldSpec(
            'id',
            'INT',
            '11',
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            'DEF',
            $comment,
            $virtuality,
            $expression,
            $move_to,
            ['id'],
            'id'
        );
        // Do not add PK
        $this->assertEquals('`id` INT(11) PMA_attribute NULL DEF COMMENT \'PMA_comment\' FIRST', $query);

        $default_type = 'NONE';
        $move_to = '-first';
        $query = Table::generateFieldSpec(
            'ids',
            'INT',
            '11',
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            'DEF',
            $comment,
            $virtuality,
            $expression,
            $move_to,
            ['id'],
            'id'
        );
        // Do not add PK
        $this->assertEquals('`ids` INT(11) PMA_attribute NULL DEF COMMENT \'PMA_comment\' FIRST', $query);

        $default_type = 'NONE';
        $move_to = '-first';
        $query = Table::generateFieldSpec(
            'ids',
            'INT',
            '11',
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            'DEF',
            $comment,
            $virtuality,
            $expression,
            $move_to,
            ['ids'],
            'id'
        );
        // Add it beaucause it is missing
        $this->assertEquals(
            '`ids` INT(11) PMA_attribute NULL DEF COMMENT \'PMA_comment\' FIRST, add PRIMARY KEY (`ids`)',
            $query
        );

        $default_type = 'NONE';
        $move_to = '-first';
        $query = Table::generateFieldSpec(
            'ids',
            'INT',
            '11',
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            'USER_DEFINED',
            $comment,
            'VIRTUAL',
            '1',
            $move_to,
            ['othercol'],
            'id'
        );
        // Do not add PK since it is not a AUTO_INCREMENT
        $this->assertEquals(
            '`ids` INT(11) PMA_attribute AS (1) VIRTUAL NULL USER_DEFINED COMMENT \'PMA_comment\' FIRST',
            $query
        );
    }

    /**
     * Test for duplicateInfo
     */
    public function testDuplicateInfo(): void
    {
        $get_fields = [
            'filed0',
            'field6',
        ];
        $where_fields = [
            'field2',
            'filed5',
        ];
        $new_fields = [
            'field3',
            'filed4',
        ];

        $relationParameters = RelationParameters::fromArray([
            'db' => 'PMA_db',
            'relwork' => true,
            'relation' => 'relation',
        ]);
        $_SESSION = ['relation' => [$GLOBALS['server'] => $relationParameters->toArray()]];

        $ret = Table::duplicateInfo('relwork', 'relation', $get_fields, $where_fields, $new_fields);
        $this->assertSame(-1, $ret);
    }

    /**
     * Test for isUpdatableView
     */
    public function testIsUpdatableView(): void
    {
        $table = new Table('', '', $GLOBALS['dbi']);
        $this->assertFalse(
            $table->isUpdatableView()
        );

        //validate that it is the same as DBI fetchResult
        $table = new Table('PMA_BookMark', 'PMA', $GLOBALS['dbi']);
        $this->assertTrue(
            $table->isUpdatableView()
        );

        $table = new Table('PMA_BookMark_2', 'PMA', $GLOBALS['dbi']);
        $this->assertFalse(
            $table->isUpdatableView()
        );
    }

    /**
     * Test for isMerge -- when there's no ENGINE info cached
     */
    public function testIsMergeCase1(): void
    {
        $tableObj = new Table('PMA_BookMark', 'PMA', $GLOBALS['dbi']);
        $this->assertEquals(
            '',
            $tableObj->isMerge()
        );

        $tableObj = new Table('PMA_BookMark', 'PMA', $GLOBALS['dbi']);
        $this->assertFalse(
            $tableObj->isMerge()
        );
    }

    /**
     * Test for isMerge -- when ENGINE info is MERGE
     */
    public function testIsMergeCase2(): void
    {
        $GLOBALS['dbi']->getCache()->cacheTableContent(
            ['PMA', 'PMA_BookMark'],
            ['ENGINE' => 'MERGE']
        );

        $tableObj = new Table('PMA_BookMark', 'PMA', $GLOBALS['dbi']);
        $this->assertTrue(
            $tableObj->isMerge()
        );
    }

    /**
     * Test for isMerge -- when ENGINE info is MRG_MYISAM
     */
    public function testIsMergeCase3(): void
    {
        $GLOBALS['dbi']->getCache()->cacheTableContent(
            ['PMA', 'PMA_BookMark'],
            ['ENGINE' => 'MRG_MYISAM']
        );

        $tableObj = new Table('PMA_BookMark', 'PMA', $GLOBALS['dbi']);
        $this->assertTrue(
            $tableObj->isMerge()
        );
    }

    /**
     * Test for Table::isMerge -- when ENGINE info is ISDB
     */
    public function testIsMergeCase4(): void
    {
        $tableObj = new Table('PMA_BookMark', 'PMA', $GLOBALS['dbi']);
        $this->assertFalse(
            $tableObj->isMerge()
        );
    }

    /**
     * Test for generateAlter
     */
    public function testGenerateAlter(): void
    {
        //parameter
        $oldcol = 'name';
        $newcol = 'new_name';
        $type = 'VARCHAR';
        $length = '2';
        $attribute = 'new_name';
        $collation = 'charset1';
        $null = 'YES';
        $default_type = 'USER_DEFINED';
        $default_value = 'VARCHAR';
        $extra = 'AUTO_INCREMENT';
        $comment = 'PMA comment';
        $virtuality = '';
        $expression = '';
        $move_to = 'new_name';

        $result = Table::generateAlter(
            $oldcol,
            $newcol,
            $type,
            $length,
            $attribute,
            $collation,
            $null,
            $default_type,
            $default_value,
            $extra,
            $comment,
            $virtuality,
            $expression,
            $move_to
        );

        $expect = '`name` `new_name` VARCHAR(2) new_name CHARACTER SET '
            . "charset1 NULL DEFAULT 'VARCHAR' "
            . "AUTO_INCREMENT COMMENT 'PMA comment' AFTER `new_name`";

        $this->assertEquals($expect, $result);
    }

    /**
     * Test for rename
     */
    public function testRename(): void
    {
        $GLOBALS['cfg']['Server']['DisableIS'] = true;

        $table = 'PMA_BookMark';
        $db = 'PMA';

        $table = new Table($table, $db, $GLOBALS['dbi']);

        //rename to same name
        $table_new = 'PMA_BookMark';
        $result = $table->rename($table_new);
        $this->assertTrue($result);

        //isValidName
        //space in table name
        $table_new = 'PMA_BookMark ';
        $result = $table->rename($table_new);
        $this->assertFalse($result);
        //empty name
        $table_new = '';
        $result = $table->rename($table_new);
        $this->assertFalse($result);
        //dot in table name
        $table_new = 'PMA_.BookMark';
        $result = $table->rename($table_new);
        $this->assertTrue($result);

        //message
        $this->assertEquals(
            'Table PMA_BookMark has been renamed to PMA_.BookMark.',
            $table->getLastMessage()
        );

        $table_new = 'PMA_BookMark_new';
        $db_new = 'PMA_new';
        $result = $table->rename($table_new, $db_new);
        $this->assertTrue($result);
        //message
        $this->assertEquals(
            'Table PMA_.BookMark has been renamed to PMA_BookMark_new.',
            $table->getLastMessage()
        );
    }

    /**
     * Test for getUniqueColumns
     */
    public function testGetUniqueColumns(): void
    {
        $table = 'PMA_BookMark';
        $db = 'PMA';

        $table = new Table($table, $db, $GLOBALS['dbi']);
        $return = $table->getUniqueColumns();
        $expect = [
            '`PMA`.`PMA_BookMark`.`index1`',
            '`PMA`.`PMA_BookMark`.`index3`',
            '`PMA`.`PMA_BookMark`.`index5`',
        ];
        $this->assertEquals($expect, $return);
    }

    /**
     * Test for getIndexedColumns
     */
    public function testGetIndexedColumns(): void
    {
        $table = 'PMA_BookMark';
        $db = 'PMA';

        $table = new Table($table, $db, $GLOBALS['dbi']);
        $return = $table->getIndexedColumns();
        $expect = [
            '`PMA`.`PMA_BookMark`.`column1`',
            '`PMA`.`PMA_BookMark`.`column3`',
            '`PMA`.`PMA_BookMark`.`column5`',
            '`PMA`.`PMA_BookMark`.`ACCESSIBLE`',
            '`PMA`.`PMA_BookMark`.`ADD`',
            '`PMA`.`PMA_BookMark`.`ALL`',
        ];
        $this->assertEquals($expect, $return);
    }

    /**
     * Test for getColumnsMeta
     */
    public function testGetColumnsMeta(): void
    {
        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $resultStub = $this->createMock(DummyResult::class);

        $dbi->expects($this->once())
            ->method('tryQuery')
            ->with('SELECT * FROM `db`.`table` LIMIT 1')
            ->will($this->returnValue($resultStub));

        $dbi->expects($this->once())
            ->method('getFieldsMeta')
            ->with($resultStub)
            ->will($this->returnValue(['aNonValidExampleToRefactor']));

        $GLOBALS['dbi'] = $dbi;

        $tableObj = new Table('table', 'db', $GLOBALS['dbi']);

        $this->assertEquals(
            $tableObj->getColumnsMeta(),
            ['aNonValidExampleToRefactor']
        );
    }

    /**
     * Tests for getSQLToCreateForeignKey() method.
     */
    public function testGetSQLToCreateForeignKey(): void
    {
        $table = 'PMA_table';
        $field = [
            'PMA_field1',
            'PMA_field2',
        ];
        $foreignDb = 'foreignDb';
        $foreignTable = 'foreignTable';
        $foreignField = [
            'foreignField1',
            'foreignField2',
        ];

        $tableObj = new Table('PMA_table', 'db', $GLOBALS['dbi']);

        $sql = $this->callFunction(
            $tableObj,
            Table::class,
            'getSQLToCreateForeignKey',
            [
                $table,
                $field,
                $foreignDb,
                $foreignTable,
                $foreignField,
            ]
        );
        $sql_excepted = 'ALTER TABLE `PMA_table` ADD  '
            . 'FOREIGN KEY (`PMA_field1`, `PMA_field2`) REFERENCES '
            . '`foreignDb`.`foreignTable`(`foreignField1`, `foreignField2`);';
        $this->assertEquals($sql_excepted, $sql);

        // Exclude db name when relations are made between table in the same db
        $sql = $this->callFunction(
            $tableObj,
            Table::class,
            'getSQLToCreateForeignKey',
            [
                $table,
                $field,
                'db',
                $foreignTable,
                $foreignField,
            ]
        );
        $sql_excepted = 'ALTER TABLE `PMA_table` ADD  '
            . 'FOREIGN KEY (`PMA_field1`, `PMA_field2`) REFERENCES '
            . '`foreignTable`(`foreignField1`, `foreignField2`);';
        $this->assertEquals($sql_excepted, $sql);
    }

    /**
     * Tests for getSqlQueryForIndexCreateOrEdit() method.
     */
    public function testGetSqlQueryForIndexCreateOrEdit(): void
    {
        $db = 'pma_db';
        $table = 'pma_table';
        $index = new Index();
        $error = false;

        $_POST['old_index'] = 'PRIMARY';

        $table = new Table($table, $db, $GLOBALS['dbi']);
        $sql = $table->getSqlQueryForIndexCreateOrEdit($index, $error);

        $this->assertEquals('ALTER TABLE `pma_db`.`pma_table` DROP PRIMARY KEY, ADD UNIQUE;', $sql);
    }

    /**
     * Tests for getSqlQueryForIndexCreateOrEdit() method.
     */
    public function testGetSqlQueryForIndexCreateOrEditSecondFormat(): void
    {
        $db = 'pma_db';
        $table = 'pma_table';
        $index = new Index();
        $error = false;

        $_POST['old_index']['Key_name'] = 'PRIMARY';

        $table = new Table($table, $db, $GLOBALS['dbi']);
        $sql = $table->getSqlQueryForIndexCreateOrEdit($index, $error);

        $this->assertEquals('ALTER TABLE `pma_db`.`pma_table` DROP PRIMARY KEY, ADD UNIQUE;', $sql);
    }

    /**
     * Test for getColumns
     */
    public function testGetColumns(): void
    {
        $table = 'PMA_BookMark';
        $db = 'PMA';

        $table = new Table($table, $db, $GLOBALS['dbi']);
        $return = $table->getColumns();
        $expect = [
            '`PMA`.`PMA_BookMark`.`column1`',
            '`PMA`.`PMA_BookMark`.`column3`',
            '`PMA`.`PMA_BookMark`.`column5`',
            '`PMA`.`PMA_BookMark`.`ACCESSIBLE`',
            '`PMA`.`PMA_BookMark`.`ADD`',
            '`PMA`.`PMA_BookMark`.`ALL`',
        ];
        $this->assertEquals($expect, $return);

        $return = $table->getReservedColumnNames();
        $expect = [
            'ACCESSIBLE',
            'ADD',
            'ALL',
        ];
        $this->assertEquals($expect, $return);
    }

    /**
     * Test for checkIfMinRecordsExist
     */
    public function testCheckIfMinRecordsExist(): void
    {
        $old_dbi = $GLOBALS['dbi'];

        $resultStub = $this->createMock(DummyResult::class);

        $dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dbi->expects($this->any())
            ->method('tryQuery')
            ->will($this->returnValue($resultStub));
        $resultStub->expects($this->any())
            ->method('numRows')
            ->willReturnOnConsecutiveCalls(0, 10, 200);
        $dbi->expects($this->any())
            ->method('fetchResult')
            ->willReturnOnConsecutiveCalls(
                [['`one_pk`']],
                [], // No Uniques found
                [
                    '`one_ind`',
                    '`sec_ind`',
                ],
                [], // No Uniques found
                [] // No Indexed found
            );

        $GLOBALS['dbi'] = $dbi;

        $table = 'PMA_BookMark';
        $db = 'PMA';
        $tableObj = new Table($table, $db, $GLOBALS['dbi']);

        // Case 1 : Check if table is non-empty
        $return = $tableObj->checkIfMinRecordsExist();
        $expect = true;
        $this->assertEquals($expect, $return);

        // Case 2 : Check if table contains at least 100
        $return = $tableObj->checkIfMinRecordsExist(100);
        $expect = false;
        $this->assertEquals($expect, $return);

        // Case 3 : Check if table contains at least 100
        $return = $tableObj->checkIfMinRecordsExist(100);
        $expect = true;
        $this->assertEquals($expect, $return);

        $GLOBALS['dbi'] = $old_dbi;
    }

    /**
     * Test for Table::countRecords
     */
    public function testCountRecords(): void
    {
        $table = 'PMA_BookMark';
        $db = 'PMA';
        $tableObj = new Table($table, $db, $GLOBALS['dbi']);

        $this->assertEquals(
            20,
            $tableObj->countRecords(true)
        );
    }

    /**
     * Test for setUiProp
     */
    public function testSetUiProp(): void
    {
        $table_name = 'PMA_BookMark';
        $db = 'PMA';

        $table = new Table($table_name, $db, $GLOBALS['dbi']);

        $property = Table::PROP_COLUMN_ORDER;
        $value = 'UiProp_value';
        $table_create_time = null;
        $table->setUiProp($property, $value, $table_create_time);

        //set UI prop successfully
        $this->assertEquals($value, $table->uiprefs[$property]);

        //removeUiProp
        $table->removeUiProp($property);
        $is_define_property = isset($table->uiprefs[$property]);
        $this->assertFalse($is_define_property);

        //getUiProp after removeUiProp
        $is_define_property = $table->getUiProp($property);
        $this->assertFalse($is_define_property);
    }

    /**
     * Test for moveCopy
     */
    public function testMoveCopy(): void
    {
        $source_table = 'PMA_BookMark';
        $source_db = 'PMA';
        $target_table = 'PMA_BookMark_new';
        $target_db = 'PMA_new';
        $what = 'dataonly';
        $move = true;
        $mode = 'one_table';

        $GLOBALS['dbi']->expects($this->any())->method('getTable')
            ->will($this->returnValue(new Table($target_table, $target_db, $GLOBALS['dbi'])));

        $return = Table::moveCopy($source_db, $source_table, $target_db, $target_table, $what, $move, $mode, true);

        //successfully
        $expect = true;
        $this->assertEquals($expect, $return);
        $sql_query = 'INSERT INTO `PMA_new`.`PMA_BookMark_new`(`COLUMN_NAME1`)'
            . ' SELECT `COLUMN_NAME1` FROM '
            . '`PMA`.`PMA_BookMark`';
        $this->assertStringContainsString($sql_query, $GLOBALS['sql_query']);
        $sql_query = 'DROP VIEW `PMA`.`PMA_BookMark`';
        $this->assertStringContainsString($sql_query, $GLOBALS['sql_query']);

        $return = Table::moveCopy($source_db, $source_table, $target_db, $target_table, $what, false, $mode, true);

        //successfully
        $expect = true;
        $this->assertEquals($expect, $return);
        $sql_query = 'INSERT INTO `PMA_new`.`PMA_BookMark_new`(`COLUMN_NAME1`)'
            . ' SELECT `COLUMN_NAME1` FROM '
            . '`PMA`.`PMA_BookMark`';
        $this->assertStringContainsString($sql_query, $GLOBALS['sql_query']);
        $sql_query = 'DROP VIEW `PMA`.`PMA_BookMark`';
        $this->assertStringNotContainsString($sql_query, $GLOBALS['sql_query']);
    }

    /**
     * Test for getStorageEngine
     */
    public function testGetStorageEngine(): void
    {
        $target_table = 'table1';
        $target_db = 'pma_test';
        $extension = new DbiDummy();
        $dbi = new DatabaseInterface($extension);
        $tbl_object = new Table($target_db, $target_table, $dbi);
        $tbl_object->getStatusInfo(null, true);
        $expect = 'DBIDUMMY';
        $tbl_storage_engine = $dbi->getTable($target_db, $target_table)->getStorageEngine();
        $this->assertEquals($expect, $tbl_storage_engine);
    }

    /**
     * Test for getComment
     */
    public function testGetComment(): void
    {
        $target_table = 'table1';
        $target_db = 'pma_test';
        $extension = new DbiDummy();
        $dbi = new DatabaseInterface($extension);
        $tbl_object = new Table($target_db, $target_table, $dbi);
        $tbl_object->getStatusInfo(null, true);
        $expect = 'Test comment for "table1" in \'pma_test\'';
        $show_comment = $dbi->getTable($target_db, $target_table)->getComment();
        $this->assertEquals($expect, $show_comment);
    }

    /**
     * Test for getCollation
     */
    public function testGetCollation(): void
    {
        $target_table = 'table1';
        $target_db = 'pma_test';
        $extension = new DbiDummy();
        $dbi = new DatabaseInterface($extension);
        $tbl_object = new Table($target_db, $target_table, $dbi);
        $tbl_object->getStatusInfo(null, true);
        $expect = 'utf8mb4_general_ci';
        $tbl_collation = $dbi->getTable($target_db, $target_table)->getCollation();
        $this->assertEquals($expect, $tbl_collation);
    }

    /**
     * Test for getRowFormat
     */
    public function testGetRowFormat(): void
    {
        $target_table = 'table1';
        $target_db = 'pma_test';
        $extension = new DbiDummy();
        $dbi = new DatabaseInterface($extension);
        $tbl_object = new Table($target_db, $target_table, $dbi);
        $tbl_object->getStatusInfo(null, true);
        $expect = 'Redundant';
        $row_format = $dbi->getTable($target_db, $target_table)->getRowFormat();
        $this->assertEquals($expect, $row_format);
    }

    /**
     * Test for getAutoIncrement
     */
    public function testGetAutoIncrement(): void
    {
        $target_table = 'table1';
        $target_db = 'pma_test';
        $extension = new DbiDummy();
        $dbi = new DatabaseInterface($extension);
        $tbl_object = new Table($target_db, $target_table, $dbi);
        $tbl_object->getStatusInfo(null, true);
        $expect = '5';
        $auto_increment = $dbi->getTable($target_db, $target_table)->getAutoIncrement();
        $this->assertEquals($expect, $auto_increment);
    }

    /**
     * Test for getCreateOptions
     */
    public function testGetCreateOptions(): void
    {
        $target_table = 'table1';
        $target_db = 'pma_test';
        $extension = new DbiDummy();
        $dbi = new DatabaseInterface($extension);
        $tbl_object = new Table($target_db, $target_table, $dbi);
        $tbl_object->getStatusInfo(null, true);
        $expect = [
            'pack_keys' => 'DEFAULT',
            'row_format' => 'REDUNDANT',
        ];
        $create_options = $dbi->getTable($target_db, $target_table)->getCreateOptions();
        $this->assertEquals($expect, $create_options);
    }
}
