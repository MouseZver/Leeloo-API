<?php

$data = [
	'email'		=> 'mail@mail.ru', 
	'phone'		=> 'phone', 
	'accountId'	=> $id_account,
	
	'id' => &$id_insert_order
];

try
{
	$leeloo_order_id = $leeloo -> orderPending( $data, $offer );
	
	$db -> query( 'INSERT... order pay, status = pending AND leeloo_order_id = {$leeloo_order_id}' );
	
	$id_insert_order = $db -> last_insert_id();
}
catch ( \Nouvu\Leeloo\LeelooOrderFailed $e )
{
	$logger -> set( $e -> getMessage() ) -> set( json_encode ( $leeloo -> getResponse() ) );
	
	$db -> query( 'INSERT... order pay, status = pending AND leeloo_order_id = null' );
	
	$id_insert_order = $db -> last_insert_id();
	
	$leeloo -> saveFailure();
}

