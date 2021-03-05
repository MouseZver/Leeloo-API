<?php

declare ( strict_types = 1 );

namespace Nouvu\Api;

use Nouvu\Config\Config;

final class Leeloo extends Core
{
	const API_TAG = [
		1 => 'https://api.leeloo.ai/api/v1/accounts/%s/%s-tag',
		2 => 'https://api.leeloo.ai/api/v2/people/%s/%s-tag'
	];
	
	const API_TEMPLATE = 'https://api.leeloo.ai/api/v1/messages/send-template';
	
	const API_ORDER = 'https://api.leeloo.ai/api/v1/orders';
	
	private array $response = [];
	
	private bool $send;
	
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
		
		$this -> set = new Setting( $this -> config );
		
		foreach ( $leeloo AS $key => $data )
		{
			if ( ! in_array ( $key, $this -> configuration_keys ) )
			{
				throw new LeelooException( 'Configuration key - not found: ' . $key );
			}
			
			$this -> set -> {$key}( $data );
		}
	}
	
	public function setSqlCallback( array $sql_callback ): void
	{
		$this -> set -> sqlCallback( $sql_callback );
	}
	
	/*
		
	*/
	public function send( string $link, array $data, string $request = 'POST', bool $cron = false, int $id = 0 ): bool
	{
		$this -> response = ( $this -> send ? $this -> stream( $link, $data, $request ) : [ 'send' => 'setSqlCallback' ] );
		
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
	
	public function addTag( /* int | string */ ...$args ): self
	{
		return $this -> tag( self :: API_TAG[1], 'add', $args );
	}
	
	public function removeTag( /* int | string */ ...$args ): self
	{
		return $this -> tag( self :: API_TAG[1], 'remove', $args );
	}
	
	public function addTagPeople( /* int | string */ ...$args ): self
	{
		return $this -> tag( self :: API_TAG[2], 'add', $args );
	}
	
	public function removeTagPeople( /* int | string */ ...$args ): self
	{
		return $this -> tag( self :: API_TAG[2], 'remove', $args );
	}
	
	public function sendTemplate( string $account_id, string $template ): void
	{
		$template_id = $this -> getData( 'leeloo.templates.' . $template );
		
		if ( is_null ( $template_id ) )
		{
			throw new LeelooException( 'template_id not found by name: ' . $template );
		}
		
		$this -> send( self :: API_TEMPLATE, [ 
			'account_id' => $account_id,
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
	
	public function orderPending( array $data, string $offer ): ?string
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
	
	public function orderUpdate( string $leeloo_order_id, array $data ): void
	{
		foreach ( [ 'status', 'price', 'currency' ] AS $key )
		{
			if ( empty ( $data[$key] ) )
			{
				throw new LeelooException( 'Missing parameter: ' . $key );
			}
		}
		
		$this -> response = $this -> stream( self :: API_ORDER . '/' . $leeloo_order_id, [
			'status'		=> $data['status'],
			'price'			=> round ( trim ( $data['price'] ) * 100 ),
			'currency'		=> $data['currency'],
			'paymentDate'	=> gmdate ( 'Y-m-d H:i:s' ),
			'userComments'	=> $data['userComments'] ?? null,
		], 
		'POST' );
		
		if ( empty ( $this -> response['status'] ) )
		{
			throw new LeelooOrderFailed( 'The sent order failed' );
		}
	}
	
	public function orderCompleted( string $leeloo_order_id, /* int | float */ $price, string $currency = 'USD', string $comments = 'card' ): void
	{
		$this -> orderUpdate( $leeloo_order_id, [
			'status'		=> 'RESOLVED',
			'price'			=> $price,
			'currency'		=> $currency,
			'userComments'	=> $comments,
		] );
	}
	
	public function orderFailed( string $leeloo_order_id, /* int | float */ $price, string $currency = 'USD', string $comments = 'card' ): void
	{
		$this -> orderUpdate( $leeloo_order_id, [
			'status'		=> 'REJECTED',
			'price'			=> $price,
			'currency'		=> $currency,
			'userComments'	=> $comments,
		] );
	}
}