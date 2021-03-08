<?php

declare ( strict_types = 1 );

namespace Nouvu\Leeloo;

use Nouvu\Config\Config;

final class Api extends Core
{
	const API = [
		'tag_accounts' => 'https://api.leeloo.ai/api/v1/accounts/%s/%s-tag',
		'tag_people' => 'https://api.leeloo.ai/api/v2/people/%s/%s-tag',
		'send_template' => 'https://api.leeloo.ai/api/v1/messages/send-template',
		'send_message' => 'https://api.leeloo.ai/api/v1/messages/send-message',
		'orders_add' => 'https://api.leeloo.ai/api/v1/orders',
	];
	
	private array $response = [];
	
	private array $configuration_keys = [ 
		'token', 
		'tags', 
		'templates', 
		'order' 
	];
	
	public function __construct ( array $leeloo, bool $send = true )
	{
		$this -> config = new Config( [ 
			'leeloo' => [],
			'sql_callback' => [
				'delete' => fn(): bool => false,
				'update' => fn(): bool => false,
				'insert' => fn(): bool => false,
				'order'
			],
			'send' => $send
		] );
		
		$this -> set = new Setting( $this -> config );
		
		$this -> verify( $this -> configuration_keys, $leeloo, fn( string $key ) => $this -> set -> {$key}( $leeloo[$key] ) );
	}
	
	public function setSqlCallback( array $sql_callback ): void
	{
		$this -> set -> sqlCallback( $sql_callback );
	}
	
	public function send( string $link, array $data, string $request = 'POST', bool $cron = false, int $id = 0 ): bool
	{
		$this -> response = ( $this -> getData( 'send' ) ? $this -> stream( $link, $data, $request ) : [ 'send' => 'setSqlCallback' ] );
		
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
		
		$this -> save();
		
		return false;
	}
	
	public function addTag( /* int | string */ ...$args ): self
	{
		return $this -> tag( 'tag_accounts', 'add', $args );
	}
	
	public function removeTag( /* int | string */ ...$args ): self
	{
		return $this -> tag( 'tag_accounts', 'remove', $args );
	}
	
	public function addTagPeople( /* int | string */ ...$args ): self
	{
		return $this -> tag( 'tag_people', 'add', $args );
	}
	
	public function removeTagPeople( /* int | string */ ...$args ): self
	{
		return $this -> tag( 'tag_people', 'remove', $args );
	}
	
	public function sendTemplate( string $account_id, string $template ): void
	{
		$template_id = $this -> getData( 'leeloo.templates.' . $template );
		
		if ( is_null ( $template_id ) )
		{
			throw new LeelooException( 'template_id not found by name: ' . $template );
		}
		
		$this -> setVars( __FUNCTION__, func_get_args () );
		
		$this -> send( self :: API['send_template'], [ 
			'account_id' => $account_id,
			'template_id' => $template_id
		] );
	}
	
	public function sendMessage( string $account_id, string $message ): void
	{
		$this -> setVars( __FUNCTION__, func_get_args () );
		
		$this -> send( self :: API['send_message'], [ 
			'account_id' => $account_id,
			'text' => $message
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
		$this -> verify( [ 'email', 'phone', 'accountId' ], $data );
		
		$offerId = $this -> getData( 'leeloo.order.offer.' . $offer );
		
		if ( is_null ( $offerId ) )
		{
			throw new LeelooException( 'offerId not found by name: ' . $offer );
		}
		
		$this -> setVars( __FUNCTION__, func_get_args () );
		
		$this -> response = $this -> stream( self :: API['orders_add'], $data + [
			'paymentCreditsId'	=> $this -> getData( 'leeloo.order.paymentCreditsId' ),
			'transactionDate'	=> gmdate ( 'Y-m-d H:i' ),
			'offerId'			=> $offerId,
			'isNotifyAccount'	=> 'false',
		], 
		'POST' );
		
		$this -> VerifyOrderStatus();
		
		return $this -> get_order_id();
	}
	
	public function orderUpdate( string $leeloo_order_id, array $data ): void
	{
		$this -> verify( [ 'status', 'price', 'currency' ], $data );
		
		$this -> setVars( __FUNCTION__, func_get_args () );
		
		$this -> response = $this -> stream( self :: API['orders_add'] . '/' . $leeloo_order_id, [
			'status'			=> $data['status'],
			'price'				=> sprintf ( '%d', $data['price'] ),
			'currency'			=> $data['currency'],
			'paymentDate'		=> gmdate ( 'Y-m-d H:i' ),
			'userComments'		=> $data['userComments'] ?? null,
		], 
		'POST' );
		
		$this -> VerifyOrderStatus();
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
	
	public function save(): void
	{
		[ 'method' => $method, 'args' => $args ] = $this -> getVars();
		
		$this -> getData( 'sql_callback.insert' )( $method, $args );
	}
	
	public function cron( array $data ): void
	{
		/* $data -> 
		
		$this -> verify( [ 'id', 'method', 'args' ] );
		
		try
		{
			
		} */
	}
}