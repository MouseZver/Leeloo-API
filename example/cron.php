<?php

/*
	WARNING !!!
	This is code example logic
*/

$leeloo = new \Nouvu\Leeloo\Api( $config );

$leeloo -> setSqlCallback( [
	'delete' => fn( int $id ) => $db -> query( 'DELETE FROM `leeloo_queue_cron` WHERE `id` = ' . $id ),
	'update' => fn( int $id, string $response ) => $db -> prepare( 'UPDATE `leeloo_queue_cron` SET `again` = `again` + 1, response = ? WHERE `id` = ?', [ $response, $id ] ),
] );

$response = $lrm -> query( 'SELECT `id`, `method`, `data` FROM `leeloo_queue_cron` ORDER BY `id` ASC, `again` ASC LIMIT 30' );

while ( $queue = $response -> fetch( FETCH_ASSOC ) )
{
	try
	{
		$leeloo -> cron( $queue );
	}
	catch ( \Nouvu\Leeloo\LeelooOrderFailed $e )
	{
		$logger -> set( $e -> getMessage() ) -> set( json_encode ( $leeloo -> getResponse() ) ) -> set( 'id row: ' . $queue['id'] );
	}
}

/* CREATE TABLE `leeloo_queue_cron` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `method` VARCHAR(20) NOT NULL,
  `data` JSON NOT NULL,
  `response` JSON NOT NULL,
  `again` INT NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_bin; */