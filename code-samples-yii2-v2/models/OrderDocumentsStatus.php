<?php

declare(strict_types=1);

namespace app\models;

use app\components\main\MainModel;
use yii\db\ActiveQuery;

/**
 * Состояние документов заказа, таблица "order_documents_status".
 *
 * Одна строка на заказ: когда клиент впервые загрузил документы и когда состав
 * стал полным. От первой загрузки считается срок работы эксперта - поэтому в
 * дашбордах эта таблица нужна почти всегда, и путь к ней описан связью, а не
 * join-ом по месту.
 *
 * @property int $order_id
 * @property string|null $first_uploaded_date первая загрузка документов клиентом
 * @property string|null $completed_date состав документов собран полностью
 *
 * @property-read Order $order
 */
final class OrderDocumentsStatus extends MainModel
{
    public static function tableName(): string
    {
        return 'order_documents_status';
    }

    public function getOrder(): ActiveQuery
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }
}
