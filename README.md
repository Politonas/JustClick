JustClick
=========

PHP interface to JustClick.Ru

Проверка работоспособности
==========================

Создан специальный временный скрип, для проерки как запускается разрабатываемый класс.
    php justclick2.php
Запуск тестов
    phpunit -c app/
    phpunit -c app/ src/Acme/StoreBundle/Tests/Controller/
    phpunit -c app/ src/Acme/StoreBundle/Tests/Controller/JustClick.php
Для сборки проекта и запуска тестов
    ant

Работа с Composer
=================

Обновление зависимостей и библиотек
    php composer.phar update
Обновление самого запускаемого файла composer.phar
    php composer.phar self-update
Добавление новой зависимости
    php composer.phar require guzzlehttp/guzzle:~3


Работа с Git
===========

Фиксирование изменений
    git commit -a -m "added travis.yml"
Запись изменений на GitHub
    git push https://github.com/bakulev/JustClick.git

Изменения
========

Сохранение cookie.
Чтобы сохранять cookie в файл сделал в конструкторе:
    $this->client = new Client();
    // Установка перманентных cookie в файл.
    $cookie_file_name = '/tmp/justclick_cookie.jar';
    $cookiePlugin = new CookiePlugin(new FileCookieJar($cookie_file_name));
    $this->client->getClient()->getEventDispatcher()->addSubscriber($cookiePlugin);
А в 
    vendor/guzzle/plugin-cookie/Guzzle/Plugin/Cookie/CookieJar/ArrayCookieJar.php
исправил метод 
    function serialize $this->all(null, null, null, false, false))); 
поставил false, false, чтобы сохранялись не только долгие cookie, но и сессионные.

