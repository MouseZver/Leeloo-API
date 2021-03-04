<?php

declare ( strict_types = 1 );

namespace Nouvu\Api;

use Nouvu\Config\Config;

final class Leeloo
{
	const API_TAG = 'https://api.leeloo.ai/api/v2/people/%s/%s';
	
	const API_TEMPLATE = 'https://api.leeloo.ai/api/v1/messages/send-template';
	
	const API_ORDER = 'https://api.leeloo.ai/api/v1/orders';
	
	private array $response = [];
	
	private bool $send;
	
	private Config $config;
	
	private array $configuration_keys = [ 
		'token', 
		'tags', 
		'templates', 
		'order' 
	];
	
	public function __construct ( array $leeloo, bool $send = true )
	{
		$this -> send = $send;
		
		$this -> config = new Config( [ 
			'leeloo' => [],
			'sql_callback' => [
				'delete' => fn(): bool => false,
				'update' => fn(): bool => false,
				'insert' => fn(): bool => false,
			]
		] );
		
		foreach ( $leeloo AS $key => $data )
		{
			if ( ! in_array ( $key, $this -> configuration_keys ) )
			{
				throw new LeelooException( 'Configuration key - not found: ' . $key );
			}
			
			$this -> {'set_' . $key}( $data );
		}
	}
	
	protected function set_token( array $token ): void
	{
		$this -> config -> set( 'leeloo.token', fn( &$config ) => $config = $token );
	}
	
	protected function set_tags( array $tags ): void
	{
		$this -> config -> set( 'leeloo.tags', fn( &$config ) => $config = $tags );
	}
	
	protected function set_templates( array $templates ): void
	{
		$this -> config -> set( 'leeloo.templates', fn( &$config ) => $config = $templates );
	}
	
	protected function set_order( array $order ): void
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
	
	public function setSqlCallback( array $sql_callback ): void
	{
		$this -> config -> set( 'sql_callback', fn( &$config ) => $config = $sql_callback + $config );
	}
	
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
	
	public function send( string $link, array $data, string $request = 'POST', int $id = 0, bool $cron = false ): bool
	{
		$this -> response = ( $this -> send ? $this -> stream( $link, $data, $request ) : [ 'cron' => $cron ] );
		
		if ( ! empty ( $this -> response['status'] ) )
		{
			if ( $cron )
			{
				$this -> getData( 'sql_callback.delete' )( $id );
			}
			
			return true;
		}
		
		if ( $cron )
		{
			$this -> getData( 'sql_callback.update' )( $id );
			
			return false;
		}
		
		$call = $this -> getData( 'sql_callback.insert' );
		
		$call( $link, json_encode ( $data ), $request, json_encode ( $this -> response ) );
		
		return false;
	}
	
	protected function setData( string $name, array $keys, array $data ): void
	{
		foreach ( $keys AS $k => $v )
		{
			$this -> config -> set( $name, fn( &$config ) => $config[$v] = $data[$k] );
		}
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
	
	protected function tag( string $type, array $args ): self
	{
		$this -> setDataTag( [ 'leeloo_id', 'status' ], $args );
		
		$status_name = $this -> getDataTag( 'status' );
		
		$status_id = $this -> getData( 'leeloo.tags.' . $status_name );
		
		if ( is_null ( $status_id ) )
		{
			throw new LeelooException( 'Not found status id for tag: ' . $status_name );
		}
		
		$link = sprintf ( self :: API_TAG, $this -> getDataTag( 'leeloo_id' ), $type . '-tag' );
		
		$this -> send( $link, [ 'tag_id' => $status_id ], 'PUT' );
		
		return $this;
	}
	
	public function addTag( /* int | string */ ...$args ): self
	{
		return $this -> tag( 'add', $args );
	}
	
	public function removeTag( /* int | string */ ...$args ): self
	{
		return $this -> tag( 'remove', $args );
	}
	
	public function sendTemplate( string $login, string $template ): void
	{
		$template_id = $this -> getData( 'leeloo.templates.' . $template );
		
		if ( is_null ( $template_id ) )
		{
			throw new LeelooException( 'template_id not found by name: ' . $template );
		}
		
		$this -> send( self :: API_TEMPLATE, [ 
			'account_id' => $login,
			'template_id' => $template_id
		] );
	}
	
	public function get_response(): array
	{
		return $this -> response;
	}
	
	public function get_order_id(): ?string
	{
		return $this -> response['data']['id'] ?? null;
	}
	
	public function pending_order( array $data, string $offer ): ?string
	{
		foreach ( [ 'email', 'phone', 'accountId' ] AS $key )
		{
			if ( empty ( $data[$key] ) )
			{
				throw new LeelooException( 'Missing parameter: ' . $key );
			}
		}
		
		$offerId = $this -> getData( 'leeloo.order.offer.' . $offer );
		
		if ( is_null ( $offerId ) )
		{
			throw new LeelooException( 'offerId not found by name: ' . $offer );
		}
		
		$this -> response = $this -> stream( self :: API_ORDER, $data + [
			'paymentCreditsId'	=> $this -> getData( 'leeloo.order.paymentCreditsId' ),
			'transactionDate'	=> gmdate ( 'Y-m-d H:i:s' ),
			'offerId'			=> $offerId,
			'isNotifyAccount'	=> 'false',
		], 
		'POST' );
		
		if ( empty ( $this -> response['status'] ) )
		{
			throw new LeelooOrderFailed( 'The sent order failed' );
		}
		
		return $this -> get_order_id();
	}
	
	public function completed_order( string $leeloo_order_id, /* int | float */ $price, string $currency = 'USD', string $comments = 'card' ): void
	{
		$this -> response = $this -> stream( self :: API_ORDER . '/' . $leeloo_order_id, [
			'status'		=> 'RESOLVED',
			'price'			=> round ( trim ( $price ) * 100 ),
			'currency'		=> 'USD',
			'paymentDate'	=> gmdate ( 'Y-m-d H:i:s' ),
			'userComments'	=> $comments,
		], 
		'POST' );
		
		if ( empty ( $this -> response['status'] ) )
		{
			throw new LeelooOrderFailed( 'The sent order failed' );
		}
	}
}