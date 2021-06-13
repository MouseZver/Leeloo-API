<?php

/*
	WARNING !!!
	This is code example logic
*/
/* CREATE TABLE `leeloo_queue_cron` (
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
COLLATE = utf8_bin; */

$storage = new \Nouvu\Database\QueryStorageBank( $db_link );

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



# composer require nouvu/logger
//$logger = new \Nouvu\Logger( __DIR__ . '/cron' );

$leeloo = new \Nouvu\Leeloo\Api( $config, true );

$response = $lrm -> query( 'SELECT `id`, `method`, `data` FROM `leeloo_queue_cron` WHERE `again` < 5 ORDER BY `again` ASC, `id` ASC, `priority` ASC LIMIT 30' );

while ( $queue = $response -> fetch( FETCH_ASSOC ) )
{
	try
	{
		$leeloo -> cron( $queue );
	}
	catch ( \Nouvu\Leeloo\LeelooOrderFailed $e )
	{
		//$logger -> set( $e -> getMessage() ) -> set( json_encode ( $leeloo -> getResponse() ) ) -> set( 'id leeloo_queue_cron: ' . $queue['id'] );
	}
}

