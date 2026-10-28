# Примеры кода, PHP 8.3 / Yii2

Куски рабочего REST API: платформа налоговых деклараций, PostgreSQL с master/slave,
26 модулей, две живые версии API. Взял три сквозных сюжета, чтобы было видно не
отдельные файлы, а как у меня устроены слои, работа с БД и внешние интеграции.

Код форматирован по PSR-12, проверяется `phpcs` и `phpstan` (конфиги в корне),
типы стоят везде, где их позволяет фреймворк.

## Как читать

Быстрый маршрут - 10 минут, по одному файлу на слой:

1. `modules/v3/actions/payoutAccount/CreateAction.php` - экшен целиком, 15 строк
2. `services/payoutAccount/PayoutAccountService.php` - сценарий, который он вызывает
3. `models/UserPayoutAccount.php` + `models/query/UserPayoutAccountQuery.php` - данные
4. `services/dashboard/ClosingSpeedCalculator.php` + `tests/.../ClosingSpeedCalculatorTest.php` - расчёт и тест на него
5. `services/payment/yaSplit/CallbackHandler.php` - транзакции и повторные колбэки платёжки

## Как это разложено по слоям

```
экшен / консольная команда   транспорт: разобрать запрос, отдать ответ или код возврата
        |
формы (yii\base\Model)       проверка того, что зависит от запроса
        |
services/                    сценарий: права, порядок шагов, транзакция, побочные эффекты
        |
модели + query-классы        данные: правила целостности, связи, условия выборки
```

Правила, которых я держусь:

- **Экшен не содержит сценария.** В нём разбор запроса и ответ - и всё. Сценарий
  лежит в `services/`, потому что тот же самый нужен консоли, CRM и очереди:
  `PayoutAccountService::add()` вызывается из v3 и из импорта партнёра, а
  `SettingRolloverService::prepare()` - из крона.
- **Домен не знает про HTTP.** Сервис бросает `NotFoundException`,
  `AccessDeniedException`, `ValidationException`; перевод в код ответа - в
  `services/exceptions/HttpStatus.php`, единственном месте, где домен встречается
  с транспортом. Консольная команда переводит те же ошибки в exit-код.
- **Права проверяются в сценарии, а не в экшене.** Для этого сервис принимает
  `Actor` (`services/Actor.php`) вместо чтения `Yii::$app->user`: тогда правило
  "свои реквизиты правит клиент, чужие - только сотрудник" описано один раз и
  работает под кроном.
- **Зависимости - через конструктор.** Экшены и команды объявляют сервисы в
  конструкторе, контейнер Yii их подставляет сам (см. регистрацию ниже).
  Внутри классов нет `new` на зависимостях, поэтому сценарии тестируются
  на заглушках.
- **Запросы - через ActiveRecord и ActiveQuery-классы.** Условия выборки названы
  своими именами и лежат в query-классе:

  ```php
  MasterVerification::find()
      ->closedBy($expertId, $period)
      ->ofServices(DashboardSettingPoint::SPEED_RATED_SERVICES)
      ->exceptCurrentYearDeclarations()
      ->selectClosingDelay()
      ->asArray()
      ->all(MasterVerification::getDbSlave());
  ```

  Раньше на этом месте был SQL на полтора экрана с `CASE`, тремя CTE и двенадцатью
  именованными параметрами под id услуг. Сейчас в БД остаётся только то, что она
  правда считает лучше - разница дат; нормативы, бонусы и штрафы применяет
  `ClosingSpeedCalculator`, у которого нет зависимостей и есть юнит-тест.
- **Правила целостности - на модели.** Формат счёта, запрет дублей, ссылка на
  живого пользователя проверяются в `rules()`, а не в экшене: те же данные
  приходят из личного кабинета, из CRM и из импорта.
- **Константы предметной области - на моделях.** Ключи настроек, состав пакетных
  услуг, статусы - рядом с таблицей, к которой относятся.
- **Внешние API - за интерфейсом.** `SplitGateway` описывает рассрочку так, как её
  видит наш код; `YaSplitGateway` прячет legacy-компонент с его
  `requestResult`/`requestError`.

## Что в комплекте

**Реквизиты для выплат** - вертикальный срез: миграция, модель, форма, сценарий, два экшена.

```
migrations/m251006_064636_create_user_payout_account.php
models/UserPayoutAccount.php
models/query/UserPayoutAccountQuery.php
modules/v3/forms/payoutAccount/PayoutAccountForm.php
modules/v3/actions/ApiAction.php
modules/v3/actions/payoutAccount/CreateAction.php
modules/v3/actions/payoutAccount/DeleteAction.php
services/Actor.php
services/payoutAccount/PayoutAccountDraft.php
services/payoutAccount/PayoutAccountService.php
services/exceptions/*.php
```

**Оплата в рассрочку** - внешняя интеграция и колбэк, который приходит по два раза.

```
modules/payment/actions/ApiAction.php
modules/payment/actions/yaSplit/CreateAction.php
modules/payment/actions/yaSplit/CallbackAction.php
services/payment/yaSplit/SplitGateway.php
services/payment/yaSplit/YaSplitGateway.php
services/payment/yaSplit/SplitCheckout.php
services/payment/yaSplit/CheckoutService.php
services/payment/yaSplit/CallbackHandler.php
```

Здесь интереснее всего идемпотентность: перевод оплаты в "проведена" сделан
атомарным `updateAll` с условием на текущий статус, поэтому два одновременных
колбэка платёжки не оплатят заказ дважды - без блокировок и без гонки между
"прочитал" и "обновил". Побочные эффекты (заметки, уведомления) выполняются
после коммита: запись в общую таблицу внутри `SERIALIZABLE`-транзакции роняла её
с 40001 именно на дублирующихся колбэках.

**Дашборд эксперта и крон** - расчёт показателя и подготовка настроек на месяц.

```
components/values/Period.php
models/MasterVerification.php
models/OrderDocumentsStatus.php
models/query/MasterVerificationQuery.php
modules/dashboard/models/settings/DashboardSettingPoint.php
modules/dashboard/models/dashboards/ExpertDashboard.php
services/dashboard/ClosingSpeedCalculator.php
services/dashboard/ClosingSpeedService.php
services/dashboard/SpeedStandard.php
services/dashboard/SettingRolloverService.php
commands/DashboardController.php
tests/unit/services/dashboard/ClosingSpeedCalculatorTest.php
```

Крон раньше был двумя `INSERT ... UNION SELECT -1 ... UNION SELECT -12` и
`UPDATE ... FROM`; теперь это `SettingRolloverService::prepare()` - идемпотентный,
в транзакции, с валидацией моделей и понятным выводом в консоль.

## Как это включается в приложение

Роут (`config/url/base.php`) и экшены контроллера:

```php
'POST v3/user/payout-account'        => 'v3/user-payout-account/create',
'DELETE v3/user/payout-account/<id:\d+>' => 'v3/user-payout-account/delete',
```

```php
public function actions(): array
{
    return [
        'create' => CreateAction::class,
        'delete' => DeleteAction::class,
    ];
}
```

Зависимости, которые контейнер подставляет в конструкторы (`config/web.php`):

```php
Yii::$container->setSingleton(SplitGateway::class, YaSplitGateway::class);
```

Остальное контейнер собирает сам по типам параметров - отдельных описаний для
сервисов не нужно.

## Проверки

```bash
vendor/bin/phpcs                     # PSR-12, конфиг phpcs.xml.dist
vendor/bin/phpstan analyse           # уровень 6, конфиг phpstan.neon.dist
vendor/bin/codecept run unit         # тесты
```

`phpcs` нужен версии 3.10+ или 4.x - более старые не знают `readonly class` и
типизированных констант PHP 8.3.

## Оговорки

Это вырезка из большого проекта, а не отдельное приложение: файлы ссылаются на
существующие классы платформы (`User`, `Order`, `Payment`, `Service`, базовый
`MainModel`, транспорт `ITransport`, `InfoBlockBuilder`) - они не приведены, чтобы
комплект читался за 10 минут. В `Order` при этом добавлена связь
`getDocumentsStatus()` на `OrderDocumentsStatus` - без неё запрос дашборда
собирался бы join-ом по месту. Названия услуг и часть доменных терминов изменены.
