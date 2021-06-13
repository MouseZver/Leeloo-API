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