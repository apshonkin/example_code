<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Реквизиты пользователей для выплат.
 *
 * Одна пара "пользователь + счёт" - одна строка: за этим следит уникальный
 * индекс, на который опирается валидатор unique в модели. Права роли выдаём
 * здесь же - без GRANT новая таблица не видна приложению, а кеш схемы сбрасываем,
 * чтобы ActiveRecord увидел таблицу сразу после миграции.
 */
class m251006_064636_create_user_payout_account extends Migration
{
    private const TABLE = 'user_payout_account';

    /** Роль приложения: пишет и читает. */
    private const ROLE_APP = 'ndflka_site';

    /** Роль отчётов и слейва: только читает. */
    private const ROLE_READONLY = 'selectonly';

    public function safeUp(): void
    {
        $this->createTable(self::TABLE, [
            'id' => $this->primaryKey(),
            'uid' => $this->bigInteger()->notNull()->comment('ID пользователя'),
            'owner_name' => $this->string(255)->notNull()->comment('ФИО владельца счёта'),
            'account_number' => $this->string(20)->notNull()->comment('Номер счёта'),
            'created_by' => $this->bigInteger()->notNull()->comment('Кто создал запись'),
            'created' => $this->timestamp()->notNull()->defaultExpression('now()')->comment('Дата создания'),
            'updated' => $this->timestamp()->notNull()->defaultExpression('now()')->comment('Дата обновления'),
        ]);

        $this->createIndex('idx_user_payout_account_uid', self::TABLE, 'uid');
        $this->createIndex('uq_user_payout_account_uid_number', self::TABLE, ['uid', 'account_number'], true);

        $this->addForeignKey('fk_user_payout_account_uid', self::TABLE, 'uid', 'user', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(
            'fk_user_payout_account_created_by',
            self::TABLE,
            'created_by',
            'user',
            'id',
            'RESTRICT',
            'CASCADE',
        );

        $this->execute(sprintf(
            'GRANT SELECT, INSERT, UPDATE, DELETE ON %s TO %s',
            self::TABLE,
            self::ROLE_APP,
        ));
        $this->execute(sprintf(
            'GRANT USAGE, SELECT ON SEQUENCE %s_id_seq TO %s',
            self::TABLE,
            self::ROLE_APP,
        ));
        $this->execute(sprintf('GRANT SELECT ON %s TO %s', self::TABLE, self::ROLE_READONLY));

        Yii::$app->cache->flush();
    }

    public function safeDown(): void
    {
        $this->dropTable(self::TABLE);

        Yii::$app->cache->flush();
    }
}
