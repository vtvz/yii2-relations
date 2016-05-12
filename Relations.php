<?php
namespace vtvz\relations;

use \Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\base\InvalidParamException;

class Relations extends Behavior
{
    public $relations = [];

    protected $_relations = [];

    public $relationConfig = [];

    public $relationClass = 'vtvz\relations\Relation';


    /**
     * Нормализация массива со связями.
     */
    public function init()
    {
        parent::init();

        $relations = [];

        foreach ($this->relations as $i => $relation) {
            if (key_exists('name', $relation)) {
                $name = $relation['name'];
            } else {
                $name = $i;
            }

            $name = $this->prepareRelationName($name);

            $relation['name'] = $name;
            $relations[$name] = $relation;
        }

        $this->relations = $relations;
    }

    /**
     * Приведение имени связи к единому стандарту
     * @param string $name Имя связи
     * @return string Нормализованное имя связи
     */
    public function prepareRelationName($name)
    {
        return strtolower($name);
    }

    /**
     * Проверка на существование связи
     * @param string $name Имя связи
     * @return bool Возвращает true, если связь с данным именем существует
     */
    public function hasRelation($name)
    {
        $name = $this->prepareRelationName($name);

        return key_exists($name, $this->relations);
    }

    /**
     * Получение связи по имени.
     * @param string $name Имя связи
     * @return vtvz\relations\BaseRelation
     */
    public function getRelation($name)
    {
        $preparedName = $this->prepareRelationName($name);

        if (!key_exists($preparedName, $this->relations)) {
            throw new InvalidParamException("Relation '$name' isn't exist");
        }


        if (!isset($this->_relations[$preparedName])) {
            $this->buildRelation($name);
        }

        return $this->_relations[$preparedName];
    }

    public function saveRelations()
    {
        $result = true;
        foreach ($this->_relations as $relation) {
            /** @var $relation Relation */
            $result = $result && $relation->save();
        }

        return $result;
    }

    private function buildRelation($name)
    {
        Yii::trace("Build relation '$name'", __METHOD__);
        $preparedName = $this->prepareRelationName($name);

        $config = [
            'class' => $this->relationClass,
            'owner' => $this->owner,
            'relationsClass' => $this,
        ];

        $config = ArrayHelper::merge($config, $this->relationConfig);

        $config = ArrayHelper::merge($config, $this->relations[$preparedName]);

        $this->_relations[$preparedName] = Yii::createObject($config);
    }

    /**
     * Проверка на существование связи по геттеру
     * @param string $name Имя геттера
     * @param bool $onlyCheck Если true, проверяет только существование связи по геттеру. Если false, вернет связь.
     * @return bool|yii\db\ActiveQueryInterface
     */
    private function hasRelationGetter($getter, $onlyCheck = true)
    {
        if (strncmp('get', $getter, 3) === 0) {
            $name = substr($getter, 3);
            if ($this->hasRelation($name)) {
                if ($onlyCheck) {
                    return true;
                }

                return $this->getRelation($name)->get();
            }
        }

        return false;
    }


    /**
     * @inheritdoc
     */
    public function hasProperty($name, $checkVars = true)
    {
        if ($this->hasRelation($name)) {
            return true;
        }

        return parent::hasProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if ($this->hasRelation($name)) {
            return true;
        }

        return parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        if ($this->hasRelation($name)) {
            return true;
        }

        return parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if ($this->hasRelation($name)) {
            return $this->getRelation($name)->get();
        }

        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if ($this->hasRelation($name)) {
            return $this->getRelation($name)->add($value);
        }

        return parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function hasMethod($name)
    {
        if ($this->hasRelation($name)) {
            return true;
        }

        if ($this->hasRelationGetter($name)) {
            return true;
        }


        return parent::hasMethod($name);
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        if ($this->hasRelation($name)) {
            return $this->getRelation($name)->create($params);
        }

        if ($relation = $this->hasRelationGetter($name, false)) {
            return $relation;
        }

        return parent::__call($name, $params);
    }
}
