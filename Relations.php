<?php
namespace vtvz\relations;

use \Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class Relations extends Behavior
{
    public $relations = [];

    protected $localRelations = [];

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

            $name = $this->prepareName($name);

            $relation['name'] = $name;
            $relations[$name] = $relation;
        }

        $this->relations = $relations;
    }

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_DELETE  => 'afterDelete'
        ];
    }

    public function beforeDelete($event)
    {
        foreach ($this->relations as $name => $relation) {
            $relationObject = $this->getRelation($name);

            if (method_exists($relationObject, 'beforeDelete')) {
                $relationObject->beforeDelete($event);
            }
        }
    }

    public function afterDelete($event)
    {
        foreach ($this->relations as $name => $relation) {
            $relationObject = $this->getRelation($name);

            if (method_exists($relationObject, 'afterDelete')) {
                $relationObject->beforeDelete($event);
            }
        }
    }

    /**
     * Приведение имени связи к единому стандарту
     * @param string $name Имя связи
     * @return string Нормализованное имя связи
     */
    public function prepareName($name)
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
        $name = $this->prepareName($name);

        return key_exists($name, $this->relations);
    }

    /**
     * Получение связи по имени.
     * @param string $name Имя связи
     * @return vtvz\relations\BaseRelation
     */
    public function getRelation($name)
    {
        if (!key_exists($name, $this->relations)) {
            throw new InvalidParamException("Relation '$name' isn't exist");
        }

        $preparedName = $this->prepareName($name);

        if (!isset($this->localRelations[$preparedName])) {
            $this->buildRelation($name);
        }

        return $this->localRelations[$preparedName];
    }

    private function buildRelation($name)
    {
        Yii::trace("Build relation '$name'", __METHOD__);
        $preparedName = $this->prepareName($name);

        $config = [
            'class' => $this->relationClass,
            'owner' => $this->owner,
        ];

        $config = ArrayHelper::merge($config, $this->relationConfig);

        $config = ArrayHelper::merge($config, $this->relations[$name]);

        $this->localRelations[$preparedName] = Yii::createObject($config);
    }

    /**
     * Проверка на существование геттера на связь
     * @param type $name
     * @return type
     */
    public function byRelationGetter($getter, $onlyCheck = false)
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
    }

    /**
     * @inheritdoc
     */
    public function hasProperty($name, $checkVars = true)
    {
        if ($this->hasRelation($name)) {
            return true;
        }

        parent::hasProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if ($this->hasRelation($name)) {
            return true;
        }

        parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        if ($this->hasRelation($name)) {
            return true;
        }

        parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if ($this->hasRelation($name)) {
            return $this->getRelation($name)->get();
        }

        parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if ($this->hasRelation($name)) {
            return $this->getRelation($name)->add($value);
        }

        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function hasMethod($name)
    {
        if ($this->hasRelation($name)) {
            return true;
        }

        if ($this->byRelationGetter($name, true)) {
            return true;
        }


        parent::hasMethod($name);
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        if ($this->hasRelation($name)) {
            return $this->getRelation($name)->create($params);
        }

        if ($relation = $this->byRelationGetter($name)) {
            return $relation;
        }

        parent::__call($name, $params);
    }
}
