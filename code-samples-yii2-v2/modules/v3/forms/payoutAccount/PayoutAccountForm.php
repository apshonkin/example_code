<?php

declare(strict_types=1);

namespace app\modules\v3\forms\payoutAccount;

use app\services\Actor;
use app\services\payoutAccount\PayoutAccountDraft;
use yii\base\Model;

/**
 * Тело запроса на добавление реквизитов.
 *
 * Форма проверяет ровно то, что зависит от запроса: пришли ли поля и похожи ли
 * они на реквизиты. Уникальность и существование пользователя проверяет модель
 * при сохранении - эти правила одинаковы для любого источника данных.
 *
 * Свойства намеренно без типов: сюда попадают сырые значения запроса, а тип
 * приводится один раз в toDraft() - после валидации.
 */
final class PayoutAccountForm extends Model
{
    /** Кому добавляем реквизиты. Пусто - значит себе. @var int|string|null */
    public $uid;

    /** @var string|null */
    public $owner_name;

    /** @var string|null */
    public $account_number;

    public function rules(): array
    {
        return [
            [['owner_name', 'account_number'], 'required'],
            [['owner_name', 'account_number'], 'trim'],
            [['uid'], 'integer', 'min' => 1],
            [['owner_name'], 'string', 'max' => 255],
            [
                ['account_number'],
                'match',
                'pattern' => '/^\d{20}$/',
                'message' => 'Номер счёта должен содержать 20 цифр',
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'uid' => 'ID пользователя',
            'owner_name' => 'ФИО владельца счёта',
            'account_number' => 'Номер счёта',
        ];
    }

    /** Тело запроса приходит без обёртки, поэтому load() работает без имени формы. */
    public function formName(): string
    {
        return '';
    }

    public function toDraft(Actor $actor): PayoutAccountDraft
    {
        return new PayoutAccountDraft(
            $this->uid === null ? $actor->id : (int) $this->uid,
            (string) $this->owner_name,
            (string) $this->account_number,
        );
    }
}
