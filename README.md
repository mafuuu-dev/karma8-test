### **Тестовое задание Karma8**

Вы разрабатываете сервис для рассылки уведомлений об истекающих подписках.

#### **Описание:**

За один и за три дня до истечения срока подписки, нужно отправить письмо пользователю с текстом:<br/>
**"{username}, your subscription is expiring soon"**.

#### **Схема таблицы users:**

- **username** - Имя пользователя.
- **email** - Email пользователя.
- **validts** - unix timestamp до которого действует ежемесячная подписка, либо 0 если подписки нет.
- **confirmed** - 0 или 1 в зависимости от того, подтвердил ли пользователь свой емейл по ссылке (пользователю после регистрации приходит письмо с уникальный ссылкой на указанный емейл, если он нажал на ссылку в емейле в этом поле устанавливается 1).
- **checked** - Была ли проверка емейла на валидацию (1) или не было (0).
- **valid** - Является ли емейл валидным (1) или нет (0).

#### **Требования к системе:**

- Таблица в DB с пользователями (**5 000 000+ строк**).
- Около **80%** пользователей не имеют подписки.
- Только **15%** пользователей подтверждают свой емейл (поле: **confirmed**).
- Внешняя функция **check_email($email)** проверяет емейл на валидность (на валидный емейл письмо точно дойдёт) и возвращает 0 или 1. Функция работает от 1 секунды до 1 минуты. **Вызов функции стоит 1 руб.**
- Функция **send_email($from, $to, $text)** отсылает емейл. Функция работает от 1 секунды до 10 секунд.

#### **Ограничения**

- Необходимо регулярно отправлять емейлы об истечении срока подписки на те емейлы, на которые письмо точно дойдёт.
- Можно использовать **cron**.
- Можно создать необходимые таблицы в DB или изменить существующие.
- Для функций **check_email** и **send_email** нужно написать "заглушки".
- Не использовать ООП.
- Очереди реализовать без использования менеджеров.

#### **Инструкция по запуску:**

- устанавливаем docker и docker-compose.
- устанавливаем в docker-compose.yml:**psql** anchor: ```<<: *fixtures```
- выполняем: ```docker-compose up psql```
- после того как docker-compose.yml:**psql** отработал завершаем процесс ```ctrl+c```
- устанавливаем в docker-compose.yml:**psql** anchor: ```<<: *default```
- устанавливаем в docker-compose.yml:**producer** command: ```command: php /app/handlers/producer.php```  
- выполняем: ```docker-compose up producer```
- устанавливаем в docker-compose.yml:**producer** command: ```command: supercronic /app/cron/crontab```
- выполняем: ```docker-compose up -d && docker-compose logs -f```

#### **Примечание:**

- для адекватной работы я использовал **Throwable/Exception, PgSql**, поэтому код все таки содержит немного ООП.
- среднее время заполнения таблицы users ~ **165 секунд**.
- ресурсы необходимые для поднятия **10 consumers - 2.5 cpus / 2.5 gb**.
- при увеличении количества **consumers** (по умолчанию: ```replicas: 10```), следует иметь в виду, что **каждый consumer может создать до 100 workers, а следовательно увеличится потребление ресурсов**.
- **!!!DANGEROUS OPERATION: docker system prune!!!**: чтобы запустить заново: ```docker-compose stop && docker system prune && docker volume rm karma8-test_psql-data```.