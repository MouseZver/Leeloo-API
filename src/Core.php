<?php

declare ( strict_types = 1 );

namespace Nouvu\Api;

use Nouvu\Config\Config;

class Core
{
	protected Config $config;
	
	protected Setting $set;
	
	protected function stream( string $link, array $data, string $request ): ?array
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
		
		$response = curl_exec ( $curl );
		
		curl_close ( $curl );
		
		return json_decode ( $response ?: '[]', true );
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
	
	protected function tag( string $link, string $type, array $args ): self
	{
		$this -> setDataTag( [ 'leeloo_id', 'status' ], $args );
		
		$status_name = $this -> getDataTag( 'status' );
		
		$status_id = $this -> getData( 'leeloo.tags.' . $status_name );
		
		if ( is_null ( $status_id ) )
		{
			throw new LeelooException( 'Not found status id for tag: ' . $status_name );
		}
		
		$link = sprintf ( $link, $this -> getDataTag( 'leeloo_id' ), $type );
		
		$this -> send( $link, [ 'tag_id' => $status_id ], 'PUT' );
		
		return $this;
	}
}