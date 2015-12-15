<?php
namespace vtvz\relations;

// use yii\base\Object;
use yii\base\InvalidParamException;
use yii\base\InvalidConfigException;
use yii\base\InvalidValueException;
use yii\db\ActiveRecord;

/**
*
*/
class Relation extends BaseRelation
{
    const TYPE_ONE = 'one';
    const TYPE_MANY = 'many';

    protected $types = [
        self::TYPE_ONE => 'hasOne',
        self::TYPE_MANY => 'hasMany'
    ];

    public function beforeDelete($event)
    {
        if (!$this->getUnlink()) {
            return true;
        }

        $this->owner->unlinkAll($this->name, $this->getDelete());

        return true;
    }

    /**
     * Получение ActiveQuery связи
     */
    public function get()
    {
        if (!empty($this->getViaTable()) && empty($this->getViaTableLink())) {
            throw new InvalidConfigException("With param viaTable sould be link in param viaTableLink");
        }

        $type = $this->types[$this->getType()];

        $relation = $this->owner->{$type}($this->model, $this->getLink());

        if (!empty($this->getViaTable())) {
            $relation->viaTable($this->getViaTable(), $this->getViaTableLink());
        }

        if (!empty($this->getVia())) {
            $relation->via($this->via);
        }

        if (!empty($this->inverseOf)) {
            $relation->inverseOf($this->inverseOf);
        }

        return $relation;
    }

    public function add($value)
    {
        if (!($value instanceof $this->model)) {
            throw new InvalidParamException("Value shoud be instance of " . $this->model);
        }

        if (empty($this->inverseOf)) {
            throw new InvalidConfigException("Property 'inverseOf' shoudn't be empty");
        }

        if (!$value->validate()) {
            throw new InvalidValueException("Value shoud be valid");
        }

        if (!empty($this->getViaTable()) && $value->getIsNewRecord()) {
            if (!$value->save(false)) {
                throw new InvalidValueException("Can't save value");
            }
        }

        $this->owner->link($this->name, $value);
    }

    public function create($params = [])
    {
        return \Yii::createObject($this->model, $params);
    }

    public function getUnlink()
    {
        if ($this->unlink !== null) {
            return (bool) $this->unlink;
        } elseif ($this->getType() == self::TYPE_MANY) {
            return true;
        } elseif ($this->getDelete()) {
            return true;
        }

        return false;
    }

    public function getDelete()
    {
        if ($this->delete !== null) {
            return (bool) $this->delete;
        } elseif ($this->getType() == self::TYPE_MANY) {
            return true;
        }

        return false;
    }

    public function getType()
    {

        if ($this->type !== null) {
            return $this->type;
        }

        if (!empty($this->viaTable)) {
            return self::TYPE_MANY;
        }

        return self::TYPE_ONE;
    }
}
