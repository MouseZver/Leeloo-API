<?php

declare ( strict_types = 1 );

namespace Nouvu\Leeloo;

use Nouvu\Config\Config;

final class Setting
{
	private Config $config;
	
	public function __construct ( Config $config )
	{
		$this -> config = $config;
	}
	
	public function token( string $token ): void
	{
		$this -> config -> set( 'leeloo.token', fn( &$config ) => $config = $token );
	}
	
	public function tags( array $tags ): void
	{
		$this -> config -> set( 'leeloo.tags', fn( &$config ) => $config = $tags );
	}
	
	public function templates( array $templates ): void
	{
		$this -> config -> set( 'leeloo.templates', fn( &$config ) => $config = $templates );
	}
	
	public function order( array $order ): void
	{
		foreach ( [ 'paymentCreditsId', 'offer' ] AS $name )
		{
			if ( ! isset ( $order[$name] ) )
			{
				throw new LeelooException( 'Order key - not found: ' . $name );
			}
		}
		
		$this -> config -> set( 'leeloo.order', fn( &$config ) => $config = $order );
	}
	
	public function storageCall( array $storage_call ): void
	{
		$this -> config -> set( 'sql_callback', fn( &$config ) => $config = $storage_call + $config );
	}
	
	public function data( string $name, array $keys, array $data ): void
	{
		foreach ( $keys AS $k => $v )
		{
			$this -> config -> set( $name, fn( &$config ) => $config[$v] = $data[$k] );
		}
	}
}