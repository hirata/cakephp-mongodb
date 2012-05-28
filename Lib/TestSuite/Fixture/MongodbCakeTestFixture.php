<?php 
/**
 * Custom CakeTestFixture for Mongodb DataSource.
 * 
 * Fixture to import data with MongoDB DataSource with conditions.
 * 
 * @package mongodb.testsuite.fixture
 * @since CakePHP 2.0 and later
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Custom CakeTestFixture to import with MongoDB Datasource
 *
 * Sample fixture:
 * <code>
 * App::uses('MongodbCakeTestFixture', 'mongodb.TestSuite/Fixture');
 * 
 * class PostFixture extends MongodbCakeTestFixture {
 *     pubic $import = array(
 *         'model' => 'Post', 
 *         'connection' => 'default',
 *     );
 * 
 *     public function __construct() {
 *         $this->import['conditions'] = array(
 *             'title' => new MongoRegex('/test/i'),
 *         );
 *         return parent::__construct();
 *     }
 * }
 * </code>
 * 
 * @package mongodb.testsuite.fixture
 */
class MongodbCakeTestFixture extends CakeTestFixture {
    /**
     * Keep Model class;
     */
    private $_model = null;

    /**
     * override init()
     */
    public function init() {
        parent::init();
		if (isset($this->import) && (is_string($this->import) || is_array($this->import))) {
			$import = array_merge(
				array('connection' => 'default', 'records' => false),
				is_array($this->import) ? $this->import : array('model' => $this->import)
			);
            if (isset($import['model'])) {
                list($plugin, $modelClass) = pluginSplit($import['model'], true);
                App::uses($modelClass, $plugin . 'Model');
                if (!class_exists($modelClass)) {
                    throw new MissingModelException(array('class' => $modelClass));
                }
                $model = new $modelClass(null, null, $import['connection']);
                $this->_model = $model;
                $db = $model->getDataSource();
                if (empty($model->tablePrefix)) {
                    $model->tablePrefix = $db->config['prefix'];
                }
                $this->fields = $model->schema(true);
                $this->fields[$model->primaryKey]['key'] = 'primary';
                $this->table = $db->fullTableName($model, false);

                if (isset($import['conditions'])) {
                    $records = $model->find('all', array('conditions' => $import['conditions']));
                } else {
                    $records = $model->find('all');
                }
                
                ClassRegistry::config(array('ds' => 'test'));
                ClassRegistry::flush();
            }
            if ($records !== false && !empty($records)) {
                $records = $this->_convertMongoId($records);
                $this->records = Set::extract($records, '{n}.' . $model->alias);
            }
        }
    }

    /**
     * override _setupTable()
     */
    protected function _setupTable($fixture, $db = null, $drop = true) {
        if (!$db) {
            $db = $this->_db;
        }
        if (!empty($fixture->created) && $fixture->created == $db->configKeyName) {
            return;
        }

        $sources = $db->listSources();
        $table = $db->config['prefix'] . $fixture->table;

        if ($drop && in_array($table, $sources)) {
            $fixture->drop($db);
            $fixture->create($db);
            $fixture->created = $db->configKeyName;
        } elseif (!in_array($table, $sources)) {
            $fixture->create($db);
            $fixture->created = $db->configKeyName;
        }
    }

    /**
     * override insert method, because $db->insertMulti() 
     * requires fieldset of all records are same.
     */
    public function insert($db) {
        if (is_null($this->_model)) {
            return false;
        }
		if (!isset($this->_insert)) {
			$values = array();
			if (isset($this->records) && !empty($this->records)) {
				$fields = array();
				foreach ($this->records as $record) {
					$fields = array_merge($fields, array_keys(array_intersect_key($record, $this->fields)));
				}
				$fields = array_unique($fields);
				$default = array_fill_keys($fields, null);
				foreach ($this->records as $record) {
					$fields = array_keys(array_merge($default, $record));
					$values = array_values(array_merge($default, $record));
                    $res = $db->create($this->_model, $fields, $values);
				}
				return true;
			}
			return true;
		}
    }

    /**
     * Convert String ID to MongoID Object
     *
     * @param array Model Data
     * @retrun array converted Model Data
     */
    private function _convertMongoId($data) {
        array_walk_recursive(
            &$data, function(&$val, $key) {
                if ($key == '_id' && is_string($val)) {
                    $val = new MongoId($val);
                }
            });
        return $data;
    }

}
