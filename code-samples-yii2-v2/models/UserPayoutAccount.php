<?php

declare(strict_types=1);

namespace app\models;

use app\components\main\MainModel;
use app\models\query\UserPayoutAccountQuery;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * Реквизиты пользователя для выплат, таблица "user_payout_account".
 *
 * Модель отвечает за целостность строки: формат номера, ссылку на живого
 * пользователя и запрет дублей "пользователь + счёт". Всё это правила данных,
 * поэтому они здесь, а не в экшене - иначе те же реквизиты, добавленные из
 * админки или импорта, прошли бы мимо проверок.
 *
 * @property int $id
 * @property int $uid
 * @property string $owner_name
 * @property string $account_number
 * @property int $created_by
 * @property string $created
 * @property string $updated
 *
 * @property-read User $user
 */
final class UserPayoutAccount extends MainModel
{
    /** Расчётный счёт в российском банке - ровно 20 цифр. */
    public const ACCOUNT_NUMBER_LENGTH = 20;

    public static function tableName(): string
    {
        return 'user_payout_account';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created',
                'updatedAtAttribute' => 'updated',
                'value' => new Expression('now()'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['uid', 'owner_name', 'account_number', 'created_by'], 'required'],
            [['uid', 'created_by'], 'integer'],
            [['owner_name'], 'trim'],
            [['owner_name'], 'string', 'max' => 255],
            [
                ['account_number'],
                'match',
                'pattern' => '/^\d{' . self::ACCOUNT_NUMBER_LENGTH . '}$/',
                'message' => 'Номер счёта должен содержать ' . self::ACCOUNT_NUMBER_LENGTH . ' цифр',
            ],
            [
                ['account_number'],
                'unique',
                'targetAttribute' => ['uid', 'account_number'],
                'message' => 'Эти реквизиты уже добавлены',
            ],
            [['uid', 'created_by'], 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'uid' => 'Пользователь',
            'owner_name' => 'ФИО владельца счёта',
            'account_number' => 'Номер счёта',
        ];
    }

    public static function find(): UserPayoutAccountQuery
    {
        return new UserPayoutAccountQuery(static::class);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'uid']);
    }

    public function belongsTo(int $userId): bool
    {
        return (int) $this->uid === $userId;
    }

    /** Номер для ответов API, логов и истории: видны банк и последние цифры, восстановить счёт нельзя. */
    public function getMaskedAccountNumber(): string
    {
        $number = (string) $this->account_number;

        return strlen($number) > 10
            ? substr($number, 0, 6) . str_repeat('*', strlen($number) - 10) . substr($number, -4)
            : str_repeat('*', strlen($number));
    }

    /** Наружу номер уходит только замаскированным - и в списке, и в ответе на создание. */
    public function fields(): array
    {
        return [
            'id' => fn (self $account): int => (int) $account->id,
            'owner_name',
            'account_number' => fn (self $account): string => $account->getMaskedAccountNumber(),
            'created',
        ];
    }
}
