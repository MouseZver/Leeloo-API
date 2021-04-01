<?php

declare ( strict_types = 1 );

namespace Nouvu\Leeloo;

use Nouvu\Config\Config;

class Core
{
	protected Config $config;
	
	protected Setting $set;
	
	protected function stream( string $link, array $data, string $request ): array
	{
		$curl = curl_init ();
		
		curl_setopt_array ( $curl, 
		[
			CURLOPT_URL             => $link,
			CURLOPT_POSTFIELDS		=> json_encode ( $data ),
			CURLOPT_RETURNTRANSFER  => true,
			CURLOPT_FOLLOWLOCATION  => true,
			CURLOPT_CUSTOMREQUEST	=> $request,
			CURLOPT_HTTPHEADER      => [
				'Content-Type: application/json',
				'X-Leeloo-AuthToken: ' . $this -> getData( 'leeloo.token' ),
			],
		] );
		
		// string(42) "Too many requests, please try again later."
		$response = curl_exec ( $curl );
		
		curl_close ( $curl );
		
		return json_decode ( $response, true ) ?? [ 'response' => $response ];
	}
	
	protected function setData( string $name, array $keys, array $data ): void
	{
		$this -> set -> data( $name, $keys, $data );
	}
	
	protected function getData( string $name ) # : mixed
	{
		return $this -> config -> get( $name );
	}
	
	protected function setDataTag( array ...$data ): void
	{
		if ( count ( $data[1] ) == 2 )
		{
			$this -> setData( 'tag', $data[0], $data[1] );
			
			return;
		}
		
		$this -> setData( 'tag', [ 'status' ], $data[1] );
	}
	
	protected function getDataTag( string $name ) # : mixed
	{
		return $this -> getData( 'tag.' . $name );
	}
	
	protected function tag( string $api_name, string $type, array $args ): self
	{
		$this -> setDataTag( [ 'leeloo_id', 'status' ], $args );
		
		$status_name = $this -> getDataTag( 'status' );
		
		$leeloo_id = $this -> getDataTag( 'leeloo_id' );
		
		$status_id = $this -> getData( 'leeloo.tags.' . $status_name );
		
		if ( is_null ( $status_id ) )
		{
			throw new LeelooException( 'Not found status id for tag: ' . $status_name );
		}
		
		$this -> setVars( __FUNCTION__, [ $api_name, $type, [ $leeloo_id, $status_name ] ], 3 );
		
		$link = sprintf ( static :: API[$api_name], $leeloo_id, $type );
		
		$send = $this -> getData( 'send' );
		
		$this -> config -> set( 'send', fn( &$config ) => $config = $this -> cron );
		
		$this -> send( $link, [ 'tag_id' => $status_id, 'api' => $api_name ], 'PUT' );
		
		$this -> config -> set( 'send', fn( &$config ) => $config = $send );
		
		return $this;
	}
	
	protected function verify( array $names, array $data, callable $callable = null ): void
	{
		foreach ( $names AS $key )
		{
			if ( ! isset ( $data[$key] ) )
			{
				throw new LeelooException( 'Missing parameter: ' . $key );
			}
			
			if ( is_callable ( $callable ) )
			{
				$callable( $key );
			}
		}
	}
	
	protected function setVars( string $name, array $args, int $priority ): void
	{
		$this -> setData( 'vars', [ 'method', 'args', 'priority' ], [ $name, $args, $priority ] );
	}
	
	protected function getVars(): array
	{
		return $this -> getData( 'vars' );
	}
	
	protected function VerifyOrderStatus(): void
	{
		if ( empty ( $this -> getResponse()['status'] ) )
		{
			throw new LeelooOrderFailed( 'The sent order failed' );
		}
	}
}