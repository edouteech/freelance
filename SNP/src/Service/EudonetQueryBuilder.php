<?php

namespace App\Service;

use Exception;

class EudonetQueryBuilder extends AbstractService{

	private $query=false;

	private $constants;
	private $tables;
	private $type;

	private $tableFromId=[];

	private static $tableAlias=[];

	private $operators = [
		'asc' => 0,
		'desc' => 1,
		'and' => 1,
		'or' => 2,
		'=' => 0,
		'<' => 1,
		'<=' => 2,
		'>' => 3,
		'>=' => 4,
		'!=' => 5,
		'like%' => 6,
		'%like' => 7,
		'in' => 8,
		'like' => 9,
		'empty' => 10,
		'full' => 17,
		'true' => 11,
		'false' => 12
	];

	/**
	 * Eudonet constructor.
	 * @param $parameters
	 * @param bool $type
	 */
	public function __construct($parameters, $type=false){

		self::$tableAlias = [];

		$this->tables = $parameters['tables'];
		$this->constants = $parameters['constants'];

		$this->type = $type;

		foreach ($this->tables as $name=>$table)
			$this->tableFromId[$table['id']] = $name;
	}

	public function getType(){

		return $this->query['type']??'select';
	}

	/**
	 * @param $fields
	 * @return $this
	 */
	public function select($fields){

		if( is_string($fields) && $fields != '*' )
			$fields = array_map('trim', explode(',', $fields));

		$this->query = [];

		$this->query['type'] = 'select';
		$this->query['fields'] = $fields;

		return $this;
	}

	/**
	 * @return $this
	 */
	public function delete(){

		$this->query = [];

		$this->query['type'] = 'delete';

		return $this;
	}

	/**
	 * @param $table
	 * @return $this
	 * @throws Exception
	 */
	public function insert($table){

		$this->query = [];

		$this->query['type'] = 'insert';
		$this->query['table'] = $this->getTableId($table);
		$this->query['table_name'] = $table;

		return $this;
	}

	/**
	 * @param $table
	 * @return $this
	 * @throws Exception
	 */
	public function update($table){

		$this->query = [];

		$this->query['type'] = 'update';
		$this->query['table'] = $this->getTableId($table);
		$this->query['table_name'] = $table;

		return $this;
	}

	/**
	 * @param $values
	 * @return $this
	 * @throws Exception
	 */
	public function setValues($values){

		$this->query['values'] = [];

		foreach ($values as $key=>$value)
			$this->setValue($key, $value);

		return $this;
	}

	/**
	 * @param $key
	 * @param $value
	 * @return $this
	 * @throws Exception
	 */
	public function setValue($key, $value){

	    if( is_null($value) )
	        return $this;

		$key = str_replace('_decrypted', '', $key);

		if( is_array($value) ){

			if( isset($value['id']) ){

				$value = $value['id']??false;
			}
			else{

				$constants = [];

				foreach ($value as $_value)
					$constants[] = $this->getConstantId($this->clean($_value));

				$value = implode(';', $constants);
			}
		}
		elseif( strpos($value, ';') != -1 ){

			$values = explode(';', $value);
			$constants = [];

			foreach ($values as $value)
				$constants[] = $this->getConstantId($this->clean($value));

			$value = implode(';', $constants);
		}
		else{

			$value = $this->getConstantId($this->clean($value));
		}

		if( $key = $this->getColumnId($this->query['table_name'], $key) )
			$this->query['values'][] = [$key, $value];

		return $this;
	}

	/**
	 * @param $table
	 * @param $alias
	 * @return $this
	 */
	public function join($table, $alias){

		$this->addTableAlias($table, $alias);
		return $this;
	}

	/**
	 * @param $id
	 * @return $this
	 * @throws Exception
	 */
	public function on($id){

		if( !$id )
			throw new Exception('Id is empty');

		$this->query['id'] = $id;
		return $this;
	}

	/**
	 * @param $table
	 * @param bool $alias
	 * @return $this
	 * @throws Exception
	 */
	public function from($table, $alias=false){

		$this->query['table'] = $this->getTableId($table);
		$this->query['table_name'] = $table;

		$this->addTableAlias($table);

		if( $alias )
			$this->addTableAlias($table, $alias);

		if( $this->query['fields']??false )
			$this->query['fields'] = $this->getColumnsId($table, $this->query['fields']);

		return $this;
	}

	/**
	 * @param $table
	 * @param bool $alias
	 * @return void
	 */
	public function addTableAlias($table, $alias=false){

		self::$tableAlias[$alias]=$table;
	}


	/**
	 * @param bool $alias
	 * @return string
	 */
	public function getTableFromAlias($alias){

		return self::$tableAlias[$alias]??$alias;
	}


	/**
	 * @param $number
	 * @return string|null
	 */
	public function getTableFromNumber($number){

		if( is_string($number) ){

			$number = explode('_', $number);

			if( count($number) != 2 )
				return null;

			$number = $number[1];
		}

		$id = intval($number)*100+1000;

		return $this->tableFromId[$id] ?? $id;
	}

	/**
	 * @param $column
	 * @return array
	 */
	public function getTableColumn($column){

		$column = explode('.', $column);

		if( count($column) == 2 ){
			$table = $this->getTableFromAlias($column[0]);
			$column = $column[1];
		}
		else{
			$table = $this->query['table_name'];
			$column = $column[0];
		}

		return [$table, $column];
	}

	/**
	 * @param $column
	 * @param $operator
	 * @param $value
	 * @param int $interOperator
	 * @return $this
	 * @throws Exception
	 */
	public function where($column, $operator, $value, $interOperator=0){

		if( $this->type == 'exp' && $interOperator==0 )
			$this->query=[];

		[$table, $column] = $this->getTableColumn($column);

		$field = $this->getColumnId($table, $column);

		if( !$field )
			return $this;

		$condition = [
			'WhereCustoms' => NULL,
			'Criteria' => array	(
				'Field' => $field,
				'Operator' => $this->getOperatorId($operator),
				'Value' => $this->getConstantId($value)
			),
			'InterOperator' => $interOperator
		];

		if( is_array($value) )
			$value = implode(';', $value);

		if( $value === '' && $operator == '!=' ){
			$condition['Criteria']['Operator'] = $this->getOperatorId('full');
		}
		elseif( $value === '' && $operator == '=' ){
			$condition['Criteria']['Operator'] = $this->getOperatorId('empty');
		}
		elseif( is_bool($value) && $operator == '=' ){
			$condition['Criteria']['Operator'] = $this->getOperatorId($value?'true':'false');
			$condition['Criteria']['Value'] = '';
		}
		elseif( is_bool($value) && $operator == '!=' ){
			$condition['Criteria']['Operator'] = $this->getOperatorId($value=='true'?'false':'true');
			$condition['Criteria']['Value'] = '';
		}

		$this->query['where'][] = $condition;

		return $this;
	}

	/**
	 * @param $column
	 * @param $operator
	 * @param $value
	 * @return $this
	 * @throws Exception
	 */
	public function andWhere($column, $operator, $value){

		$this->where($column, $operator, $value, $this->getOperatorId('AND'));

		return $this;
	}

	/**
	 * @param EudonetQueryBuilder $qb
	 * @return $this
	 * @throws Exception
	 */
	public function andSubWhere(EudonetQueryBuilder $qb){

		$this->query['where'][] = [
			'WhereCustoms' => $qb->getEQL(),
			'Criteria' => NULL,
			'InterOperator' => $this->getOperatorId('AND')
		];

		return $this;
	}

	/**
	 * @param EudonetQueryBuilder $qb
	 * @return $this
	 * @throws Exception
	 */
	public function subWhere(EudonetQueryBuilder $qb){

		$this->query['where'][] = [
			'WhereCustoms' => $qb->getEQL(),
			'Criteria' => NULL,
			'InterOperator' => 0
		];

		return $this;
	}

	/**
	 * @param EudonetQueryBuilder $qb
	 * @return $this
	 * @throws Exception
	 */
	public function orSubWhere(EudonetQueryBuilder $qb){

		$this->query['where'][] = [
			'WhereCustoms' => $qb->getEQL(),
			'Criteria' => NULL,
			'InterOperator' => $this->getOperatorId('OR')
		];

		return $this;
	}

	/**
	 * @param $column
	 * @param $operator
	 * @param $value
	 * @return $this
	 * @throws Exception
	 */
	public function orWhere($column, $operator, $value){

		$this->where($column, $operator, $value, $this->getOperatorId('OR'));

		return $this;
	}

	/**
	 * @param $column
	 * @param string $direction
	 * @return $this
	 * @throws Exception
	 */
	public function orderBy($column, $direction='ASC'){

		[$table, $column] = $this->getTableColumn($column);

        $column_id = $this->getColumnId($table, $column);
        $operatorId = $this->getOperatorId($direction);

		if( $column_id && $operatorId )
			$this->query['order'] = [$column_id, $operatorId];

		return $this;
	}

	/**
	 * @param $count
	 * @param int $offset
	 * @return $this
	 */
	public function limit($count, $offset=0){

		$this->query['rowsPerPage'] = $count;
		$this->query['offset'] = intval($offset)+1;

		return $this;
	}

	public function getTable(){

		return $this->query['table'];
	}

	public function getId(){

		return $this->query['id']??false;
	}

	public function isSelectUnique(){

		return is_string($this->query['fields']);
	}

	public function getFields(){

		return $this->query['fields']??[];
	}

	public function getTableName(){

		return $this->query['table_name'];
	}

	/**
	 * @return array|bool|mixed
	 * @throws Exception
	 */
	public function getEQL(){

		if( $this->type == 'exp')
			return $this->query['where'];

		switch( $this->query['type'] ){

			case 'select':

				if( !isset($this->query['order']) )
					$this->query['order'] = [$this->query['fields'][0], $this->getOperatorId('asc')];

				$orderBy = [
					'DescId' => $this->query['order'][0],
					'Order' => $this->query['order'][1]
				];

				return [
					'ShowMetadata' => true,
					'RowsPerPage' => $this->query['rowsPerPage']??'',
					'NumPage' => $this->query['offset']??1,
					'ListCols' => (array)$this->query['fields'],
					'WhereCustom' => [
						'WhereCustoms' => $this->query['where'],
						'Criteria' => NULL,
						'InterOperator' => 0
					],
					'OrderBy' => [$orderBy]
				];

			case 'insert':
			case 'update':

				$data = [];

				foreach ($this->query['values'] as $field)
					$data[] = ['DescId' => $field[0], 'Value' => $field[1]];

				return ['Fields' => $data];
		}

		return false;
	}

	/**
	 * Get table id
	 *
	 * @param $name
	 * @return bool
	 * @throws Exception
	 */
	public function getTableId($name){

		if( is_int($name) )
			return $name;

		$name = $this->clean($name);

		if( !isset($this->tables[$name]) )
			throw new Exception('Table '.$name.' does not exists');

		return $this->tables[$name]['id']??false;
	}

	/**
	 * Clean string
	 *
	 * @param $name
	 * @return bool
	 */
	private function clean($name){

		return str_replace('`', '', trim(strip_tags(stripslashes($name))));
	}

	/**
	 * Get operator id
	 *
	 * @param $name
	 * @return bool|mixed
	 * @throws Exception
	 */
	private function getOperatorId($name){

		$name = strtolower(trim($name));

		if( !isset($this->operators[$name]) )
			throw new Exception('Operator '.$name.' does not exists');

		return $this->operators[$name]??false;
	}

	/**
	 * Get table related columns id
	 *
	 * @param $table
	 * @param $columns
	 * @return array
	 * @throws Exception
	 */
	public function getColumnsId($table, $columns){

		if( $columns == '*' )
			$columns = array_keys($this->tables[$table]['columns']);

		$ids =[];

		foreach ((array)$columns as $column)
			$ids[] = $this->getColumnId($table, $column, 'select');

		return array_values(array_filter($ids));
	}

	/**
	 * Get table related column id
	 *
	 * @param $table
	 * @param $column
	 * @param string $type
	 * @return bool
	 * @throws Exception
	 */
	public function getColumnId($table, $column, $type=''){

		$column = $this->clean($column);

		if( $column == 'id' )
			return false;

		$_column = explode('.', $column);

		if( count($_column) == 2 ) {
			$table = $_column[0];
			$column = $_column[1];
		}

		if( !$table_id = $this->getTableId($table) )
			return false;

		if( !isset($this->tables[$table]['columns'][$column]) ){

			$column = $column.'_id';

			if( !isset($this->tables[$table]['columns'][$column]) )
				throw new Exception('Column '.$column.' does not exists in the table '.$table );
		}

		$column_id = $this->tables[$table]['columns'][$column]??false;

		// check if we are requesting an id from another table
		if( $column_id ){

			$column_id = explode('|', $column_id);
			$column_id = intval($column_id[0]);

			if( $type == 'select' && ($column_id<$table_id || $column_id-$table_id >= 100) && ($column_id-$table_id) % 100 == 0 )
				$column_id+=1;
		}

		return $column_id;
	}

	/**
	 * Get constant id from string
	 *
	 * @param $name
	 * @return bool|string|array
	 * @throws Exception
	 */
	public function getConstantId($name){

		if( is_array($name) ){

			foreach ($name as &$value)
				$value = $this->getConstantId($value);

			return implode(';', $name);
		}
		else{

			$name = str_replace("'", "", $name);

			if( empty($name) || !isset($this->constants[$name]) )
				return $name;

			return $this->constants[$name];
		}
	}
}