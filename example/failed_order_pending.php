<?php

$data = [
	'email'		=> 'mail@mail.ru', 
	'phone'		=> 'phone', 
	'accountId'	=> $id_account,
];

while ( true )
{
	try
	{
		$leeloo_order_id = $leeloo -> orderPending( $data, $offer );
		
		break;
	}
	catch ( \Nouvu\Leeloo\LeelooOrderFailed $e )
	{
		sleep ( 2 );
	}
}