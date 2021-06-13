Leeloo API - PHP ver7.4.0
=
[![Latest Unstable Version](https://poser.pugx.org/nouvu/leeloo/v/stable)](https://packagist.org/packages/nouvu/leeloo) [![License](https://poser.pugx.org/nouvu/leeloo/license)](//packagist.org/packages/nouvu/leeloo)

## Installation
[Composer](http://getcomposer.org). Run the following command to install it:
> composer require nouvu/leeloo

## 1.Start

Preparing for launch and configuring the api Leeloo.ai

```php
<?php

require 'vendor/autoload.php';

$pdo = new \PDO( ... ); # a tool for working with database (example)


/*
	https://github.com/MouseZver/Query-Storage-Bank
*/
$storage = new \Nouvu\Database\QueryStorageBank( $pdo );

$storage -> setEvent( 'leeloo_insert', static function ( $db, array $items ): void
{
	$stmt = $db -> prepare( 'INSERT INTO `leeloo_queue_cron` ( `method`, `data`, `response`, `priority` ) VALUES ( ?,?,?,? )' );
	
	foreach ( $items AS $data )
	{
		$stmt -> execute( $data );
	}
} );

$storage -> setEvent( 'leeloo_delete', static function ( $db, array $ids ): void
{
	$db -> query( sprintf ( 'DELETE FROM `leeloo_queue_cron` WHERE id IN ( %s )', implode ( ',', $ids ) ) );
} );

$storage -> setEvent( 'leeloo_update', static function ( $db, array $items ): void
{
	$stmt = $db -> prepare( 'UPDATE `leeloo_queue_cron` SET `again` = `again` + 1, `response` = ? WHERE `id` = ?' );
	
	foreach ( $items AS $data )
	{
		$stmt -> execute( $data );
	}
} );



$config = [
	'token' => '********************************************',
	'tags' => [
		'Happy_Event_7June2021' => '******ad20e82b2644******',
		/* ... name => hash */
	],
	'templates' => [
		'after_register' => '******174e0fbb000d******',
		/* ... name => hash */
	],
	'order' => [
		'paymentCreditsId' => '******c02eb3c7000d******',
		'offer' => [
			'online_trading_offer'	=> '******eb14c1880011******',
			/* ... name => hash */
		],
	]
];

/*
	@leeloo<array> - array containing authorization data and other tags, templates, etc.
	@storage<Nouvu\Database\QueryStorageBank>
	@send<bool> - send directly(true) or to a queue(false)( recommended ).
*/
$leeloo = new \Nouvu\Leeloo\Api( $config, $storage, true );
```

## 2.Cron
Set the task in cron to run (every 2 minutes) with the following code in the example:
https://github.com/MouseZver/Leeloo-API/blob/main/example/cron.php

## 3.Create table
```sql
CREATE TABLE `leeloo_queue_cron` (
	`id` INT NOT NULL AUTO_INCREMENT,
	`method` VARCHAR(20) NOT NULL,
	`data` JSON NOT NULL,
	`response` JSON NOT NULL,
	`priority` INT NOT NULL,
	`again` INT NOT NULL DEFAULT 1,
	`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_bin;
```
***

## Methods:

add-tag
[(https://leelooai.atlassian.net/wiki/spaces/DOC/pages/1389756423/API+v+2.0#Добавить-тег-человеку)](#Добавить-тег-человеку)
```php
/*
    @person_id<string> - leeloo person_id people
    @tag<string> - name tag ( no hash )
*/
$leeloo -> addTagPeople( string $person_id, string $tag ): \Nouvu\Leeloo\Api
```

remove-tag
[(https://leelooai.atlassian.net/wiki/spaces/DOC/pages/1389756423/API+v+2.0#Удалить-тег-у-человека)](#Удалить-тег-у-человека)
```php
/*
    @person_id - leeloo person_id people
    @tag - name tag ( no hash )
*/
$leeloo -> removeTagPeople( string $person_id, string $tag ): \Nouvu\Leeloo\Api
```

send-template
[(https://leelooai.atlassian.net/wiki/spaces/DOC/pages/1389756423/API+v+2.0#Отправка-шаблона-сообщения)](#Отправка-шаблона-сообщения)
```php
/*
    @person_id - leeloo $account_id
    @template - name template ( no hash )
*/
$leeloo -> sendTemplate( string $account_id, string $template ): void
```

send-message
[(https://leelooai.atlassian.net/wiki/spaces/DOC/pages/1389756423/API+v+2.0#Отправка-сообщений)](#Отправка-сообщений)
```php
/*
    @account_id - leeloo account_id
    @message - message text
    @sending - send directly(true) or to a queue(false)
*/
$leeloo -> sendMessage( string $account_id, string $message, bool $sending = true ): void
```

create order
[(https://leelooai.atlassian.net/wiki/spaces/DOC/pages/1389756423/API+v+2.0#Создать-ручной-ордер)](#Создать-ручной-ордер)
[(https://github.com/MouseZver/Leeloo-API/blob/main/example/failed_order_pending.php)](Пример)
```php
/*
    @account_id - leeloo account_id
    @message - message text
    @sending - send directly(true) or to a queue(false)
*/
$leeloo_order_id = $leeloo -> orderPending( string $email, string $phone, string $personId, string $offer ): ?string
```

update order - RESOLVED
[(https://leelooai.atlassian.net/wiki/spaces/DOC/pages/1389756423/API+v+2.0#Обновить-информацию-в-МАНУАЛ-(ручном)-ордере)](#Обновить-информацию-в-МАНУАЛ-(ручном)-ордере)
[(https://github.com/MouseZver/Leeloo-API/blob/main/example/failed_order_update.php)](Пример)
```php
/*
    @leeloo_order_id - leeloo order id
    @price - example: '100' (actual price that you receive from account)
    @currency - example: 'RUB'
    @comments - userComments example: 'actual date dont match'
*/
$leeloo -> orderCompleted( string $leeloo_order_id, int | float $price, string $currency = 'USD', string $comments = 'card' ): void
```

update order - REJECTED
[(https://leelooai.atlassian.net/wiki/spaces/DOC/pages/1389756423/API+v+2.0#Обновить-информацию-в-МАНУАЛ-(ручном)-ордере)](#Обновить-информацию-в-МАНУАЛ-(ручном)-ордере)
[(https://github.com/MouseZver/Leeloo-API/blob/main/example/failed_order_update.php)](Пример)
```php
/*
    @leeloo_order_id - leeloo order id
    @price - example: '100' (actual price that you receive from account)
    @currency - example: 'RUB'
    @comments - userComments example: 'actual date dont match'
*/
$leeloo -> orderFailed( string $leeloo_order_id, int | float $price, string $currency = 'USD', string $comments = 'card' ): void
```
***

Create by [MouseZver](//php.ru/forum/members/40235)
