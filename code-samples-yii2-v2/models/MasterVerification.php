<?php

declare(strict_types=1);

namespace app\models;

use app\components\main\MainModel;
use app\models\query\MasterVerificationQuery;
use yii\db\ActiveQuery;

/**
 * Проверка декларации экспертом, таблица "master_verification".
 *
 * Приведена ради связей и запросов: дашборды считают показатели эксперта именно
 * по проверкам, поэтому путь "проверка - декларация - заказ - статусы документов"
 * описан здесь один раз, а не собирается join-ами в каждом запросе.
 *
 * @property int $id
 * @property int $did декларация, master.id
 * @property int $verified_by эксперт, закрывший проверку
 * @property string|null $closed
 *
 * @property-read Master $master
 */
final class MasterVerification extends MainModel
{
    public static function tableName(): string
    {
        return 'master_verification';
    }

    public static function find(): MasterVerificationQuery
    {
        return new MasterVerificationQuery(static::class);
    }

    public function getMaster(): ActiveQuery
    {
        return $this->hasOne(Master::class, ['id' => 'did']);
    }
}
