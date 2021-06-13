<?php


function create_order( \Nouvu\Leeloo\Api $leeloo, string $email, string $phone, string $personId, string $offer )
{
	$close = 1;
	
	while ( true )
	{
		try
		{
			return $leeloo -> orderPending( $email, $phone, $personId, $offer );
		}
		catch ( \Nouvu\Leeloo\LeelooOrderFailed $e )
		{
			sleep ( 2 );
			
			if ( ( $close++ ) == 5 )
			{
				header ( 'Location: /page-leeloo-error' );
				
				exit;
			}
		}
	}
}

$leeloo_order_id = create_order( $leeloo, $email, $phone, $personId, $offer );
