<?php

$data = [
	'status'		=> 'RESOLVED', // REJECTED
	'price'			=> 10,
	'currency'		=> 'USD',
	'userComments'	=> 'Hello world',
];

try
{
	$leeloo -> orderUpdate( $leeloo_order_id, $data );
	/* 
	$leeloo -> orderCompleted( $leeloo_order_id, 10, 'USD', 'Hello world' );
	$leeloo -> orderFailed( $leeloo_order_id, 10, 'USD', 'Hello world' );
	 */
}
catch ( \Nouvu\Leeloo\LeelooOrderFailed $e )
{
	$logger -> set( $e -> getMessage() ) -> set( json_encode ( $leeloo -> getResponse() ) );
	
	$leeloo -> saveFailure();
}

